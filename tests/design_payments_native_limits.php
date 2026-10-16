<?php
declare(strict_types=1);
use CajunWebPro\Foundation\DesignPayments\DesignPaymentSchedule;
if(PHP_SAPI!=='cli'||!defined('CWP_BOOT'))exit(2);
/** Construct bounded historical fixtures directly; production writes are tested separately. */
function dp_history_fixture(int $job,int $base,bool $large):array{
    $row=studio_job($job);$expected=design_payments_initial($row);$initial=$expected;$previous=str_repeat('0',64);$bytes=0;$count=0;$first=null;$lastId='';$time=time();$actor=['type'=>'staff','id'=>1];
    $template=dp_fields($base,$job);
    if($large){$template['terms']=str_repeat('Terms ',5000);$template['items']=[];for($i=0;$i<40;$i++)$template['items'][]=['id'=>dp_id(8000+$i),'description'=>str_repeat('x',2000),'quantity'=>1,'unit_cents'=>1,'discount_cents'=>0,'tax_cents'=>0];}
    db()->beginTransaction();try{
        while($count<DESIGN_PAYMENTS_MAX_EVENTS){
            $fields=$template;$fields['id']=dp_id($base+$count);$schedule=DesignPaymentSchedule::fromArray($fields);$payload=$schedule->data();
            $event=['format'=>1,'id'=>$fields['id'],'project_id'=>$job,'client_id'=>1,'sequence'=>$count,'kind'=>'offered','actor_type'=>'staff','actor_id'=>1,'occurred_at'=>$time,'previous'=>$previous,'expected'=>$expected,'payload'=>$payload];$json=design_payments_json($event);
            if($bytes+strlen($json)>DESIGN_PAYMENTS_MAX_BYTES)break;
            $hash=hash('sha256',$json);$input=design_payments_command($job,1,$actor,'offered',$expected,$payload);
            query('INSERT INTO '.table('design_payment_events').' (id,job_id,client_id,staff_id,client_actor_id,sequence,kind,created_at,event_json,event_sha256,input_sha256) VALUES (?,?,?,1,NULL,?,?,?,?,?,?)',[$fields['id'],$job,1,$count,'offered',gmdate('Y-m-d H:i:s',$time),$json,$hash,$input]);
            if($first===null)$first=$fields;$bytes+=strlen($json);$previous=$expected=$hash;$lastId=$fields['id'];$count++;
        }
        query('INSERT INTO '.table('design_payment_heads').' (id,event_id,event_sha256,event_count,retained_bytes) VALUES (?,?,?,?,?)',[$job,$lastId,$previous,$count,$bytes]);db()->commit();
    }catch(Throwable $error){if(db()->inTransaction())db()->rollBack();throw $error;}
    return ['count'=>$count,'bytes'=>$bytes,'first'=>$first,'initial'=>$initial,'expected'=>$expected];
}
$capacity=dp_history_fixture(5,100000,false);$view=dp_view(5);$countHead=$view['expected'];
dp_ok('Complete4096-event financial history remains readable under128MiB',$capacity['count']===4096&&$view['count']===4096&&count($view['items'])===6&&$view['page']===410);
dp_ok('First financial history page contains original immutable offers',design_payments_staff_get(5,1)['items'][0]['id']===dp_id(100000));
dp_deny('Unknown history page refuses instead of returning empty financial state',fn()=>design_payments_staff_get(5,411));
dp_deny('New offers refuse at retained event capacity without pruning',fn()=>design_payments_publish(5,1,dp_id(200000),$view['expected'],dp_fields(200000,5)));
dp_ok('Original offer retries still work at event capacity',design_payments_publish(5,1,dp_id(100000),$capacity['initial'],$capacity['first'])['replayed']);
$capacity=dp_history_fixture(6,300000,true);$view=dp_view(6);
dp_ok('Nearly32MiB retained financial history remains readable under128MiB',$capacity['bytes']>DESIGN_PAYMENTS_MAX_BYTES-133120&&$view['count']===$capacity['count']);
$large=$capacity['first'];$large['id']=dp_id(400000);
dp_deny('New offers refuse when retained byte capacity would be exceeded',fn()=>design_payments_publish(6,1,dp_id(400000),$view['expected'],$large));
dp_ok('Original offer retries still work at byte capacity',design_payments_publish(6,1,dp_id(300000),$capacity['initial'],$capacity['first'])['replayed']);
dp_ok('Capacity refusals preserve exact financial heads',dp_view(5)['expected']===$countHead&&dp_view(6)['expected']===$capacity['expected']);
