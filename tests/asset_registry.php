<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
// Pure static regression: every literal asset('name') used by a shipping
// PHP file must resolve through the closed map in includes/urls.php and the
// mapped file must exist under site/. Catches unregistered stylesheet/script
// references that otherwise throw at render time (HTTP 500).
define('CWP_BOOT',true);
define('CWP_VERSION','0.0.0-test');
$GLOBALS['cwp_config']=['site_url'=>'https://example.test'];
$siteRoot=realpath(__DIR__.'/../site');
require $siteRoot.'/includes/urls.php';
$checks=[];
function ar_check(string $name,bool $passed):void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}

$usages=[];$dynamic=[];
$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($siteRoot,FilesystemIterator::SKIP_DOTS));
foreach($iterator as $file){
    if($file->getExtension()!=='php')continue;
    $path=$file->getPathname();
    // The canonical installer embeds generated copies of these functions;
    // vendored interface stubs are not application call sites.
    if(str_contains($path,'bootstrap.generated.php')||str_contains($path,DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR))continue;
    $source=(string)file_get_contents($path);
    if(preg_match_all("/asset\\('([^']+)'\\)/",$source,$m))foreach($m[1] as $name)$usages[$name][]=$file->getFilename();
    // Dynamic calls are allowed only where the value stays inside a literal
    // allowlist (image_url cover fallback); flag anything else for review.
    if(preg_match('/asset\\((?!\s*\')/',$source))$dynamic[]=$file->getFilename();
}
ar_check('Shipping source contains literal asset references',count($usages)>=10);
ar_check('Only the audited image_url allowlist uses dynamic asset()',array_diff($dynamic,['images.php','urls.php'])===[]);

$resolved=0;
foreach(array_keys($usages) as $name){
    try{$url=asset($name);}catch(InvalidArgumentException){ar_check('asset('.$name.') resolves through the map',false);continue;}
    $resolved++;
    $path=parse_url($url,PHP_URL_PATH);
    $base=(string)parse_url('https://example.test',PHP_URL_PATH);
    $relative=$base!==''&&str_starts_with($path,$base.'/')?substr($path,strlen($base)+1):ltrim($path,'/');
    ar_check('asset('.$name.') maps to an existing file',is_file($siteRoot.'/'.$relative));
}
ar_check('Every referenced asset resolves ('.$resolved.' names)',$resolved===count($usages));

// The map must not carry entries pointing at missing files, which would break
// the first layout that references them.
foreach(new ReflectionFunction('asset')->getStaticVariables()['paths'] as $name=>$target)
    ar_check('Registered asset '.$name.' exists on disk',is_file($siteRoot.'/'.$target));

@mkdir(__DIR__.'/../qa',0700,true);
file_put_contents(__DIR__.'/../qa/asset-registry-results.json',json_encode(['passed'=>true,'php'=>PHP_VERSION,'assets'=>count($usages),'scope'=>'Static asset-map registry only; no HTTP rendering or deployment acceptance','checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
echo 'PASS: '.count($checks).' asset registry checks ('.count($usages)." referenced names)\n";
