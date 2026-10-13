<?php
/** Inspect prepared client archives without extraction or execution. */
declare(strict_types=1);
defined('CWP_BOOT') || exit;
function delivery_limits(): array {return ['archive'=>50*1024*1024,'expanded'=>200*1024*1024,'file'=>32*1024*1024,'entries'=>1000,'project'=>250*1024*1024,'site'=>1024*1024*1024];}
function delivery_archive_path(string $name): string {
    if($name===''||strlen($name)>240||!preg_match('/^[a-zA-Z0-9_.\/ @()+,-]+$/D',$name)||str_contains($name,'\\')||str_starts_with($name,'/')||str_ends_with($name,' ')||str_ends_with($name,'.'))throw new InvalidArgumentException('ZIP has an unsafe or unsupported entry path. Use simple relative file names.');
    $path=rtrim($name,'/');foreach(explode('/',$path) as $part){
        if($part===''||$part==='.'||$part==='..'||str_ends_with($part,'.')||str_ends_with($part,' ')||preg_match('/^(con|prn|aux|nul|com[0-9]|lpt[0-9])(?:\.|$)/iD',$part))throw new InvalidArgumentException('ZIP contains an ambiguous or unsafe path.');
        if(preg_match('/^(?:\.git|\.svn|\.hg|\.env(?:\..*)?|\.ssh|\.aws|\.installed\.php|config\.php|wp-config\.php|configuration\.php|proxy-config\.php|payment-master\.key|id_rsa|id_ed25519)$/iD',$part))throw new InvalidArgumentException('ZIP contains a private configuration or repository path. Prepare a sanitized delivery package.');
    }
    if(preg_match('/\.(?:zip|gz|tgz|tar|bz2|xz|7z|rar|sql|sqlite[0-9]*|db|bak|log|key|pem|p12|pfx)$/iD',$path))throw new InvalidArgumentException('Nested archives, database exports, logs and key files are not accepted in this files-only delivery feature.');
    return $path;
}
function delivery_secret_check(string $text): void {
    if(preg_match('/-----BEGIN [A-Z0-9 ]*PRIVATE KEY-----|\bAKIA[0-9A-Z]{16}\b|\bgh[pousr]_[A-Za-z0-9]{30,}\b/',$text))throw new InvalidArgumentException('ZIP contains a recognizable private key or credential.');
    // Conservative literal-value detection supplements, never replaces, the owner's content review.
    if(preg_match_all('/[\'"$]?(?:[a-z_]*password|passwd|[a-z_]*secret|api[_-]?key|transaction[_-]?key|signature[_-]?key|[a-z_]*token)[\'"]?\s*(?:=>|:|=|,)\s*[\'"]([^\'"\r\n]{1,512})[\'"]/i',$text,$matches))foreach($matches[1] as $value)if(!preg_match('/^(?:CHANGEME|REPLACE_ME|YOUR_[A-Z0-9_]+|\$\{[A-Z0-9_]+\}|<[A-Z0-9_ -]+>)$/D',$value))throw new InvalidArgumentException('ZIP contains a possible embedded credential. Use empty or clearly marked placeholder values.');
}
function delivery_inspect(string $path,string $scope): array {
    if(!in_array($scope,['static','files'],true))throw new InvalidArgumentException('Choose a supported package scope.');
    if(!class_exists('ZipArchive'))throw new RuntimeException('Ask your host to enable the PHP ZIP extension before uploading website releases.');
    $limits=delivery_limits();$bytes=is_file($path)?filesize($path):false;
    if($bytes===false||$bytes<22||$bytes>$limits['archive'])throw new InvalidArgumentException('Choose a ZIP no larger than 50 MiB.');
    $head=fopen($path,'rb');if(!$head)throw new RuntimeException('Upload could not be read.');$signature=fread($head,4);fclose($head);
    if($signature!=="PK\x03\x04")throw new InvalidArgumentException('Choose a regular ZIP without an executable prefix.');
    $zip=new ZipArchive();if($zip->open($path,ZipArchive::RDONLY|ZipArchive::CHECKCONS)!==true)throw new InvalidArgumentException('ZIP is corrupt or unsupported.');
    try{
        if($zip->numFiles<1||$zip->numFiles>$limits['entries'])throw new InvalidArgumentException('ZIP must contain 1–1000 entries.');
        $seen=[];$files=[];$total=0;
        for($index=0;$index<$zip->numFiles;$index++){
            $stat=$zip->statIndex($index);if(!$stat)throw new InvalidArgumentException('ZIP metadata could not be read.');
            if(str_contains($stat['name'],'//'))throw new InvalidArgumentException('ZIP contains an ambiguous path.');
            $name=delivery_archive_path($stat['name']);$key=strtolower($name);$directory=str_ends_with($stat['name'],'/');
            if(isset($seen[$key]))throw new InvalidArgumentException('ZIP contains duplicate or case-colliding paths.');$seen[$key]=$directory?'directory':'file';
            $opsys=0;$attributes=0;if(!$zip->getExternalAttributesIndex($index,$opsys,$attributes))throw new InvalidArgumentException('ZIP entry attributes could not be verified.');
            $type=($attributes>>16)&0170000;
            if($opsys===3&&!in_array($type,[0,0100000,0040000],true)||$type===0040000&&!$directory||$type===0100000&&$directory)throw new InvalidArgumentException('ZIP links and special files are not supported.');
            if(($stat['encryption_method']??-1)!==0||!in_array($stat['comp_method'],[0,8],true))throw new InvalidArgumentException('Encrypted or unsupported ZIP entries cannot be reviewed.');
            $size=(int)$stat['size'];$total+=$size;if($size<0||$size>$limits['file']||$total>$limits['expanded']||$total>max(10*1024*1024,$bytes*200))throw new InvalidArgumentException('ZIP exceeds expanded-file or compression limits.');
            if($directory){if($size!==0)throw new InvalidArgumentException('ZIP directory contains unexpected data.');continue;}
            if(in_array($key,['handover.md','licenses.md'],true)&&$size<20)throw new InvalidArgumentException('Handover and license documents must contain useful instructions, not empty placeholders.');
            if($scope==='static'&&preg_match('/\.(?:php[0-9]?|phtml|phar|cgi|pl|py|rb|asp[x]?|jsp|sh)$/iD',$name))throw new InvalidArgumentException('Static-site scope cannot include server-side programs. Choose files-only scope.');
            $stream=$zip->getStreamIndex($index);if(!$stream)throw new InvalidArgumentException('ZIP entry cannot be read.');
            $hash=hash_init('sha256');$crc=hash_init('crc32b');$read=0;$tail='';
            try{while(!feof($stream)){$chunk=fread($stream,65536);if($chunk===false||$chunk===''&&!feof($stream))throw new InvalidArgumentException('ZIP entry read failed.');$read+=strlen($chunk);if($read>$size)throw new InvalidArgumentException('ZIP content exceeds declared size.');hash_update($hash,$chunk);hash_update($crc,$chunk);delivery_secret_check($tail.$chunk);$tail=substr($tail.$chunk,-4096);}}finally{fclose($stream);}
            if($read!==$size||strtolower(hash_final($crc))!==sprintf('%08x',$stat['crc']))throw new InvalidArgumentException('ZIP content failed its integrity check.');
            $files[]=['path'=>$name,'bytes'=>$size,'sha256'=>hash_final($hash)];
        }
        foreach(array_keys($seen) as $name){$parts=explode('/',$name);array_pop($parts);while($parts){if(($seen[implode('/',$parts)]??null)==='file')throw new InvalidArgumentException('ZIP contains overlapping file and directory paths.');array_pop($parts);}}
        if(($seen['handover.md']??null)!=='file'||($seen['licenses.md']??null)!=='file')throw new InvalidArgumentException('Include HANDOVER.md with installation/content instructions and LICENSES.md with transfer/dependency terms.');
        if($scope==='static'&&($seen['index.html']??null)!=='file')throw new InvalidArgumentException('A static website needs index.html at the archive root.');
        if(!$files)throw new InvalidArgumentException('ZIP has no files.');
        usort($files,fn($a,$b)=>strcmp($a['path'],$b['path']));return ['bytes'=>$bytes,'sha256'=>hash_file('sha256',$path),'expanded_bytes'=>$total,'files'=>$files];
    }finally{$zip->close();}
}
