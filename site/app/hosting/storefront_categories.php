<?php
/** Guarded append-only storefront taxonomy and classification history. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/catalog_offers_storage.php';
require_once __DIR__.'/storefront_categories_document.php';
require_once CWP_CODE_ROOT.'/database/schema/hosting_storefront_schema.php';
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';

function hosting_storefront_guard(): void { hosting_catalog_guard(); }
function hosting_storefront_tables(): array { return array_keys(hosting_storefront_schema(config()['db']['prefix'])); }
function hosting_storefront_table(string $name): void {
    if (!in_array($name,hosting_storefront_tables(),true)) throw new LogicException('Unknown hosting storefront table.');
}
function hosting_storefront_exists(string $name): bool {
    hosting_storefront_table($name);
    return one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])!==null;
}
function hosting_storefront_recovery(): never {
    throw new RuntimeException('Hosting storefront history needs recovery. Restore its original tables, settings and audit records.');
}
function hosting_storefront_validate_schema(string $name,string $sql): void {
    $expected=hosting_storefront_indexes($name);$unique=[];
    foreach ($expected as $key=>[$nonUnique,$columns]) if ($key!=='PRIMARY' && $nonUnique===0) $unique[$key]=$columns;
    catalog_validate_schema($name,$sql,$unique);
    foreach (all('SHOW FULL COLUMNS FROM '.table($name)) as $column) {
        $auto=$column['Field']==='seq'?'auto_increment':'';
        $collation=match($column['Field']) {
            'id','slug','state','category_slug','source','classification_id','document_sha256'=>'ascii_bin',
            'document_json'=>'utf8mb4_bin',
            default=>null,
        };
        if ($column['Extra']!==$auto || $column['Default']!==null || $column['Collation']!==$collation) hosting_storefront_recovery();
    }
    $actual=[];
    foreach (all('SHOW INDEX FROM '.table($name)) as $index) {
        if ($index['Sub_part']!==null || $index['Index_type']!=='BTREE') hosting_storefront_recovery();
        $actual[$index['Key_name']][0]=(int)$index['Non_unique'];
        $actual[$index['Key_name']][1][(int)$index['Seq_in_index']]=$index['Column_name'];
    }
    foreach ($actual as &$index) { ksort($index[1]);$index[1]=array_values($index[1]); } unset($index);
    ksort($actual);ksort($expected);if ($actual!==$expected) hosting_storefront_recovery();
    $relations=all('SELECT k.CONSTRAINT_NAME,k.ORDINAL_POSITION,k.COLUMN_NAME,k.REFERENCED_TABLE_SCHEMA,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
    $groups=[];
    foreach ($relations as $relation) {
        if ($relation['REFERENCED_TABLE_SCHEMA']!==config()['db']['name'] || !in_array($relation['DELETE_RULE'],['RESTRICT','NO ACTION'],true) || !in_array($relation['UPDATE_RULE'],['RESTRICT','NO ACTION'],true)) hosting_storefront_recovery();
        $groups[$relation['CONSTRAINT_NAME']][(int)$relation['ORDINAL_POSITION']]=$relation;
    }
    $actual=[];
    foreach ($groups as $rows) {
        ksort($rows);$rows=array_values($rows);
        if (count(array_unique(array_column($rows,'REFERENCED_TABLE_NAME')))!==1) hosting_storefront_recovery();
        $actual[]=json_encode([array_column($rows,'COLUMN_NAME'),$rows[0]['REFERENCED_TABLE_NAME'],array_column($rows,'REFERENCED_COLUMN_NAME')],JSON_THROW_ON_ERROR);
    }
    $expected=array_map(static fn(array $r):string=>json_encode([$r[0],config()['db']['prefix'].$r[1],$r[2]],JSON_THROW_ON_ERROR),hosting_storefront_relations($name));
    sort($actual);sort($expected);if ($actual!==$expected) hosting_storefront_recovery();
}
function hosting_storefront_row_document(string $name,array $row): array {
    hosting_storefront_table($name);service_plans_id($row['id']);hosting_storefront_hash($row['document_sha256']);
    if (!is_string($row['document_json']) || !hash_equals(hash('sha256',$row['document_json']),$row['document_sha256'])) hosting_storefront_recovery();
    $d=match($name) {
        'hosting_storefront_categories'=>hosting_storefront_category_data($row['document_json']),
        'hosting_storefront_classifications'=>hosting_storefront_classification_data($row['document_json']),
        'hosting_storefront_publications'=>hosting_storefront_publication_data($row['document_json']),
        default=>throw new LogicException('Unknown hosting storefront table.'),
    };
    if ($d['id']!==$row['id'] || $d['created_at']!==hosting_catalog_integer($row['created_epoch'],253402300799) || $d['created_by']!==hosting_catalog_integer($row['created_by']) || hosting_catalog_integer($row['seq'])<1) hosting_storefront_recovery();
    if ($name==='hosting_storefront_categories') {
        if ($d['slug']!==$row['slug'] || $d['revision']!==hosting_catalog_integer($row['revision'])) hosting_storefront_recovery();
    } elseif ($name==='hosting_storefront_classifications') {
        if ($d['product_id']!==hosting_catalog_integer($row['product_id']) || $d['product_revision']!==hosting_catalog_integer($row['product_revision']) || $d['state']!==$row['state'] || $d['category_slug']!==$row['category_slug'] || $d['category_revision']!==($row['category_revision']===null?null:hosting_catalog_integer($row['category_revision'])) || $d['source']!==$row['source']) hosting_storefront_recovery();
    } else {
        if ($d['product_id']!==hosting_catalog_integer($row['product_id']) || $d['category_slug']!==$row['category_slug'] || $d['category_revision']!==hosting_catalog_integer($row['category_revision']) || $d['classification_id']!==$row['classification_id'] || $d['source_format']!==hosting_catalog_integer($row['source_format'])) hosting_storefront_recovery();
    }
    return $d;
}
function hosting_storefront_witness(string $name,array $row): array {
    $kind=match($name) {'hosting_storefront_categories'=>'category','hosting_storefront_classifications'=>'classification','hosting_storefront_publications'=>'publication',default=>throw new LogicException('Unknown hosting storefront table.')};
    return [hosting_catalog_integer($row['created_by']),'hosting_storefront.'.$kind,'Record '.$row['id'].' sha256 '.$row['document_sha256'].' seq '.hosting_catalog_integer($row['seq']),gmdate('Y-m-d H:i:s',hosting_catalog_integer($row['created_epoch'],253402300799))];
}
function hosting_storefront_assert_witness(string $name,array $row): void {
    if (hosting_catalog_integer(query('SELECT COUNT(*) FROM '.table('audit').' WHERE user_id=? AND BINARY action=? AND BINARY detail=? AND created_at=?',hosting_storefront_witness($name,$row))->fetchColumn())!==1) hosting_storefront_recovery();
}
function hosting_storefront_row(string $name,string $id): array {
    hosting_storefront_table($name);service_plans_id($id);
    $row=one('SELECT * FROM '.table($name).' WHERE id=?',[$id]);if (!$row) hosting_storefront_recovery();
    hosting_storefront_row_document($name,$row);hosting_storefront_assert_witness($name,$row);return $row;
}
function hosting_storefront_validate_links(string $name,array $row): void {
    $d=hosting_storefront_row_document($name,$row);
    if ($name==='hosting_storefront_categories') return;
    if ($name==='hosting_storefront_publications') {
        $publication=one('SELECT product_id,created_by,document_json,document_sha256 FROM '.table('hosting_catalog_publications').' WHERE id=?',[$d['id']]);
        $category=one('SELECT id,document_json,document_sha256 FROM '.table('hosting_storefront_categories').' WHERE slug=? AND revision=?',[$d['category_slug'],$d['category_revision']]);
        $classification=one('SELECT product_id,document_json,document_sha256 FROM '.table('hosting_storefront_classifications').' WHERE id=?',[$d['classification_id']]);
        if (!$publication || !$category || !$classification || hosting_catalog_integer($publication['product_id'])!==$d['product_id'] || hosting_catalog_integer($classification['product_id'])!==$d['product_id'] || hosting_catalog_integer($publication['created_by'])!==$d['created_by'] || !hash_equals($publication['document_sha256'],$d['publication_sha256']) || !hash_equals($category['document_sha256'],$d['category_sha256']) || !hash_equals($classification['document_sha256'],$d['classification_sha256'])) hosting_storefront_recovery();
        $publicationDoc=HostingCatalogPublication::fromJson($publication['document_json']);
        if ($publicationDoc->data()['format']!==$d['source_format']) hosting_storefront_recovery();
        if ($d['source_format']===3 && ($publicationDoc->data()['category_json']!==$category['document_json'] || $publicationDoc->data()['classification_json']!==$classification['document_json'])) hosting_storefront_recovery();
        return;
    }
    $revision=one('SELECT snapshot FROM '.table('catalog_revisions').' WHERE product_id=? AND revision=?',[$d['product_id'],$d['product_revision']]);
    if (!$revision) hosting_storefront_recovery();
    if ($d['state']==='bound') {
        $category=one('SELECT * FROM '.table('hosting_storefront_categories').' WHERE slug=? AND revision=?',[$d['category_slug'],$d['category_revision']]);
        if (!$category || $category['id']!==$d['category_id'] || !hash_equals($category['document_sha256'],$d['category_sha256'])) hosting_storefront_recovery();
        hosting_storefront_row_document('hosting_storefront_categories',$category);hosting_storefront_assert_witness('hosting_storefront_categories',$category);
    }
}
/** Missing setup may leave only empty DDL. Any record, setting or audit fragment is recovery-only. */
function hosting_storefront_ready(): bool {
    hosting_catalog_lock();$tables=hosting_storefront_tables();$marker=one('SELECT value FROM '.table('settings').' WHERE name=?',['hosting_storefront_version']);
    $counts=[];$exists=[];$rows=[];
    foreach ($tables as $name) {
        $counts[$name]=one('SELECT value FROM '.table('settings').' WHERE name=?',[$name.'_count']);$exists[$name]=hosting_storefront_exists($name);
        $rows[$name]=$exists[$name]?hosting_catalog_integer(query('SELECT COUNT(*) FROM '.table($name))->fetchColumn(),999999999):0;
    }
    $events=hosting_catalog_integer(query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY LEFT(action,19)=?',['hosting_storefront.'])->fetchColumn());
    if (!$marker) {
        if (array_filter($counts)||array_sum($rows)!==0||$events!==0) hosting_storefront_recovery();
        return false;
    }
    if ($marker['value']!=='1'||$events!==1+array_sum($rows)) hosting_storefront_recovery();
    $setup=all('SELECT user_id,detail FROM '.table('audit').' WHERE BINARY action=?',['hosting_storefront.prepared']);
    if (count($setup)!==1 || hosting_catalog_integer($setup[0]['user_id'])<1 || $setup[0]['detail']!=='Hosting storefront storage version 1') hosting_storefront_recovery();
    foreach (hosting_storefront_schema(config()['db']['prefix']) as $name=>$sql) {
        if (!$exists[$name]||!$counts[$name]||hosting_catalog_integer($counts[$name]['value'],999999999)!==$rows[$name]) hosting_storefront_recovery();
        hosting_storefront_validate_schema($name,$sql);
        $cursor=0;
        do {
            $batch=all('SELECT * FROM '.table($name).' WHERE seq>? ORDER BY seq LIMIT 64',[$cursor]);
            foreach ($batch as $row) { hosting_storefront_validate_links($name,$row);hosting_storefront_assert_witness($name,$row);$cursor=hosting_catalog_integer($row['seq']); }
        } while (count($batch)===64);
    }
    return true;
}
function hosting_storefront_recorded(string $name,string $id): array {
    hosting_storefront_guard();$saved=one('SELECT * FROM '.table($name).' WHERE id=?',[$id]);if (!$saved) hosting_storefront_recovery();
    $row=hosting_storefront_row_document($name,$saved);$count=one('SELECT value FROM '.table('settings').' WHERE name=?',[$name.'_count']);
    if (!$count || hosting_catalog_integer($count['value'],999999999)>=999999999) hosting_storefront_recovery();
    save_setting($name.'_count',(string)(hosting_catalog_integer($count['value'],999999999)+1));
    query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',hosting_storefront_witness($name,$saved));
    return $row;
}
function hosting_storefront_insert_category(array $d): array {
    hosting_storefront_guard();$json=hosting_storefront_category_document($d);$hash=hash('sha256',$json);
    query('INSERT INTO '.table('hosting_storefront_categories').' (id,slug,revision,created_by,created_epoch,document_json,document_sha256) VALUES (?,?,?,?,?,?,?)',[$d['id'],$d['slug'],$d['revision'],$d['created_by'],$d['created_at'],$json,$hash]);
    hosting_storefront_recorded('hosting_storefront_categories',$d['id']);return $d+['sha256'=>$hash];
}
function hosting_storefront_insert_classification(array $d): array {
    hosting_storefront_guard();$json=hosting_storefront_classification_document($d);$hash=hash('sha256',$json);
    query('INSERT INTO '.table('hosting_storefront_classifications').' (id,product_id,product_revision,state,category_slug,category_revision,source,created_by,created_epoch,document_json,document_sha256) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$d['id'],$d['product_id'],$d['product_revision'],$d['state'],$d['category_slug'],$d['category_revision'],$d['source'],$d['created_by'],$d['created_at'],$json,$hash]);
    hosting_storefront_recorded('hosting_storefront_classifications',$d['id']);return $d+['sha256'=>$hash];
}
function hosting_storefront_insert_publication(array $d): array {
    hosting_storefront_guard();$json=hosting_storefront_publication_document($d);$hash=hash('sha256',$json);
    query('INSERT INTO '.table('hosting_storefront_publications').' (id,product_id,category_slug,category_revision,classification_id,source_format,created_by,created_epoch,document_json,document_sha256) VALUES (?,?,?,?,?,?,?,?,?,?)',[$d['id'],$d['product_id'],$d['category_slug'],$d['category_revision'],$d['classification_id'],$d['source_format'],$d['created_by'],$d['created_at'],$json,$hash]);
    hosting_storefront_recorded('hosting_storefront_publications',$d['id']);return $d+['sha256'=>$hash];
}
function hosting_storefront_current_category(string $slug): ?array {
    hosting_storefront_guard();hosting_storefront_slug($slug);$row=one('SELECT * FROM '.table('hosting_storefront_categories').' WHERE slug=? ORDER BY seq DESC LIMIT 1',[$slug]);
    if (!$row) return null;$d=hosting_storefront_row_document('hosting_storefront_categories',$row);hosting_storefront_assert_witness('hosting_storefront_categories',$row);return $d+['sha256'=>$row['document_sha256']];
}
function hosting_storefront_current_classification(int $product): ?array {
    hosting_storefront_guard();service_plans_actor($product);$row=one('SELECT * FROM '.table('hosting_storefront_classifications').' WHERE product_id=? ORDER BY seq DESC LIMIT 1',[$product]);
    if (!$row) return null;$d=hosting_storefront_row_document('hosting_storefront_classifications',$row);hosting_storefront_assert_witness('hosting_storefront_classifications',$row);hosting_storefront_validate_links('hosting_storefront_classifications',$row);return $d+['sha256'=>$row['document_sha256']];
}
function hosting_storefront_publication(string $id): array {
    hosting_storefront_guard();service_plans_id($id);$row=one('SELECT * FROM '.table('hosting_storefront_publications').' WHERE id=?',[$id]);
    if (!$row) hosting_storefront_recovery();$d=hosting_storefront_row_document('hosting_storefront_publications',$row);hosting_storefront_assert_witness('hosting_storefront_publications',$row);hosting_storefront_validate_links('hosting_storefront_publications',$row);return $d+['sha256'=>$row['document_sha256']];
}
/** Entry-point-local proof that the complete storefront history was validated under the owning guard. */
final class HostingStorefrontReadScope {
    private function __construct() {}
    public static function open(): ?self { hosting_storefront_guard();return hosting_storefront_ready()?new self:null; }
    public static function require(): self {
        $scope=self::open();if (!$scope) throw new RuntimeException('Prepare storefront categories before publishing hosting offers.');return $scope;
    }
}
function hosting_storefront_product_context(int $product,HostingStorefrontReadScope $scope): array {
    hosting_storefront_guard();
    $classification=hosting_storefront_current_classification($product);
    if (!$classification || $classification['state']!=='bound') throw new RuntimeException('Review and assign this product to a storefront category before publication.');
    $category=hosting_storefront_current_category($classification['category_slug']);
    if (!$category || $category['id']!==$classification['category_id'] || $category['revision']!==$classification['category_revision'] || !hash_equals($category['sha256'],$classification['category_sha256'])) throw new RuntimeException('The assigned storefront category changed. Review the product category again before publication.');
    $row=one('SELECT family FROM '.table('catalog_products').' WHERE id=?',[$product]);
    if (!$row || $row['family']!==$category['required_family']) throw new RuntimeException('The product fulfillment family differs from its storefront category.');
    if ($category['purchase_mode']!=='online') throw new RuntimeException('This storefront category is not available for online checkout.');
    $categoryData=array_diff_key($category,['sha256'=>true]);$classificationData=array_diff_key($classification,['sha256'=>true]);
    $categoryJson=hosting_storefront_category_document($categoryData);$classificationJson=hosting_storefront_classification_document($classificationData);
    return ['category_json'=>$categoryJson,'category_sha256'=>hash('sha256',$categoryJson),'classification_json'=>$classificationJson,'classification_sha256'=>hash('sha256',$classificationJson),'projection'=>hosting_storefront_category_projection($categoryData)];
}
function hosting_storefront_link_publication(HostingCatalogPublication $publication): array {
    hosting_storefront_guard();$scope=HostingStorefrontReadScope::require();
    $d=$publication->data();$old=one('SELECT id FROM '.table('hosting_storefront_publications').' WHERE id=?',[$d['id']]);
    if ($old) {
        $link=hosting_storefront_publication($d['id']);
        if (!hash_equals($link['publication_sha256'],$publication->fingerprint())) throw new RuntimeException('This publication already has different storefront history.');
        return $link;
    }
    $context=hosting_storefront_product_context($d['product']['id'],$scope);$category=hosting_storefront_category_data($context['category_json']);$classification=hosting_storefront_classification_data($context['classification_json']);
    if ($d['format']<3) {
        if ($category['slug']!=='shared-hosting') throw new RuntimeException('Historical hosting publications can only be linked as Shared.');
    } elseif ($d['category_json']!==$context['category_json'] || $d['classification_json']!==$context['classification_json']) throw new RuntimeException('The publication storefront snapshots changed before they were recorded.');
    return hosting_storefront_insert_publication(['format'=>1,'id'=>$d['id'],'publication_sha256'=>$publication->fingerprint(),'product_id'=>$d['product']['id'],'category_id'=>$category['id'],'category_slug'=>$category['slug'],'category_revision'=>$category['revision'],'category_sha256'=>$context['category_sha256'],'classification_id'=>$classification['id'],'classification_sha256'=>$context['classification_sha256'],'source_format'=>$d['format'],'created_by'=>$d['publisher']['id'],'created_at'=>time()]);
}
function hosting_storefront_classification_candidate(int $product,string $slug,array $actor): array {
    hosting_storefront_guard();$category=hosting_storefront_current_category($slug);if (!$category) throw new InvalidArgumentException('Choose a configured storefront category.');
    $row=one('SELECT id,revision,family FROM '.table('catalog_products').' WHERE id=?',[$product]);
    if (!$row || $row['family']!==$category['required_family']) throw new InvalidArgumentException('Choose a product whose fulfillment family matches the storefront category.');
    $current=hosting_storefront_current_classification($product);
    $review=['product'=>['id'=>$product,'revision'=>(int)$row['revision'],'family'=>$row['family']],'category'=>['id'=>$category['id'],'slug'=>$category['slug'],'revision'=>$category['revision'],'sha256'=>$category['sha256']],'current_sha256'=>$current['sha256']??null,'actor'=>['id'=>(int)$actor['id'],'auth_version'=>$actor['auth_version']]];
    return ['review'=>$review,'review_sha256'=>hash('sha256',service_plans_json($review)),'category'=>$category,'current'=>$current];
}
function hosting_storefront_binding_preview(int $product,string $slug): array {
    return staff_locked(fn():array=>staff_transaction(function()use($product,$slug):array {
        $actor=service_plans_owner();if (!hosting_storefront_ready()) throw new RuntimeException('Prepare storefront categories first.');
        $candidate=hosting_storefront_classification_candidate($product,$slug,$actor);
        return ['review_sha256'=>$candidate['review_sha256'],'product'=>$candidate['review']['product'],'category'=>hosting_storefront_category_projection(array_diff_key($candidate['category'],['sha256'=>true])),'current_sha256'=>$candidate['review']['current_sha256']];
    }));
}
function hosting_storefront_bind(string $id,int $product,string $slug,string $reviewHash,#[\SensitiveParameter] string $password): array {
    service_plans_id($id);hosting_storefront_hash($reviewHash);
    return staff_locked(fn():array=>staff_transaction(function()use($id,$product,$slug,$reviewHash,$password):array {
        $actor=service_plans_password($password);if (!hosting_storefront_ready()) throw new RuntimeException('Prepare storefront categories first.');
        $saved=one('SELECT * FROM '.table('hosting_storefront_classifications').' WHERE id=?',[$id]);
        if ($saved) {
            $old=hosting_storefront_row_document('hosting_storefront_classifications',$saved);hosting_storefront_assert_witness('hosting_storefront_classifications',$saved);hosting_storefront_validate_links('hosting_storefront_classifications',$saved);
            if ($old['product_id']!==$product || $old['state']!=='bound' || $old['category_slug']!==$slug || $old['source']!=='owner' || $old['created_by']!==(int)$actor['id']) throw new RuntimeException('This classification identity was already used for different details.');
            return $old+['sha256'=>$saved['document_sha256']];
        }
        $candidate=hosting_storefront_classification_candidate($product,$slug,$actor);
        if (!hash_equals($candidate['review_sha256'],$reviewHash)) throw new RuntimeException('The product, category, or owner authority changed. Review the category assignment again.');
        $current=$candidate['current'];
        if ($current && $current['state']==='bound' && $current['category_id']===$candidate['category']['id'] && hash_equals($current['category_sha256'],$candidate['category']['sha256'])) {
            if ($current['id']!==$id) throw new RuntimeException('This product is already assigned to that category under a different history identity.');
            return $current;
        }
        $category=$candidate['category'];
        return hosting_storefront_insert_classification(['format'=>1,'id'=>$id,'product_id'=>$product,'product_revision'=>$candidate['review']['product']['revision'],'state'=>'bound','category_id'=>$category['id'],'category_slug'=>$category['slug'],'category_revision'=>$category['revision'],'category_sha256'=>$category['sha256'],'source'=>'owner','created_by'=>(int)$actor['id'],'created_at'=>time()]);
    }));
}
/** Called only after an exact retained 20i addWeb-compatible import, under the shared lock/transaction. */
function hosting_storefront_classify_import(int $product,array $actor,string $source='twentyi-addweb-import'): ?array {
    hosting_storefront_guard();if (!hosting_storefront_ready()) return null;
    $current=hosting_storefront_current_classification($product);if ($current) return $current;
    $row=one('SELECT revision,family FROM '.table('catalog_products').' WHERE id=?',[$product]);
    $origin=one('SELECT id FROM '.table('catalog_provider_imports').' WHERE product_id=?',[$product]);
    if (!$origin && function_exists('provider_catalog_variant_ready') && provider_catalog_variant_ready()) $origin=one('SELECT id FROM '.table('catalog_provider_variants').' WHERE product_id=?',[$product]);
    if (!$row || !$origin || $row['family']!=='hosting') throw new RuntimeException('Only an exact retained Shared addWeb import or billing option can receive the default category.');
    $category=hosting_storefront_current_category('shared-hosting');if (!$category || $category['required_family']!=='hosting' || $category['purchase_mode']!=='online') hosting_storefront_recovery();
    return hosting_storefront_insert_classification(['format'=>1,'id'=>bin2hex(random_bytes(16)),'product_id'=>$product,'product_revision'=>(int)$row['revision'],'state'=>'bound','category_id'=>$category['id'],'category_slug'=>$category['slug'],'category_revision'=>$category['revision'],'category_sha256'=>$category['sha256'],'source'=>$source,'created_by'=>(int)$actor['id'],'created_at'=>time()]);
}

