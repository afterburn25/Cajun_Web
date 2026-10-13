<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function image_url(array $item): string { return !empty($item['media_id']) ? site_url('media/index.php?id='.rawurlencode($item['media_id'])) : asset(in_array($item['cover']??'', ['project-restaurant.webp','project-construction.webp','project-wellness.webp'],true)?$item['cover']:'project-placeholder.svg'); }

function upload_image(string $field='image'): ?string {
    $file=$_FILES[$field]??null; if(!$file || ($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return null;
    if(is_array($file['error']??null) || $file['error']!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) throw new InvalidArgumentException('The upload did not complete. Please try a smaller image.');
    if($file['size']>6*1024*1024) throw new InvalidArgumentException('Images must be no larger than 6 MB.');
    if(!extension_loaded('gd')) throw new RuntimeException('Enable the PHP GD extension to upload images.');
    $info=@getimagesize($file['tmp_name']); $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if(!$info || !in_array($mime,['image/jpeg','image/png','image/webp'],true) || $info[0]*$info[1]>16000000 || $info[0]<1 || $info[1]<1) throw new InvalidArgumentException('Use a valid JPG, PNG or WebP image up to 16 megapixels. SVG and animated files are not accepted.');
    $mem=ini_get('memory_limit'); $limit=(int)$mem; if(str_ends_with(strtolower($mem),'g')) $limit*=1024*1024*1024; elseif(str_ends_with(strtolower($mem),'m')) $limit*=1024*1024; elseif(str_ends_with(strtolower($mem),'k')) $limit*=1024;
    if($limit>0 && memory_get_usage(true)+$info[0]*$info[1]*8+32*1024*1024>$limit) throw new InvalidArgumentException('This image is too large for the server memory limit. Resize it before uploading.');
    $im=@imagecreatefromstring(file_get_contents($file['tmp_name'])); if(!$im) throw new InvalidArgumentException('The image could not be decoded.');
    $ratio=min(1,2400/max($info[0],$info[1])); $w=(int)round($info[0]*$ratio);$h=(int)round($info[1]*$ratio);$out=imagecreatetruecolor($w,$h);
    imagealphablending($out,false); imagesavealpha($out,true); imagecopyresampled($out,$im,0,0,0,0,$w,$h,$info[0],$info[1]);
    ob_start(); imagepng($out,null,6); $bytes=ob_get_clean(); imagedestroy($im);imagedestroy($out);
    $id=bin2hex(random_bytes(16)); $fileName=$id.'.php'; $dest=rtrim(config()['storage'],'/').'/media/'.$fileName;
    with_staged_upload($bytes,static function(string $stage) use($dest): void {
        $guarded=file_get_contents($stage);if($guarded===false || !str_starts_with($guarded,CWP_GUARD))throw new RuntimeException('Upload staging could not be verified.');
        write_private_file($dest,$guarded);
    });
    try { query('INSERT INTO '.table('media').' (id,filename,mime,bytes,width,height,original_name,created_at) VALUES (?,?,?,?,?,?,?,?)',[$id,$fileName,'image/png',strlen($bytes),$w,$h,substr(basename($file['name']),0,180),gmdate('Y-m-d H:i:s')]); } catch(Throwable $e) { @unlink($dest); throw $e; }
    log_event('media.upload',$id); return $id;
}
/**
 * Save a NEW private file. Permissions are applied before writing sensitive bytes.
 * Never overwrites a file or follows a caller-supplied symlink. All callers use
 * fixed application paths or random filenames inside the private media folder.
 */
