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
