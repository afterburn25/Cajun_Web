<?php
/** Pure storefront category and product-classification contracts. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

function hosting_storefront_slug(mixed $value): string {
    if (!is_string($value) || !preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\z/',$value)) throw new InvalidArgumentException('Invalid storefront category slug.');
    return $value;
}
function hosting_storefront_hash(mixed $value): string {
    if (!is_string($value) || !preg_match('/\A[a-f0-9]{64}\z/',$value)) throw new InvalidArgumentException('Invalid storefront category fingerprint.');
    return $value;
}
function hosting_storefront_family(mixed $value): string {
    if (!is_string($value) || !in_array($value,['hosting','vps'],true)) throw new InvalidArgumentException('Choose a supported storefront fulfillment family.');
    return $value;
}
function hosting_storefront_category_document(array $d): string {
    service_plans_keys($d,['format','id','slug','revision','label','summary','required_family','purchase_mode','created_by','created_at']);
    if (($d['format']??null)!==1) throw new InvalidArgumentException('Invalid storefront category format.');
    service_plans_id($d['id']);hosting_storefront_slug($d['slug']);service_plans_actor($d['revision']);
    hosting_catalog_text($d['label'],80);hosting_catalog_text($d['summary'],500,true,true);
    hosting_storefront_family($d['required_family']);
    if (!in_array($d['purchase_mode'],['online','unavailable'],true)) throw new InvalidArgumentException('Choose a supported storefront purchase mode.');
    // No supported VPS retail/provider contract exists yet. The category remains a truthful destination only.
    if ($d['required_family']==='vps' && $d['purchase_mode']!=='unavailable') throw new InvalidArgumentException('VPS checkout remains unavailable until a separately evidenced retail and provider contract exists.');
    service_plans_actor($d['created_by']);service_plans_time($d['created_at']);
    return service_plans_json($d);
}
function hosting_storefront_classification_document(array $d): string {
    service_plans_keys($d,['format','id','product_id','product_revision','state','category_id','category_slug','category_revision','category_sha256','source','created_by','created_at']);
    if (($d['format']??null)!==1 || !in_array($d['state'],['bound','review'],true)) throw new InvalidArgumentException('Invalid storefront product classification.');
    service_plans_id($d['id']);service_plans_actor($d['product_id']);service_plans_actor($d['product_revision']);
    if (!in_array($d['source'],['owner','twentyi-addweb-migration','twentyi-addweb-import','migration-review'],true)) throw new InvalidArgumentException('Invalid storefront classification source.');
    if ($d['state']==='bound') {
        service_plans_id($d['category_id']);hosting_storefront_slug($d['category_slug']);service_plans_actor($d['category_revision']);hosting_storefront_hash($d['category_sha256']);
    } elseif ($d['category_id']!==null || $d['category_slug']!==null || $d['category_revision']!==null || $d['category_sha256']!==null) {
        throw new InvalidArgumentException('A review classification cannot claim a storefront category.');
    }
    service_plans_actor($d['created_by']);service_plans_time($d['created_at']);
    return service_plans_json($d);
}
function hosting_storefront_publication_document(array $d): string {
    service_plans_keys($d,['format','id','publication_sha256','product_id','category_id','category_slug','category_revision','category_sha256','classification_id','classification_sha256','source_format','created_by','created_at']);
    if (($d['format']??null)!==1 || !in_array($d['source_format']??null,[1,2,3],true)) throw new InvalidArgumentException('Invalid storefront publication binding.');
    service_plans_id($d['id']);hosting_storefront_hash($d['publication_sha256']);service_plans_actor($d['product_id']);
    service_plans_id($d['category_id']);hosting_storefront_slug($d['category_slug']);service_plans_actor($d['category_revision']);hosting_storefront_hash($d['category_sha256']);
    service_plans_id($d['classification_id']);hosting_storefront_hash($d['classification_sha256']);service_plans_actor($d['created_by']);service_plans_time($d['created_at']);
    return service_plans_json($d);
}
function hosting_storefront_category_data(string $bytes): array {
    if (strlen($bytes)>4096) throw new InvalidArgumentException('Storefront category exceeds its bound.');
    $d=json_decode($bytes,true,12,JSON_THROW_ON_ERROR);
    if (!is_array($d) || hosting_storefront_category_document($d)!==$bytes) throw new InvalidArgumentException('Noncanonical storefront category.');
    return $d;
}
function hosting_storefront_classification_data(string $bytes): array {
    if (strlen($bytes)>4096) throw new InvalidArgumentException('Storefront classification exceeds its bound.');
    $d=json_decode($bytes,true,12,JSON_THROW_ON_ERROR);
    if (!is_array($d) || hosting_storefront_classification_document($d)!==$bytes) throw new InvalidArgumentException('Noncanonical storefront classification.');
    return $d;
}
function hosting_storefront_publication_data(string $bytes): array {
    if (strlen($bytes)>4096) throw new InvalidArgumentException('Storefront publication binding exceeds its bound.');
    $d=json_decode($bytes,true,12,JSON_THROW_ON_ERROR);
    if (!is_array($d) || hosting_storefront_publication_document($d)!==$bytes) throw new InvalidArgumentException('Noncanonical storefront publication binding.');
    return $d;
}
function hosting_storefront_category_projection(array $category): array {
    $bytes=hosting_storefront_category_document($category);
    return ['slug'=>$category['slug'],'label'=>$category['label'],'summary'=>$category['summary'],'required_family'=>$category['required_family'],'purchase_mode'=>$category['purchase_mode'],'revision'=>$category['revision'],'sha256'=>hash('sha256',$bytes)];
}

