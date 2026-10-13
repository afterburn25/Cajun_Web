<?php
/** Scheduling validation and canonical calendar mapping; no persistence or network. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\Scheduling\WeeklyRule;
use CajunWebPro\Foundation\Scheduling\TimeWindow;
use CajunWebPro\Foundation\Scheduling\SchedulePolicy;
use CajunWebPro\Foundation\Scheduling\Consultation;
use CajunWebPro\Foundation\Scheduling\ConsultationCalendar;

foreach (['TimeWindow','WeeklyRule','SchedulePolicy','Consultation','ConsultationCalendar'] as $class) require_once CWP_CODE_ROOT.'/includes/foundation/scheduling/'.$class.'.php';

function appointments_text(mixed $value,string $label,int $max,bool $required=true): string {
    if (!is_string($value) || strlen($value)>$max || !preg_match('//u',$value) || preg_match('/[\x00-\x1F\x7F]/',$value)) throw new InvalidArgumentException('Check '.$label.' and its length limit.');
    $value=trim($value);if ($required && $value==='') throw new InvalidArgumentException('Complete '.$label.'.');return $value;
}
function appointments_uint(mixed $value,string $label,int $min,int $max): int {
    if ((!is_string($value) && !is_int($value)) || !preg_match('/^[0-9]{1,12}$/D',(string)$value) || (int)$value<$min || (int)$value>$max) throw new InvalidArgumentException('Check '.$label.'.');return (int)$value;
}
function appointments_zone(mixed $value): string {return WeeklyRule::zone(appointments_text($value,'the time zone',80))->getName();}
function appointments_minute(mixed $value,bool $end=false): int {
    $value=appointments_text($value,'the availability time',5);
    if ($end && $value==='24:00') return 1440;
    if (!preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])$/D',$value,$parts)) throw new InvalidArgumentException('Enter availability as HH:MM using the 24-hour clock.');
    return (int)$parts[1]*60+(int)$parts[2];
}
function appointments_config_values(array $input): array {
    $zone=appointments_zone($input['timezone']??null);$duration=appointments_uint($input['slot_minutes']??null,'the consultation length',15,120);
    if (!in_array($duration,[15,30,45,60,90,120],true)) throw new InvalidArgumentException('Choose an offered consultation length.');
    $buffer=appointments_uint($input['buffer_minutes']??null,'the appointment buffer',0,120);$notice=appointments_uint($input['notice_minutes']??null,'the minimum notice',0,10080);
    $enabled=appointments_uint($input['enabled']??'0','whether scheduling is enabled',0,1);$hours=$input['hours']??[];
    if (!is_array($hours) || count($hours)>7) throw new InvalidArgumentException('Use one availability window per weekday.');
    $rules=[];$seen=[];
    foreach ($hours as $day=>$hour) {
        $day=appointments_uint($day,'the weekday',0,6);if (!is_array($hour) || isset($seen[$day])) throw new InvalidArgumentException('Use one set of availability fields per weekday.');$seen[$day]=true;
        if (appointments_uint($hour['enabled']??'0','whether the weekday is available',0,1)===0) continue;
        $rule=new WeeklyRule($day,appointments_minute($hour['start']??null),appointments_minute($hour['end']??null,true),$zone);
        $rules[]=['day'=>$rule->dayOfWeek,'start'=>$rule->startMinute,'end'=>$rule->endMinute];
    }
    if ($enabled && !$rules) throw new InvalidArgumentException('Add available hours before enabling appointment requests.');
    usort($rules,fn($a,$b)=>$a['day']<=>$b['day']);
    return ['timezone'=>$zone,'slot_minutes'=>$duration,'buffer_minutes'=>$buffer,'notice_minutes'=>$notice,'enabled'=>$enabled,'rules'=>json_encode($rules,JSON_THROW_ON_ERROR)];
}
function appointments_policy(array $settings): SchedulePolicy {return new SchedulePolicy((int)$settings['slot_minutes'],(int)$settings['buffer_minutes']*60,(int)$settings['notice_minutes']*60);}
function appointments_model(array $row): Consultation {
    return new Consultation((string)$row['lead_id'],$row['name'],$row['email'],new TimeWindow((int)$row['starts_at'],(int)$row['ends_at']),$row['timezone'],$row['status'],(int)$row['requested_at']);
}
function appointments_calendar(array $settings,array $rows,int $from,int $until): ConsultationCalendar {
    $windows=[];
    foreach (json_decode($settings['rules'],true,16,JSON_THROW_ON_ERROR) as $item) {
        $rule=new WeeklyRule($item['day'],$item['start'],$item['end'],$settings['timezone']);array_push($windows,...$rule->expand($from,$until));
    }
    return new ConsultationCalendar(appointments_policy($settings),$windows,array_map('appointments_model',$rows));
}
function appointments_slot(mixed $start,array $settings,int $now): TimeWindow {
    $start=appointments_uint($start,'the requested time',0,4102444800);
    if ($start<$now || $start>=$now+30*86400) throw new InvalidArgumentException('Choose an available time within the next 30 days.');
    return new TimeWindow($start,$start+(int)$settings['slot_minutes']*60);
}
function appointments_label(int $start,int $end,string $zone): string {
    $tz=new DateTimeZone($zone);$begin=(new DateTimeImmutable('@'.$start))->setTimezone($tz);$finish=(new DateTimeImmutable('@'.$end))->setTimezone($tz);
    return $begin->format('D, M j, Y H:i').' – '.$finish->format('H:i').' '.$zone.' (UTC'.$begin->format('P').')';
}
