<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyIInventory;
if(PHP_SAPI!=='cli'&&!defined('CWP_BOOT')){http_response_code(404);exit;}

/** Observed registration-year options. They imply no renewal, price or entitlement. */
final readonly class RegistrationPeriod
{
    /** Defensive local bounds, not claims about registry rules. */
    public const MAX_OPTIONS=64;
    public const MAX_YEAR=2147483647;
    private function __construct(public string $tld,public array $years){}

    public static function fromItem(mixed $tld,mixed $years):self
    {
        if(!is_string($tld)||strlen($tld)>254||$tld!==strtolower($tld)
            ||!preg_match('/\A\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*\z/',$tld))
            throw new \InvalidArgumentException('Registration extension is not canonical.');
        if(!is_array($years)||!array_is_list($years)||count($years)>self::MAX_OPTIONS)
            throw new \InvalidArgumentException('Registration options are not a bounded year list.');
        $copy=[];
        foreach($years as $year){
            if(!is_int($year)||$year<1||$year>self::MAX_YEAR||isset($copy[$year]))
                throw new \InvalidArgumentException('Registration options contain an invalid or repeated year.');
            $copy[$year]=$year;
        }
        sort($copy,SORT_NUMERIC);
        return new self($tld,$copy);
    }
}
