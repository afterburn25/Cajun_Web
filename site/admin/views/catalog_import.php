<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

function provider_catalog_import_admin_view(array $user): void {
    if(!staff_can('staff.manage',$user)||support_current()||array_key_exists('support',$_GET))return;
    ?><section class="workspace-card"><h2>Import hosting products</h2><p>Create drafts from hosting types in your latest 20i inventory. After importing, add your price, description and image. Prices are not imported, and hosting products cannot be published or ordered yet. Other product types are not available here.</p><?php
    try {
        provider_catalog_import_owner();
        if(!provider_catalog_import_ready()){
            ?><form method="post"><?php admin_action('catalog_import_prepare');admin_input('owner_password','Your current owner password','','password','required autocomplete="current-password"');admin_check('confirm','I have a current database and file backup.',false);?><button class="btn outline">Enable imports</button></form><?php
        }else{
            $page=gint($_GET,'import_page',1,410,1);$preview=provider_catalog_import_preview($page);
            echo '<p>Inventory checked: '.esc($preview['observed_at']).' UTC. Review the inventory in <a href="'.esc(admin_url('provider-settings')).'">20i settings</a> to refresh it. Creating a draft does not place an order.</p>';
            if($preview['unnamed'])echo '<p>'.(int)$preview['unnamed'].' package record(s) have no type name and cannot be imported.</p>';
            if(!$preview['types'])echo '<p>No named hosting types were found on this page.</p>';
            foreach($preview['types'] as $type){
                echo '<div class="catalog-review"><h3>'.esc($type['name']).'</h3><p>20i type '.esc($type['type_ref']).'</p>';
                if($type['product_id'])echo '<p>'.($type['earlier_credentials']?'Your 20i connection details changed after this draft was imported. The existing draft keeps its original source and cannot be imported again here.':'Already imported. Your price, wording and image will be preserved.').' <a href="'.esc(admin_url('catalog',['id'=>$type['product_id']])).'">Review draft product</a></p>';
                else{
                    $retry=($_POST['action']??'')==='catalog_import_create'&&($_POST['type_ref']??null)===$type['type_ref'];
                    $field=static fn(string $key,string $fallback):string=>$retry?(is_string($_POST[$key]??null)?$_POST[$key]:''):$fallback;
                    ?><form method="post"><?php admin_action('catalog_import_create');studio_hidden('type_ref',$type['type_ref']);studio_hidden('preview_hash',$field('preview_hash',$preview['hash']));admin_input('sku','Local unique SKU',$field('sku','20I-'.$type['type_ref']),'text','required maxlength="60"');admin_select('term','Local service period to review',[''=>'Choose a period']+catalog_terms(),$field('term',''));?><p>The period is your local choice, not a provider billing term. Price, features and service terms start blank. Publication remains unavailable for hosting products.</p><?php admin_input('owner_password','Your current owner password','','password','required autocomplete="current-password"');admin_check('confirm','Create this local draft for review. No provider order or payment will occur.',$retry&&($_POST['confirm']??'')==='1');?><button class="btn outline">Create draft</button></form><?php
                }
                echo '</div>';
            }
            echo '<nav aria-label="Observed hosting type pages">';
            if($page>1)echo '<a href="'.esc(admin_url('catalog',['import_page'=>$page-1])).'">Previous types</a> ';
            if($preview['has_more'])echo '<a href="'.esc(admin_url('catalog',['import_page'=>$page+1])).'">More types</a>';
            echo '</nav>';
        }
    }catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;echo '<div class="notice warning">'.esc($e->getMessage()).'</div>';}
    ?><p>After importing, upload an image you own. Images are not copied from 20i.</p></section><?php
}

function provider_catalog_import_image_view(array $product,array $user): void {
    if(!staff_can('staff.manage',$user)||support_current()||array_key_exists('support',$_GET))return;
    try {
        provider_catalog_import_owner();$record=provider_catalog_import_record((int)$product['id']);if(!$record)return;
        $retry=($_POST['action']??'')==='catalog_import_image'&&($_POST['id']??null)===(string)$product['id'];
        $field=static fn(string $key,string $fallback):string=>$retry?(is_string($_POST[$key]??null)?$_POST[$key]:''):$fallback;
        ?><section class="workspace-card"><h2>Imported draft image</h2><p>Imported from 20i type <?=esc($record['type_ref'])?> (<?=esc($record['type_name'])?>) on <?=esc($record['created_at'])?> UTC. This product remains a hosting draft until ordering and publication are available. You can edit or retire it, but cannot change its product type.</p><?php
        if($record['media_id']){if(!one('SELECT id FROM '.table('media').' WHERE id=?',[$record['media_id']]))echo '<p class="notice warning">The product image is missing. Restore it or explicitly upload a replacement.</p>';else echo '<img class="editor-preview" src="'.esc(site_url('media/index.php?id='.$record['media_id'])).'" alt="Uploaded draft product image">';}
        ?><form method="post" enctype="multipart/form-data"><?php admin_action('catalog_import_image');studio_hidden('id',(int)$product['id']);studio_hidden('revision',$field('revision',(string)$product['revision']));studio_hidden('image_revision',$field('image_revision',(string)$record['media_revision']));?><label>Choose an image<input type="file" name="product_image" accept="image/jpeg,image/png,image/webp"></label><p>JPG, PNG or WebP, up to 6 MB / 16 megapixels. Images are accessible via their URL even while the product is a draft. Use artwork you may publish, never private customer records. After a failed submission, select the file again.</p><?php admin_check('remove_image','Remove the current image',$retry&&($_POST['remove_image']??'')==='1');admin_input('owner_password','Your current owner password','','password','required autocomplete="current-password"');?><button class="btn outline">Save image</button></form></section><?php
    }catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;echo '<div class="notice warning">'.esc($e->getMessage()).'</div>';}
}
