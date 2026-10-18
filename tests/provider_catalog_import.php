<?php
/** No database, provider, upload, or publication. Exercises the real saved-type document. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/app/providers/catalog_import_document.php';
$checks=[];
function pci_ok(string $name,bool $passed):void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function pci_deny(string $name,callable $call):void {try{$call();}catch(InvalidArgumentException|RuntimeException){pci_ok($name,true);return;}pci_ok($name,false);}
try {
    $rows=[['type_ref'=>'811','package_type_name'=>'Linux Unlimited','name'=>'private-client.example','stack_users'=>['stack-user:private'],'productSpec'=>['secret'=>'private-api-key'],'image'=>'https://127.0.0.1/private'],['type_ref'=>811,'package_type_name'=>'Linux Unlimited'],['type_ref'=>814,'package_type_name'=>'Hosting & care']];
    $document=ProviderCatalogImportDocument::fromRows($rows);
    pci_ok('Repeated package type references become exactly two local choices',$document->types===[['type_ref'=>'811','name'=>'Linux Unlimited'],['type_ref'=>'814','name'=>'Hosting & care']]);
    pci_ok('Private packages domains users metadata and remote images are absent',array_keys($document->types[0])===['type_ref','name']&&!str_contains(json_encode($document,JSON_THROW_ON_ERROR),'private')&&!str_contains(json_encode($document),'https://'));
    pci_ok('Source row order does not change deduplicated preview',ProviderCatalogImportDocument::fromRows(array_reverse($rows))->types===$document->types);
    pci_ok('Empty complete response is an empty choice list',ProviderCatalogImportDocument::fromRows([])->types===[]);
    $unnamed=ProviderCatalogImportDocument::fromRows([['type_ref'=>1,'package_type_name'=>null],['type_ref'=>2]]);
    pci_ok('Missing documented names are not invented',$unnamed->types===[]&&$unnamed->unnamed===2);
    pci_deny('Unnamed types cannot be imported',fn()=>$unnamed->type('1'));
    pci_deny('Same identifier with conflicting names is refused',fn()=>ProviderCatalogImportDocument::fromRows([['type_ref'=>811,'package_type_name'=>'A'],['type_ref'=>811,'package_type_name'=>'B']]));
    pci_deny('Associative row collection is refused',fn()=>ProviderCatalogImportDocument::fromRows(['row'=>$rows[0]]));
    pci_deny('Observation beyond bounded stored inventory is refused',fn()=>ProviderCatalogImportDocument::fromRows(array_fill(0,8193,$rows[0])));
    pci_ok('Bounded repeated observations do not create extra products',count(ProviderCatalogImportDocument::fromRows(array_fill(0,8192,$rows[0]))->types)===1);
    foreach([null,true,false,0,-1,1.5,'0','01','1e3','+1','1 ',' 1',str_repeat('9',19),[],new stdClass] as $i=>$bad) pci_deny('Invalid type reference '.$i,fn()=>ProviderCatalogImportDocument::fromRows([['type_ref'=>$bad,'package_type_name'=>'Hosting']]));
    foreach(['',"\t",' ',str_repeat('a',121),"Name\n", "Name\0", "\x7f", "\xff",12,true,[],new stdClass] as $i=>$bad)pci_deny('Invalid provider type name '.$i,fn()=>ProviderCatalogImportDocument::fromRows([['type_ref'=>1,'package_type_name'=>$bad]]));
    pci_ok('Documented UTF-8 names are preserved',ProviderCatalogImportDocument::fromRows([['type_ref'=>1,'package_type_name'=>'Hébergement & support']])->type('1')['name']==='Hébergement & support');
    foreach(['once','month','year'] as $term){$draft=$document->draft('811','OWNER-SKU',$term);pci_ok('Explicit local period '.$term.' creates only an unpriced hosting draft',$draft===['sku'=>'OWNER-SKU','title'=>'Linux Unlimited','family'=>'hosting','status'=>'draft','term'=>$term,'currency'=>'USD','amount'=>'','setup'=>'0.00','cost'=>'','provider_ref'=>'20i/observed-type/811','summary'=>'','features'=>'','terms'=>'','sort_order'=>'0']);}
    foreach(['','annual','auto','MONTH'] as $term)pci_deny('Unreviewed local period refused '.json_encode($term),fn()=>$document->draft('811','SKU',$term));
    pci_deny('Unobserved type cannot create a draft',fn()=>$document->draft('815','SKU','month'));
    pci_deny('Type selection uses exact canonical identity',fn()=>$document->type('0811'));
    pci_ok('Source records remain untouched after projection and draft creation',$rows[0]['name']==='private-client.example'&&$rows[0]['productSpec']['secret']==='private-api-key');
    echo json_encode(['passed'=>true,'checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $e){echo json_encode(['passed'=>false,'checks'=>$checks,'failure'=>$e::class.': '.$e->getMessage()],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;exit(1);}
