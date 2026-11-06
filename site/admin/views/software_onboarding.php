<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}

function software_onboarding_product_choices(int $boundProductId):array{
    if($boundProductId<0||$boundProductId>9999999)throw new RuntimeException('Selected software product identity is invalid.');
    if($boundProductId>0){
        $product=one("SELECT id,sku,title FROM ".table('catalog_products')." WHERE id=? AND family='software'",[$boundProductId]);
        return [(string)$boundProductId=>$product?$product['title'].' · '.$product['sku']:'Selected software product is no longer available'];
    }
    $products=['0'=>'Create a new draft product'];
    foreach(all("SELECT id,sku,title FROM ".table('catalog_products')." WHERE family='software' ORDER BY title,id LIMIT 500") as $product)$products[(string)$product['id']]=$product['title'].' · '.$product['sku'];
    return $products;
}

function software_onboarding_admin_view(array $user,string $error):void{
    $canManage=staff_can('staff.manage',$user);
    ?><section class="workspace-card" id="software-onboarding"><h2>Add software from a ZIP</h2>
    <p>Upload package <span aria-hidden="true">→</span> Review product details <span aria-hidden="true">→</span> Review license settings <span aria-hidden="true">→</span> Save product</p><?php
    if($error!==''&&str_starts_with((string)($_POST['action']??''),'catalog_software_'))echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    if(support_current()){echo '<p>Leave client support before managing private software packages.</p></section>';return;}
    if(!$canManage){echo '<p>Owner access is required for private software packages and license settings.</p></section>';return;}
    if(!software_commerce_ready()){echo '<p>Enable software commerce from Licenses &amp; software before using guided uploads.</p></section>';return;}
    if(!software_onboarding_ready()){?>
        <p>This adds one private staging table. Existing products, packages, licenses, prices, and publication settings are unchanged.</p>
        <form method="post"><?php admin_action('catalog_software_prepare');admin_check('confirm','I have a current database and private-file backup.',false);?><button class="btn gold">Enable guided software uploads</button></form>
        </section><?php return;
    }
    $done=is_string($_GET['onboarding_done']??null)?$_GET['onboarding_done']:'';
    if($done!==''&&preg_match('/^[1-9][0-9]{0,9}$/D',$done)){?>
        <div class="notice success"><strong>Draft software product saved.</strong> Its ZIP is stored securely and remains owner-private.</div>
        <p class="button-row"><a class="btn outline" href="<?=esc(admin_url('studio-licenses'))?>#license-create">Issue a license</a><a class="btn outline" href="<?=esc(admin_url('catalog',['software_product'=>$done]))?>#software-onboarding">Add another version</a><a class="btn outline" href="<?=esc(admin_url('catalog',['id'=>$done]))?>#product-editor">Review price and publication</a></p><?php
    }
    $key=is_string($_GET['software_upload']??null)?$_GET['software_upload']:'';
    if($key===''&&($_POST['action']??'')==='catalog_software_save'&&is_string($_POST['request_key']??null))$key=$_POST['request_key'];
    $request=null;$requestError='';
    if($key!=='')try{$request=software_onboarding_request($key);}catch(InvalidArgumentException|RuntimeException $e){$requestError=$e->getMessage();}
    if($requestError!=='')echo '<div class="notice error" role="alert">'.esc($requestError).'</div>';
    if(!$request||$request['state']!=='review'){?>
        <h3>1. Upload package</h3><p>Choose one ZIP up to 300 MB. CajunWebPro validates paths, expansion limits, special files, and nested archives before saving anything.</p>
        <form method="post" enctype="multipart/form-data" data-software-package-upload><?php admin_action('catalog_software_upload');studio_hidden('software_product',is_string($_GET['software_product']??null)?(int)$_GET['software_product']:0);?><input type="hidden" name="MAX_FILE_SIZE" value="314572800"><label>Software ZIP<input type="file" name="package" accept=".zip,application/zip" required></label><progress value="0" max="100" hidden data-upload-progress></progress><p class="hint" aria-live="polite" data-upload-status>Nothing has been uploaded.</p><button class="btn gold">Upload and review</button></form>
        </section><?php return;
    }
    $metadata=$request['metadata'];$boundProductId=(int)($metadata['target_product_id']??0);$selected=$boundProductId>0?$boundProductId:(is_string($_POST['product_id']??null)?(int)$_POST['product_id']:0);
    $products=software_onboarding_product_choices($boundProductId);
    if($selected===0&&($metadata['embedded_sku']??'')!==''){$matched=one('SELECT id FROM '.table('catalog_products').' WHERE family=? AND sku=?',['software',$metadata['embedded_sku']]);if($matched)$selected=(int)$matched['id'];}
    $title=is_string($_POST['title']??null)?$_POST['title']:(string)($metadata['title']??'');
    $version=is_string($_POST['version']??null)?$_POST['version']:(string)($metadata['version']??'');
    ?><div class="notice success"><strong>Stored securely.</strong> <?=esc((string)$request['original_name'])?> · <?=esc(number_format((int)$request['byte_size']))?> bytes · checksum <?=esc(substr((string)$request['sha256'],0,16))?>…</div>
    <p class="hint"><?=esc((string)($metadata['source']??'No supported metadata detected.'))?> No package code was executed.</p>
    <form method="post"><?php admin_action('catalog_software_save');studio_hidden('request_key',(string)$request['request_key']);?>
    <h3>2. Review product details</h3><?php admin_select('product_id','Product',$products,(string)$selected);?><p class="hint"><?=$boundProductId>0?'This upload is bound to the software product selected by Add another version. Start a new upload to choose a different product.':'Choose an existing software product to add a version. Creating a product generates and retains its stable internal SKU.'?></p><?php admin_input('title','Software name',$title,'text','required maxlength="120"');admin_input('version','Package version',$version,'text','required maxlength="40" pattern="[0-9A-Za-z][0-9A-Za-z._-]{0,39}"');if(($metadata['embedded_sku']??'')!=='')echo '<p>Embedded product identity: <code>'.esc((string)$metadata['embedded_sku']).'</code>. It must match an existing selection.</p>'; ?>
    <h3>3. Review license settings</h3><dl><dt>Package access</dt><dd>Owner-private; no customer can download it until separately published.</dd><dt>Catalog visibility</dt><dd>Draft; no price, terms, or public description will be invented.</dd><dt>Installations</dt><dd>2 per issued license</dd><dt>Updates</dt><dd>No time limit while the license remains valid</dd><dt>Module key</dt><dd><?=esc((string)(($metadata['module_key']??'')!==''?$metadata['module_key']:'Not detected'))?></dd><dt>Compatibility</dt><dd><?=esc((string)($metadata['compatibility']??'Not detected in supported package metadata.'))?></dd></dl>
    <p class="hint">Compatibility above is package metadata only. Uploading does not prove runtime compatibility or add license enforcement to arbitrary code.</p>
    <h3>4. Save product</h3><p>This single operation saves the draft product (when needed), default license policy, and immutable private package record together.</p><button class="btn gold">Save draft product</button></form>
    <p><a href="<?=esc(admin_url('catalog'))?>#software-onboarding">Start over with another ZIP</a></p></section><?php
}
