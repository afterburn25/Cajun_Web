<?php
/** Additive storefront taxonomy setup. Performs no provider, payment, message or deployment action. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/hosting/storefront_categories.php';
require_once CWP_CODE_ROOT.'/app/providers/catalog_import_storage.php';
require_once CWP_CODE_ROOT.'/app/providers/catalog_variants_storage.php';

function hosting_storefront_prepare(#[\SensitiveParameter] string $password): void {
    if (db()->inTransaction()) throw new LogicException('Hosting storefront setup cannot inherit a transaction.');
    staff_locked(function()use($password):void {
        $actor=service_plans_password($password);
        if (!catalog_ready() || !hosting_catalog_ready() || !provider_catalog_import_ready()) throw new RuntimeException('Prepare catalog, hosting offers, and retained 20i imports before storefront categories.');
        if (hosting_storefront_ready()) return;
        foreach (hosting_storefront_schema(config()['db']['prefix']) as $name=>$sql) {
            if (!hosting_storefront_exists($name)) db()->exec($sql);
            hosting_storefront_validate_schema($name,$sql);
        }
        staff_transaction(function()use($password,$actor):void {
            hosting_storefront_guard();$current=service_plans_password($password);
            if ((int)$current['id']!==(int)$actor['id'] || !hash_equals($actor['auth_version'],$current['auth_version'])) throw new RuntimeException('Hosting storefront setup authority changed.');
            if (hosting_storefront_ready()) return;
            foreach (hosting_storefront_tables() as $name) {
                if (hosting_catalog_integer(query('SELECT COUNT(*) FROM '.table($name))->fetchColumn(),999999999)!==0) hosting_storefront_recovery();
                save_setting($name.'_count','0');
            }
            $at=time();
            $shared=hosting_storefront_insert_category(['format'=>1,'id'=>bin2hex(random_bytes(16)),'slug'=>'shared-hosting','revision'=>1,'label'=>'Shared Hosting','summary'=>'Managed shared website hosting plans.','required_family'=>'hosting','purchase_mode'=>'online','created_by'=>(int)$actor['id'],'created_at'=>$at]);
            hosting_storefront_insert_category(['format'=>1,'id'=>bin2hex(random_bytes(16)),'slug'=>'vps-hosting','revision'=>1,'label'=>'VPS Hosting','summary'=>'Virtual private server hosting. Online purchase remains unavailable until its retail and provider contract is qualified.','required_family'=>'vps','purchase_mode'=>'unavailable','created_by'=>(int)$actor['id'],'created_at'=>$at]);
            $products=all("SELECT p.id,p.revision,p.family,i.id import_id FROM ".table('catalog_products')." p LEFT JOIN ".table('catalog_provider_imports')." i ON i.product_id=p.id WHERE p.family IN ('hosting','vps') ORDER BY p.id");
            $variantsReady=provider_catalog_variant_ready();
            foreach ($products as $product) {
                $variant=$variantsReady&&$product['import_id']===null?one('SELECT id FROM '.table('catalog_provider_variants').' WHERE product_id=?',[(int)$product['id']]):null;
                $bound=$product['family']==='hosting' && ($product['import_id']!==null || $variant!==null);
                hosting_storefront_insert_classification(['format'=>1,'id'=>bin2hex(random_bytes(16)),'product_id'=>(int)$product['id'],'product_revision'=>(int)$product['revision'],'state'=>$bound?'bound':'review','category_id'=>$bound?$shared['id']:null,'category_slug'=>$bound?'shared-hosting':null,'category_revision'=>$bound?1:null,'category_sha256'=>$bound?$shared['sha256']:null,'source'=>$bound?'twentyi-addweb-migration':'migration-review','created_by'=>(int)$actor['id'],'created_at'=>$at]);
            }
            foreach (all('SELECT id,product_id,created_by,document_json,document_sha256 FROM '.table('hosting_catalog_publications').' ORDER BY seq') as $saved) {
                $publication=HostingCatalogPublication::fromJson($saved['document_json']);$classification=hosting_storefront_current_classification((int)$saved['product_id']);
                if (!$classification || $classification['state']!=='bound' || $classification['category_slug']!=='shared-hosting') throw new RuntimeException('An existing hosting publication needs explicit Shared classification before storefront migration.');
                hosting_storefront_insert_publication(['format'=>1,'id'=>$saved['id'],'publication_sha256'=>$saved['document_sha256'],'product_id'=>(int)$saved['product_id'],'category_id'=>$shared['id'],'category_slug'=>'shared-hosting','category_revision'=>1,'category_sha256'=>$shared['sha256'],'classification_id'=>$classification['id'],'classification_sha256'=>$classification['sha256'],'source_format'=>$publication->data()['format'],'created_by'=>(int)$saved['created_by'],'created_at'=>$at]);
            }
            save_setting('hosting_storefront_version','1');
            query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[(int)$actor['id'],'hosting_storefront.prepared','Hosting storefront storage version 1',gmdate('Y-m-d H:i:s',$at)]);
            if (!hosting_storefront_ready()) hosting_storefront_recovery();
        });
    });
}

