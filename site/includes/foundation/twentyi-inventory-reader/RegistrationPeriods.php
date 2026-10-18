<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyIInventoryReader;
if(PHP_SAPI!=='cli'&&!defined('CWP_BOOT')){http_response_code(404);exit;}
use CajunWebPro\Foundation\Provider\TransportResponse;
use CajunWebPro\Foundation\TwentyIInventory\{InventorySnapshot,RegistrationPeriod};

/** Owner-supplied full reference34e713c3, lines1233-1237: dotted TLD => integer-year array. */
final class RegistrationPeriods
{
    public const MAX_BODY_BYTES=1048576;
    public const MAX_TLDS=4096;

    public static function project(TransportResponse $response,int $observedAt,string $scope):InventorySnapshot
    {
        $reason=match(true){
            in_array($response->status,[401,403],true)=>'authentication',
            $response->status===429=>'rate_limited',
            $response->status>=500=>'remote_error',
            $response->status!==200=>'unexpected_status',
            strlen($response->body)>self::MAX_BODY_BYTES=>'response_limit',
            default=>null,
        };
        if($reason!==null)return InventorySnapshot::unavailable($reason,$observedAt,$scope);
        try{$map=json_decode($response->body,false,8,JSON_THROW_ON_ERROR);}
        catch(\JsonException){return InventorySnapshot::unavailable('malformed_body',$observedAt,$scope);}
        if(!$map instanceof \stdClass)return InventorySnapshot::unavailable('unexpected_shape',$observedAt,$scope);
        $fields=get_object_vars($map);
        if(count($fields)>self::MAX_TLDS)return InventorySnapshot::unavailable('response_limit',$observedAt,$scope);
        if(self::duplicateTopLevelKeys($response->body))return InventorySnapshot::unavailable('malformed_body',$observedAt,$scope);
        $records=[];$skipped=0;$reasons=[];
        foreach($fields as $tld=>$years){
            try{$records[]=RegistrationPeriod::fromItem($tld,$years);}
            catch(\InvalidArgumentException $e){$skipped++;if(count($reasons)<3&&!in_array($e->getMessage(),$reasons,true))$reasons[]=$e->getMessage();}
        }
        usort($records,static fn(RegistrationPeriod $a,RegistrationPeriod $b):int=>strcmp($a->tld,$b->tld));
        return new InventorySnapshot($skipped?'partial':'complete',$records,$observedAt,$skipped,$reasons,'',$scope);
    }

    /** JSON has already been validated. Count top-level keys without losing duplicates to json_decode. */
    private static function duplicateTopLevelKeys(string $json):bool
    {
        $depth=0;$keys=[];$length=strlen($json);
        for($i=0;$i<$length;$i++){
            $c=$json[$i];
            if($c==='{'||$c==='['){$depth++;continue;}
            if($c==='}'||$c===']'){$depth--;continue;}
            if($c!=='"')continue;
            $start=$i++;
            while($i<$length){if($json[$i]==='\\'){$i+=2;continue;}if($json[$i]==='"')break;$i++;}
            if($depth===1){
                $key=json_decode(substr($json,$start,$i-$start+1),true,2,JSON_THROW_ON_ERROR);
                // At object depth1 a string value is malformed as a period row; only keys followed by ':' count.
                $next=$i+1;while($next<$length&&str_contains(" \t\r\n",$json[$next]))$next++;
                if($next<$length&&$json[$next]===':'){if(isset($keys[$key]))return true;$keys[$key]=true;}
            }
        }
        return false;
    }
}
