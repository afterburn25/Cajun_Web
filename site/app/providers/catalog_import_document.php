<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Only documented typeRef/packageTypeName from saved GET /package observations. */
final readonly class ProviderCatalogImportDocument {
    private function __construct(public array $types, public int $unnamed) {}

    public static function fromRows(array $rows): self {
        if (!array_is_list($rows) || count($rows)>8192) throw new InvalidArgumentException('The saved hosting observation exceeds the supported bounds.');
        $types=[];$unnamed=0;
        foreach ($rows as $row) {
            if (!is_array($row)) throw new InvalidArgumentException('The saved hosting type is invalid.');
            $id=$row['type_ref']??null;
            if ((!is_int($id)&&!is_string($id)) || !preg_match('/^[1-9][0-9]{0,17}$/D',(string)$id)) throw new InvalidArgumentException('The saved hosting type reference is invalid.');
            $name=$row['package_type_name']??null;
            if ($name===null) {$unnamed++;continue;}
            if (!is_string($name)||trim($name)===''||strlen($name)>120||preg_match('//u',$name)!==1||preg_match('/[\x00-\x1f\x7f]/',$name)) throw new InvalidArgumentException('The saved hosting type name is invalid.');
            $key='type:'.$id;
            if (isset($types[$key])&&$types[$key]['name']!==$name) throw new RuntimeException('The saved observation has conflicting names for one hosting type. Refresh and review the inventory first.');
            $types[$key]=['type_ref'=>(string)$id,'name'=>$name];
        }
        ksort($types,SORT_STRING);
        return new self(array_values($types),$unnamed);
    }

    public function type(string $id): array {
        foreach ($this->types as $type) if ($type['type_ref']===$id) return $type;
        throw new InvalidArgumentException('Choose a named hosting type from the current saved observation.');
    }

    /** No inferred price, limits, tax, stock, image, entitlement, or provider billing term. */
    public function draft(string $id,string $sku,string $term): array {
        $type=$this->type($id);
        if (!in_array($term,['once','month','year'],true)) throw new InvalidArgumentException('Choose the local service period you intend to review.');
        return ['sku'=>$sku,'title'=>$type['name'],'family'=>'hosting','status'=>'draft','term'=>$term,'currency'=>'USD','amount'=>'','setup'=>'0.00','cost'=>'','provider_ref'=>'20i/observed-type/'.$id,'summary'=>'','features'=>'','terms'=>'','sort_order'=>'0'];
    }
}
