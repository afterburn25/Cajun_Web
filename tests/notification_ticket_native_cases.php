<?php
/** Current-authority ticket event projection inside the notification native fixture. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||!function_exists('nc_ok'))exit(2);
require_once __DIR__.'/../site/app/support/ticket_views.php';

$ticket=str_repeat('a',32);$opening=str_repeat('b',32);$reply=str_repeat('c',32);$note=str_repeat('d',32);
nc_owner();query('UPDATE '.table('staff_profiles').' SET permissions=? WHERE user_id=2',[json_encode(['clients.manage','support.tickets'])]);support_tickets_migrate();
nc_client();support_tickets_client_create($ticket,1,'Confidential ticket subject',$opening,'Secret opening body');
$ticketRows=all('SELECT * FROM '.table('notification_feed').' WHERE ref=? ORDER BY audience,recipient_id',['ticket:'.$ticket]);
nc_ok('Client ticket opening notifies only current ticket staff',array_map('intval',array_column($ticketRows,'recipient_id'))===[1,2]&&!array_filter($ticketRows,static fn($row)=>$row['audience']!=='staff'||$row['kind']!=='support-reply'));
nc_ok('Ticket opening projection excludes subject and body',!str_contains(json_encode($ticketRows,JSON_THROW_ON_ERROR),'Confidential ticket subject')&&!str_contains(json_encode($ticketRows,JSON_THROW_ON_ERROR),'Secret opening body'));
$count=count($ticketRows);support_tickets_client_create($ticket,1,'Confidential ticket subject',$opening,'Secret opening body');nc_ok('Exact ticket opening replay creates no duplicate notification',(int)query('SELECT COUNT(*) FROM '.table('notification_feed').' WHERE ref=?',['ticket:'.$ticket])->fetchColumn()===$count);

$projectionTicket=str_repeat('e',32);$projectionOpening=str_repeat('f',32);$projectionLog=$private.'/expected-ticket-projection.log';if(is_file($projectionLog))unlink($projectionLog);$priorErrorLog=(string)ini_get('error_log');ini_set('error_log',$projectionLog);
db()->exec("CREATE TRIGGER notifyci_fail_ticket_projection BEFORE INSERT ON notifyci_notification_feed FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic ticket projection failure'; END");
try{support_tickets_client_create($projectionTicket,1,'Projection recovery subject',$projectionOpening,'Projection recovery body');}finally{db()->exec('DROP TRIGGER notifyci_fail_ticket_projection');ini_set('error_log',$priorErrorLog);}
$projectionError=is_file($projectionLog)?file_get_contents($projectionLog):'';nc_ok('Expected projection failure retains a generic content-free diagnostic',preg_match('/CWP \[[A-F0-9]{8}\] PDOException code=/',(string)$projectionError)===1&&!str_contains((string)$projectionError,'Projection recovery subject')&&!str_contains((string)$projectionError,'Projection recovery body')&&!str_contains((string)$projectionError,'synthetic ticket projection failure'));
nc_ok('Projection failure does not roll back the committed ticket',one('SELECT id FROM '.table('support_tickets').' WHERE id=?',[$projectionTicket])!==null&&(int)query('SELECT COUNT(*) FROM '.table('notification_feed').' WHERE ref=?',['ticket:'.$projectionTicket])->fetchColumn()===0&&!db()->inTransaction());
support_tickets_client_create($projectionTicket,1,'Projection recovery subject',$projectionOpening,'Projection recovery body');nc_ok('Exact business replay recovers the deterministic projection',(int)query('SELECT COUNT(*) FROM '.table('notification_feed').' WHERE ref=?',['ticket:'.$projectionTicket])->fetchColumn()===2);

nc_owner();support_tickets_staff_change($ticket,0,$reply,'reply',['body'=>'Secret staff reply']);
$clientRow=one('SELECT * FROM '.table('notification_feed').' WHERE ref=? AND audience=? AND recipient_id=?',['ticket:'.$ticket,'client',1]);
nc_ok('Committed staff reply notifies the owning primary client',$clientRow!==null&&$clientRow['title']==='New support reply'&&$clientRow['body']==='Sign in to your account to review the support conversation.');
nc_ok('Staff reply projection excludes reply content',!str_contains(json_encode($clientRow,JSON_THROW_ON_ERROR),'Secret staff reply'));
$count=(int)query('SELECT COUNT(*) FROM '.table('notification_feed').' WHERE ref=?',['ticket:'.$ticket])->fetchColumn();support_tickets_staff_change($ticket,1,$note,'note',['body'=>'Secret private note']);nc_ok('Private ticket note emits no general notification',(int)query('SELECT COUNT(*) FROM '.table('notification_feed').' WHERE ref=?',['ticket:'.$ticket])->fetchColumn()===$count);

nc_owner(2);nc_ok('Ticket-only staff recipient is currently authorized',notification_recipient('staff',2,'ticket:'.$ticket,'support-reply')!==null);query('UPDATE '.table('staff_profiles').' SET permissions=? WHERE user_id=2',[json_encode(['clients.manage'])]);nc_ok('Revoked ticket grant hides the retained notification',notification_recipient('staff',2,'ticket:'.$ticket,'support-reply')===null);nc_owner();
query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=1');nc_ok('Inactive client removes ticket notification access for every audience',notification_recipient('client',1,'ticket:'.$ticket,'support-reply')===null&&notification_recipient('staff',1,'ticket:'.$ticket,'support-reply')===null);query('UPDATE '.table('studio_clients').' SET active=1 WHERE id=1');

$failedTicket=str_repeat('1',32);$failedOpening=str_repeat('2',32);$before=(int)query('SELECT COUNT(*) FROM '.table('notification_feed'))->fetchColumn();
db()->exec("CREATE TRIGGER notifyci_fail_ticket_audit BEFORE INSERT ON notifyci_audit FOR EACH ROW BEGIN IF NEW.action='support_tickets.created' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic ticket audit failure'; END IF; END");$error=null;
try{nc_client();support_tickets_client_create($failedTicket,1,'Rolled back ticket',$failedOpening,'Never projected');}catch(PDOException $e){$error=$e;}finally{db()->exec('DROP TRIGGER notifyci_fail_ticket_audit');}
nc_ok('Failed ticket transaction emits nothing after rollback',$error!==null&&!one('SELECT id FROM '.table('support_tickets').' WHERE id=?',[$failedTicket])&&(int)query('SELECT COUNT(*) FROM '.table('notification_feed'))->fetchColumn()===$before&&!db()->inTransaction());
nc_owner();
