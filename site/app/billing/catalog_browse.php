<?php
/** Read-only administrator product browsing. Fixed SQL fragments and bound filter values only. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
function catalog_admin_browse(array $query):array {
    $q=[];
    foreach(['product_search'=>120,'product_family'=>20,'product_status'=>20] as $name=>$max){
        $v=array_key_exists($name,$query)?$query[$name]:'';
        if(!is_string($v)||strlen($v)>$max||preg_match('//u',$v)!==1||preg_match('/[\x00-\x1f\x7f]/',$v))throw new InvalidArgumentException('Choose valid product filters.');
        $q[$name]=trim($v);
    }
    if($q['product_family']!==''&&!array_key_exists($q['product_family'],catalog_families()))throw new InvalidArgumentException('Choose a product family.');
    if(!in_array($q['product_status'],['','draft','published','retired'],true))throw new InvalidArgumentException('Choose a product status.');
    $p=array_key_exists('product_page',$query)?$query['product_page']:'1';
    if(!is_string($p)||!preg_match('/\A[1-9][0-9]{0,4}\z/',$p)||(int)$p>10000)throw new InvalidArgumentException('Choose an available product page.');
    $where=[];$params=[];
    if($q['product_search']!==''){
        $like='%'.str_replace(['!','%','_'],['!!','!%','!_'],$q['product_search']).'%';
        $where[]="(sku LIKE ? ESCAPE '!' OR title LIKE ? ESCAPE '!')";array_push($params,$like,$like);
    }
    foreach(['product_family'=>'family','product_status'=>'status'] as $field=>$column)if($q[$field]!==''){$where[]=$column.'=?';$params[]=$q[$field];}
    return ['query'=>$q,'page'=>(int)$p,'offset'=>((int)$p-1)*50,'where'=>$where?' WHERE '.implode(' AND ',$where):'','params'=>$params];
}
function catalog_admin_browse_url(array $browse,int $page):string {
    $q=$browse['query'];$q['product_page']=(string)$page;
    catalog_admin_browse($q); // Reject impossible pagination before producing a link.
    return admin_url('catalog',$q).'#catalog-products';
}

/** A shortcut may suggest a family for a new product, but never changes an existing product. */
function catalog_admin_new_family(array $query):string {
    return ($query['new_family']??null)==='software'?'software':'web-design';
}

/** Pure presentation state from already validated product/import/publication rows. */
function catalog_admin_product_readiness(array $product,?array $import,?array $offer,bool $mediaAvailable):array {
    $missing=[];
    if(($product['amount_cents']??null)===null)$missing[]='Price needed';
    if(trim((string)($product['summary']??''))===''||trim((string)($product['features']??''))===''||trim((string)($product['terms']??''))==='')$missing[]='Details needed';
    $imageId=$import['media_id']??null;$imageLabel=null;
    if($import!==null){
        if($imageId===null)$imageLabel='No provider image available';
        elseif(!$mediaAvailable)$imageLabel='Image missing';
        else $imageLabel='Image ready';
        if(!$mediaAvailable)$missing[]='Image missing';
    }
    $offerLabel=null;$offerAction=null;$offerNeedsReview=false;
    if($import!==null){
        if($offer===null){$offerLabel='Review for publication';$offerAction='Review for publication';}
        elseif((int)$offer['withdrawn']===1){$offerLabel='Offer withdrawn';$offerAction='Review and update offer';$offerNeedsReview=true;}
        elseif((int)$offer['product_revision']!==(int)$product['revision']||($offer['media_id']??null)!==$imageId){$offerLabel='Changes need review';$offerAction='Review and update offer';$offerNeedsReview=true;}
        else{$offerLabel='Offer published';$offerAction='Review hosting offer';}
    }
    return ['imported'=>$import!==null,'image_id'=>$mediaAvailable?$imageId:null,'image_label'=>$imageLabel,
        'missing'=>array_values(array_unique($missing)),'offer_label'=>$offerLabel,'offer_action'=>$offerAction,
        'offer_needs_review'=>$offerNeedsReview];
}

/**
 * Validate import and hosting history once under the shared staff mutex, then derive one bounded page.
 * This read-only transaction creates no setup marker, publication, provider operation or order.
 */
function catalog_admin_product_contexts(array $products):array {
    if(!array_is_list($products)||count($products)>50)throw new InvalidArgumentException('Choose one bounded product page.');
    if(!$products)return [];
    $ids=[];foreach($products as $product){$id=filter_var($product['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>2147483647]]);if($id===false||isset($ids[$id]))throw new InvalidArgumentException('Choose distinct saved products.');$ids[$id]=$id;}
    return staff_locked(fn()=>staff_transaction(function()use($products,$ids):array {
        $imports=[];
        if(provider_catalog_import_ready()){
            foreach(array_chunk(array_values($ids),20) as $chunk)foreach(provider_catalog_import_records_in_scope($chunk) as $record)$imports[(int)$record['product_id']]=$record;
        }
        $media=[];$mediaIds=array_values(array_unique(array_filter(array_column($imports,'media_id'),static fn($id)=>is_string($id)&&$id!=='')));
        if($mediaIds){
            $placeholders=implode(',',array_fill(0,count($mediaIds),'?'));
            foreach(all('SELECT id FROM '.table('media').' WHERE id IN ('.$placeholders.')',$mediaIds) as $row)$media[$row['id']]=true;
        }
        $offers=[];
        if(hosting_catalog_ready()){
            $placeholders=implode(',',array_fill(0,count($ids),'?'));
            $publications=table('hosting_catalog_publications');$withdrawals=table('hosting_catalog_withdrawals');
            $sql="SELECT p.id,p.product_id,p.product_revision,p.media_id,CASE WHEN w.id IS NULL THEN 0 ELSE 1 END AS withdrawn FROM $publications p LEFT JOIN $publications newer ON newer.product_id=p.product_id AND newer.seq>p.seq LEFT JOIN $withdrawals w ON w.publication_id=p.id WHERE newer.id IS NULL AND p.product_id IN ($placeholders)";
            foreach(all($sql,array_values($ids)) as $offer)$offers[(int)$offer['product_id']]=$offer;
        }
        $contexts=[];foreach($products as $product){$id=(int)$product['id'];$import=$imports[$id]??null;$mediaAvailable=$import!==null&&is_string($import['media_id']??null)&&isset($media[$import['media_id']]);$contexts[$id]=catalog_admin_product_readiness($product,$import,$offers[$id]??null,$mediaAvailable);}
        return $contexts;
    }));
}
