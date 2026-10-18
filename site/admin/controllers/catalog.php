<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/catalog_import.php';
require_once CWP_CODE_ROOT.'/admin/views/catalog_import.php';
function catalog_selection(): array {
    $value=posted('product',40);if(!preg_match('/^([1-9][0-9]{0,8}):([1-9][0-9]{0,8})$/D',$value,$m))throw new InvalidArgumentException('Choose a current published service.');return [(int)$m[1],(int)$m[2]];
}
function catalog_admin_action(string $action,array $user): never {
    check_csrf();
    if(str_starts_with($action,'catalog_import_')){
        provider_catalog_import_owner();
        if($action==='catalog_import_prepare'){
            if(posted('confirm',1)!=='1')throw new InvalidArgumentException('Confirm that you have a current backup.');
            provider_catalog_import_prepare(posted('owner_password',1000));flash('Observed hosting type imports enabled. Existing catalog products are unchanged.');redirect(admin_url('catalog'));
        }
        if($action==='catalog_import_catalog_prepare'){
            provider_package_types_prepare(posted('owner_password',1000));flash('20i hosting catalog discovery enabled. Refresh the catalog to load its hosting types.');redirect(admin_url('catalog',['import_source'=>'package.types']));
        }
        if($action==='catalog_import_catalog_refresh'){
            $revision=posted('profile_revision',10);
            if(!preg_match('/^[1-9][0-9]{0,9}$/D',$revision)||(int)$revision>2147483647)throw new InvalidArgumentException('Reload the current hosting catalog form.');
            $state=provider_inventory_refresh(posted('profile_id',32),(int)$revision,posted('owner_password',1000),null,null,\CajunWebPro\Foundation\TwentyIInventory\InventoryRead::PACKAGE_TYPES);
            flash($state==='complete'?'20i hosting types saved. Review them below before creating local drafts.':'The hosting catalog refresh could not be completed. Existing products are unchanged; refresh again before importing from this catalog.',$state==='complete'?'success':'error');
            redirect(admin_url('catalog',['import_source'=>'package.types']));
        }
        if($action==='catalog_import_batch'){
            if(posted('confirm',1)!=='1')throw new InvalidArgumentException('Confirm importing these hosting types as local drafts.');
            $source=posted('import_source',20)?:'package.types';$term=posted('term',10);$prefix=posted('sku_prefix',40);
            $result=provider_catalog_import_batch(posted('preview_hash',64),$term,$prefix,posted('owner_password',1000),$source,gint($_POST,'import_offset',0,8192,0));
            flash($result['created_count'].' draft(s) created. '.$result['existing_count'].' existing product(s) preserved. '.$result['remaining_count'].' type(s) remain to import.');
            $query=['import_source'=>$source,'batch_term'=>$term,'batch_prefix'=>$prefix];
            if($result['next_offset']!==null)$query['import_offset']=$result['next_offset'];
            redirect(admin_url('catalog',$query));
        }
        if($action==='catalog_import_create'){
            if(posted('confirm',1)!=='1')throw new InvalidArgumentException('Confirm creation of this local draft.');
            $id=provider_catalog_import_create(posted('preview_hash',64),posted('type_ref',18),posted('sku',60),posted('term',10),posted('owner_password',1000),posted('import_source',20)?:'packages.list');
            flash('Observed hosting type saved as a local draft, or its existing draft retained. Review its price, terms and image. No product was published or ordered.');redirect(admin_url('catalog',['id'=>$id]));
        }
        if($action==='catalog_import_image'){
            $id=gint($_POST,'id',1,9999999);provider_catalog_import_image($id,gint($_POST,'revision',1,9999999),gint($_POST,'image_revision',0,9999999),posted('remove_image',1)==='1',posted('owner_password',1000));flash('Draft product image saved. Publication has not changed.');redirect(admin_url('catalog',['id'=>$id]));
        }
        throw new InvalidArgumentException('Unknown catalog import action.');
    }
    if($action==='catalog_migrate'){
        if(posted('confirm',1)!=='1')throw new InvalidArgumentException('Confirm that you have a current backup.');catalog_migrate(posted('owner_password',1000));flash('Service catalog enabled. Add your approved products and prices.');redirect(admin_url('catalog'));
    }
    catalog_require(in_array($action,['catalog_preview','catalog_issue','catalog_void'],true));
    if($action==='catalog_save'){
        $id=catalog_save(gint($_POST,'id',0,9999999),gint($_POST,'revision',0,9999999),$_POST);flash('Product saved with a new revision. Existing invoices keep their original price.');redirect(admin_url('catalog',['id'=>$id]));
    }
    if($action==='catalog_void'){
        if(posted('confirm',1)!=='1')throw new InvalidArgumentException('Confirm cancellation of this unpaid invoice.');$job=gint($_POST,'job_id',1,9999999);catalog_transition($job,gint($_POST,'invoice_id',1,9999999),'void',posted('reason',500));flash('Invoice voided. Its price record remains available.');redirect(admin_url('studio-job',['id'=>$job]));
    }
    if(in_array($action,['catalog_preview','catalog_issue'],true)){
        [$product,$revision]=catalog_selection();$job=gint($_POST,'job_id',1,9999999);$qty=gint($_POST,'quantity',1,100);$instructions=posted('instructions',6000);
        if($action==='catalog_preview'){
            studio_job($job);$GLOBALS['catalog_preview']=catalog_quote($product,$revision,$qty);catalog_admin_render('catalog-invoice',$user,'');
        }
        if(posted('confirm',1)!=='1')throw new InvalidArgumentException('Review and confirm the exact invoice amount and service period.');
        catalog_issue($job,$product,$revision,$qty,$instructions,posted('request_key',64));flash('Invoice issued. No payment was collected and no subscription was created.');redirect(admin_url('studio-job',['id'=>$job]));
    }
    throw new InvalidArgumentException('Unknown catalog action.');
}
function catalog_admin_render(string $view,array $user,string $error): never {
    if($view==='catalog-invoice'&&!staff_can('clients.manage',$user)){http_response_code(403);admin_head('Access unavailable','catalog',$user);echo '<p>Both catalog and client-workspace permissions are required to issue invoices.</p>';admin_foot();exit;}
    admin_head($view==='catalog-invoice'?'Review service invoice':'Service catalog','catalog',$user);
    if($error)echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    if(!catalog_ready()){
        if(!staff_ready()||!studio_ready())echo '<p>Enable administrator accounts and the client workspace first.</p>';
        elseif(staff_can('staff.manage',$user)){?><section class="workspace-card"><h2>Enable the service catalog</h2><p>Add approved service prices and issue invoices using your existing clients and projects. This creates three catalog tables; existing invoices and estimator settings stay in place.</p><form method="post"><?php admin_action('catalog_migrate');admin_input('owner_password','Your current owner password','','password','required autocomplete="current-password"');admin_check('confirm','I have a current database and file backup.',false);?><button class="btn gold">Enable service catalog</button></form></section><?php }
        else echo '<p>Ask an owner to enable the service catalog.</p>';
        admin_foot();exit;
    }
    if($view==='catalog-invoice'){
        $jobId=(int)($_POST['job_id']??$_GET['job_id']??0);try{$job=studio_job($jobId);}catch(InvalidArgumentException){http_response_code(404);echo '<p>Project not found.</p>';admin_foot();exit;}
        echo '<p>Project: <strong>'.esc($job['title']).'</strong>. This issues one manual invoice for the selected service period. No card charge, recurring enrollment or automatic renewal occurs. Tax and discounts are not calculated in this version; confirm that the configured price is appropriate before issuing.</p>';
        $products=[];foreach(catalog_public() as $p)$products[$p['id'].':'.$p['revision']]=$p['sku'].' · '.$p['title'].' · $'.gmoney((int)$p['amount_cents']).' + $'.gmoney((int)$p['setup_cents']).' setup · '.catalog_terms()[$p['term']];
        $input=[];foreach(['product','quantity','instructions'] as $key)$input[$key]=is_string($_POST[$key]??null)?$_POST[$key]:'';
        if(!$products)echo '<p>Publish a configured service in the catalog first.</p>';
        else{?><section class="workspace-card"><h2>Select service</h2><form method="post"><?php admin_action('catalog_preview');studio_hidden('job_id',$jobId);admin_select('product','Current published product and price',$products,$input['product']);admin_input('quantity','Units (setup fee applies once per invoice)',$input['quantity']?:'1','number','min="1" max="100" required');admin_textarea('instructions','Manual payment instructions',$input['instructions']);?><button class="btn outline">Review invoice</button></form></section><?php }
        if($p=$GLOBALS['catalog_preview']??null){?><section class="workspace-card catalog-review"><h2>Confirm exact invoice</h2><?php catalog_snapshot_view($p);?><p><?=nl2br(esc($input['instructions']))?></p><form method="post"><?php admin_action('catalog_issue');studio_hidden('job_id',$jobId);studio_hidden('product',$p['id'].':'.$p['revision']);studio_hidden('quantity',$p['quantity']);studio_hidden('instructions',$input['instructions']);studio_hidden('request_key',bin2hex(random_bytes(32)));admin_check('confirm','I reviewed the price, service period and client agreement. Issue this manual invoice.',false);?><button class="btn gold">Issue reviewed invoice</button></form></section><?php }
        echo '<p><a href="'.esc(admin_url('studio-job',['id'=>$jobId])).'">Return to project</a></p>';admin_foot();exit;
    }
    ?><p class="admin-intro">Manage approved service prices here. Published design, care and additional services appear on the Packages page. Provider-dependent products stay draft until their integration is verified. Service periods describe one invoice; automatic billing is not enabled.</p><p><a class="btn outline" href="<?=esc(admin_url('catalog'))?>">Add a product</a> <a class="btn outline" href="<?=esc(page_url('packages'))?>">View published services</a></p><?php
    provider_catalog_import_admin_view($user);
    $id=is_string($_GET['id']??null)?(int)$_GET['id']:0;$p=$id?one('SELECT * FROM '.table('catalog_products').' WHERE id=?',[$id]):null;
    if($id&&!$p){http_response_code(404);echo '<p>Product not found.</p>';admin_foot();exit;}
    $values=($p??[])+['sku'=>'','title'=>'','family'=>'web-design','status'=>'draft','term'=>'once','currency'=>'USD','amount'=>'','setup'=>'0.00','cost'=>'','provider_ref'=>'','summary'=>'','features'=>'','terms'=>'','sort_order'=>'0','revision'=>0];
    if($p){$values['amount']=gmoney($p['amount_cents']===null?null:(int)$p['amount_cents']);$values['setup']=gmoney((int)$p['setup_cents']);$values['cost']=gmoney($p['cost_cents']===null?null:(int)$p['cost_cents']);}
    if($error&&($_POST['action']??'')==='catalog_save')foreach(array_keys($values) as $key)if(is_string($_POST[$key]??null))$values[$key]=$_POST[$key];
    ?><section class="workspace-card"><h2><?=$id?'Edit product':'New product'?></h2><form method="post"><?php admin_action('catalog_save');studio_hidden('id',$id);studio_hidden('revision',$values['revision']);?><div class="workspace-grid"><div><?php admin_input('sku','Unique SKU',$values['sku'],'text','required maxlength="60"');admin_input('title','Service name',$values['title'],'text','required maxlength="120"');admin_select('family','Product family',catalog_families(),$values['family']);admin_select('status','Visibility',['draft'=>'Draft','published'=>'Published','retired'=>'Retired'],$values['status']);admin_select('term','Service period',catalog_terms(),$values['term']);admin_input('currency','Currency','USD','text','readonly');admin_input('amount','Unit price in USD',$values['amount'],'text','inputmode="decimal" placeholder="Not configured"');admin_input('setup','One-time setup fee in USD',$values['setup'],'text','inputmode="decimal"');?></div><div><?php admin_textarea('summary','Public summary',$values['summary'],3,1000);admin_textarea('features','Public features (one per line)',$values['features'],4,4000);admin_textarea('terms','Public service terms / included period',$values['terms'],4,4000);admin_input('sort_order','Display order',$values['sort_order'],'number','min="0" max="9999"');?><details><summary>Internal product information</summary><p>Only administrators with catalog access see these values. Enter identifiers, never provider credentials.</p><?php admin_input('cost','Internal cost in USD (optional)',$values['cost'],'text','inputmode="decimal"');admin_input('provider_ref','Internal provider product reference (optional)',$values['provider_ref'],'text','maxlength="160"');?></details></div></div><button class="btn gold">Save product</button></form></section>
    <section class="workspace-card"><h2>Products</h2><div class="table-wrap"><table><thead><tr><th>SKU / service</th><th>Status / period</th><th>Unit / setup USD</th><th>Revision</th></tr></thead><tbody><?php foreach(all('SELECT * FROM '.table('catalog_products').' ORDER BY sort_order,id LIMIT 200') as $item){?><tr><td><a href="<?=esc(admin_url('catalog',['id'=>$item['id']]))?>"><?=esc($item['sku'].' · '.$item['title'])?></a></td><td><?=esc($item['status'].' · '.(catalog_terms()[$item['term']]??$item['term']))?></td><td><?=esc($item['amount_cents']===null?'Not configured':gmoney((int)$item['amount_cents']))?> / <?=esc(gmoney((int)$item['setup_cents']))?></td><td><?=(int)$item['revision']?></td></tr><?php }?></tbody></table></div></section><?php
    if($id){?><section class="workspace-card"><h2>Price and publication history</h2><p>Last 20 saved revisions. Issued invoices keep the price and service text they were issued with.</p><?php foreach(all('SELECT r.*,u.name FROM '.table('catalog_revisions').' r LEFT JOIN '.table('users').' u ON u.id=r.actor_id WHERE r.product_id=? ORDER BY r.revision DESC LIMIT 20',[$id]) as $r){$v=json_decode($r['snapshot'],true,32,JSON_THROW_ON_ERROR);echo '<p>Revision '.(int)$r['revision'].' · '.esc($r['created_at'].' UTC · '.$r['name'].' · '.$v['status'].' · '.($v['amount_cents']===null?'No price':'$'.gmoney((int)$v['amount_cents'])).' + $'.gmoney((int)$v['setup_cents']).' setup').'</p>';}?></section><?php }
    if($p)provider_catalog_import_image_view($p,$user);
    admin_foot();exit;
}
