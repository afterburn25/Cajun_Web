<?php
declare(strict_types=1);
require_once __DIR__.'/../../foundation/billing-calendar/BillingCalendar.php';
use CajunWebPro\Foundation\BillingCalendar\BillingCalendar as Calendar;

$checks = [];
function verify(string $name, bool $passed): void {
    global $checks;
    $checks[] = ['name' => $name, 'passed' => $passed];
    if (!$passed) throw new RuntimeException($name);
}
function refused(string $name, callable $call): void {
    try { $call(); $refused = false; } catch (InvalidArgumentException) { $refused = true; }
    verify($name, $refused);
}
function previousDay(string $date): string {
    return (new DateTimeImmutable($date, new DateTimeZone('UTC')))->sub(new DateInterval('P1D'))->format('Y-m-d');
}

try {
    // Literal independent expectations, not values produced by the calendar under test.
    $examples = [
        ['2025-01-31','monthly','day-of-month',0,'2025-01-31','2025-02-28'],
        ['2025-01-31','monthly','day-of-month',1,'2025-02-28','2025-03-31'],
        ['2025-01-31','monthly','day-of-month',2,'2025-03-31','2025-04-30'],
        ['2024-01-31','monthly','day-of-month',1,'2024-02-29','2024-03-31'],
        ['2025-01-30','monthly','day-of-month',1,'2025-02-28','2025-03-30'],
        ['2025-01-29','monthly','day-of-month',1,'2025-02-28','2025-03-29'],
        ['2025-01-28','monthly','day-of-month',1,'2025-02-28','2025-03-28'],
        ['2025-04-30','monthly','day-of-month',0,'2025-04-30','2025-05-30'],
        ['2025-04-30','monthly','month-end',0,'2025-04-30','2025-05-31'],
        ['2025-02-28','monthly','month-end',1,'2025-03-31','2025-04-30'],
        ['2024-02-29','monthly','month-end',11,'2025-01-31','2025-02-28'],
        ['2025-12-31','monthly','day-of-month',0,'2025-12-31','2026-01-31'],
        ['2025-01-31','quarterly','day-of-month',0,'2025-01-31','2025-04-30'],
        ['2025-01-31','quarterly','day-of-month',1,'2025-04-30','2025-07-31'],
        ['2025-11-30','quarterly','day-of-month',1,'2026-02-28','2026-05-30'],
        ['2025-11-30','quarterly','month-end',1,'2026-02-28','2026-05-31'],
        ['2023-11-30','quarterly','month-end',1,'2024-02-29','2024-05-31'],
        ['2024-02-29','annually','day-of-month',0,'2024-02-29','2025-02-28'],
        ['2024-02-29','annually','day-of-month',3,'2027-02-28','2028-02-29'],
        ['2023-02-28','annually','day-of-month',0,'2023-02-28','2024-02-28'],
        ['2023-02-28','annually','month-end',0,'2023-02-28','2024-02-29'],
        ['1896-02-29','annually','day-of-month',4,'1900-02-28','1901-02-28'],
        ['1996-02-29','annually','day-of-month',4,'2000-02-29','2001-02-28'],
        ['2096-02-29','annually','day-of-month',4,'2100-02-28','2101-02-28'],
        ['2396-02-29','annually','day-of-month',4,'2400-02-29','2401-02-28'],
        ['1000-01-01','monthly','day-of-month',0,'1000-01-01','1000-02-01'],
        ['9998-12-31','annually','month-end',0,'9998-12-31','9999-12-31'],
        ['2026-10-14','quarterly','day-of-month',0,'2026-10-14','2027-01-14'],
    ];
    foreach ($examples as $n => [$anchor,$cadence,$alignment,$index,$start,$end]) {
        $calendar = new Calendar($anchor,$cadence,$alignment);
        $expected = ['index'=>$index,'start'=>$start,'end'=>$end];
        verify('Literal period '.$n.' '.$anchor.' '.$cadence.' '.$alignment.' #'.$index, $calendar->period($index)===$expected);
        verify('Literal lookup '.$n.' includes first and final day only', $calendar->periodAt($start)===$expected && $calendar->periodAt(previousDay($end))===$expected);
    }
    $calendar = new Calendar('2025-01-31','monthly','day-of-month');
    verify('Exact boundary belongs to the next period', $calendar->periodAt('2025-02-28')===$calendar->period(1));
    verify('Day before the anchor has no period', $calendar->periodAt('2025-01-30')===null);
    verify('Distant pre-anchor date has no period', $calendar->periodAt('1000-01-01')===null);
    verify('Anchor month before the day stays in the preceding period', $calendar->periodAt('2025-03-30')===$calendar->period(1));
    verify('Lookup uses the original anniversary after a clipped month', $calendar->periodAt('2025-03-31')===$calendar->period(2));
    $distant = new Calendar('1000-01-31','monthly','day-of-month');
    verify('Distant date lookup does not require an unbounded catch-up window', $distant->periodAt('9998-12-30')===['index'=>107986,'start'=>'9998-11-30','end'=>'9998-12-31']);
    foreach (['monthly'=>1,'quarterly'=>3,'annually'=>12] as $cadence=>$months) {
        foreach (['day-of-month','month-end'] as $alignment) {
            $c = new Calendar('1999-01-31',$cadence,$alignment);
            $window = $c->window(0,120);
            $valid = count($window)===120;
            foreach ($window as $i=>$period) {
                $valid = $valid && $period===$c->period($i) && $period['start']<$period['end'];
                $valid = $valid && $c->periodAt($period['start'])===$period && $c->periodAt(previousDay($period['end']))===$period;
                if ($i>0) $valid = $valid && $window[$i-1]['end']===$period['start'];
                // Core DateTime on day one supplies an independent month/year oracle.
                $first=(new DateTimeImmutable('1999-01-01',new DateTimeZone('UTC')))->add(new DateInterval('P'.($i*$months).'M'));
                $last=$first->format('Y-m-t');
                $valid = $valid && $period['start']===$last;
            }
            verify('120 adjacent contained periods agree with core Gregorian oracle '.$cadence.' '.$alignment,$valid);
            verify('Window offset retains global period identity '.$cadence.' '.$alignment,$c->window(37,3)===array_slice($window,37,3));
        }
    }
    verify('A single-period window keeps the canonical projection',$calendar->window(8,1)===[$calendar->period(8)]);
    foreach (['','2025-2-01','2025-02-1','2025-02-29','1900-02-29','2100-02-29','2025-04-31','2025-00-01','2025-13-01','2025-01-00','2025-01-32','0000-01-01','0999-12-31','10000-01-01','2025-01-01T00:00:00Z','2025-01-01+00:00',' 2025-01-01','2025-01-01 ' ,"2025-01-01\n","2025-01-01\0",'tomorrow','2025-01-01 +1 month','２０２５-01-01',20250101,true,null,[],new stdClass] as $i=>$bad) {
        refused('Invalid anchor date '.$i,fn()=>new Calendar($bad,'monthly','day-of-month'));
        refused('Invalid lookup date '.$i,fn()=>$calendar->periodAt($bad));
    }
    foreach (['one-time','weekly','yearly','Monthly',' monthly','monthly ',1,true,null,[],new stdClass] as $i=>$bad) {
        refused('Invalid recurring cadence '.$i,fn()=>new Calendar('2025-01-31',$bad,'day-of-month'));
    }
    foreach (['','month_end','month-end ','DAY-OF-MONTH',1,true,null,[]] as $i=>$bad) {
        refused('Invalid explicit alignment '.$i,fn()=>new Calendar('2025-01-31','monthly',$bad));
    }
    foreach (['2025-01-30','2024-02-28','2025-04-29'] as $bad) {
        refused('Month-end requires an actual original month end '.$bad,fn()=>new Calendar($bad,'monthly','month-end'));
    }
    foreach ([-1,120000,PHP_INT_MAX,1.0,'1',true,null,[],new stdClass] as $i=>$bad) {
        refused('Invalid period index '.$i,fn()=>$calendar->period($bad));
        refused('Invalid window first index '.$i,fn()=>$calendar->window($bad,1));
    }
    foreach ([0,-1,121,PHP_INT_MAX,1.0,'1',true,null,[],new stdClass] as $i=>$bad) {
        refused('Invalid window count '.$i,fn()=>$calendar->window(0,$bad));
    }
    refused('Window cannot overflow its independent index bound',fn()=>$calendar->window(Calendar::MAX_INDEX,2));
    foreach (['monthly','quarterly','annually'] as $cadence) {
        $c=new Calendar('9999-12-31',$cadence,'month-end');
        refused('Period end cannot exceed year9999 '.$cadence,fn()=>$c->period(0));
        refused('Lookup refuses a period whose end cannot be represented '.$cadence,fn()=>$c->periodAt('9999-12-31'));
        refused('Window never returns a partially representable year9999 range '.$cadence,fn()=>(new Calendar('9998-12-31',$cadence,'month-end'))->window(0,13));
    }
    refused('Finite but distant index still enforces supported dates',fn()=>$calendar->period(Calendar::MAX_INDEX));
    $original=$calendar->window(0,4);$copy=$original;$copy[0]['start']='2025-01-01';
    verify('Returned period mutation cannot change the original calendar',$calendar->window(0,4)===$original);
    $anchor='2025-01-31';$c=new Calendar($anchor,'monthly','day-of-month');$anchor='2025-02-01';
    verify('Changing a caller anchor cannot change a constructed calendar',$c->anchor==='2025-01-31' && $c->period(0)===$calendar->period(0));
    try {$c->anchor='2025-02-01';$immutable=false;} catch (Error) {$immutable=true;}
    verify('Calendar anchor remains immutable',$immutable && $c->period(0)===$calendar->period(0));
    verify('A clone keeps the exact immutable billing rules',(clone $calendar)->window(0,4)===$original);
    $timezone=date_default_timezone_get();
    try {
        foreach (['UTC','America/Chicago','Pacific/Kiritimati','Pacific/Pago_Pago','Europe/London'] as $zone) {
            date_default_timezone_set($zone);
            verify('Default timezone cannot alter calendar dates '.$zone,$calendar->window(0,4)===$original && $calendar->periodAt('2025-03-30')===$original[1]);
        }
    } finally {date_default_timezone_set($timezone);}
    verify('Failed requests cannot mutate later valid periods',$calendar->window(0,4)===$original);
    verify('Period projection contains only index and calendar boundaries',array_keys($calendar->period(0))===['index','start','end']);
    echo json_encode(['passed'=>true,'checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $e) {
    echo json_encode(['passed'=>false,'checks'=>$checks,'error'=>get_class($e).': '.$e->getMessage()],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
    exit(1);
}
