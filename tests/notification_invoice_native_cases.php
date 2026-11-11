<?php
/** Manual issued-invoice projection inside the notification native fixture. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||!function_exists('nc_ok'))exit(2);

$now=gmdate('Y-m-d H:i:s');query('INSERT INTO '.table('studio_invoices').' (job_id,reference,title,amount_cents,instructions,created_at) VALUES (1,?,?,?,?,?)',['PRIVATE-NOTIFY-1','Confidential invoice title',12345,'Secret invoice instructions',$now]);$invoice=(int)db()->lastInsertId();notification_invoice_issued(1,$invoice);
$rows=all('SELECT * FROM '.table('notification_feed').' WHERE ref=?',['invoice:'.$invoice]);$payload=$rows?($rows[0]['title']."\n".$rows[0]['body']):'';
nc_ok('Committed manual invoice projects one generic client notification',count($rows)===1&&$rows[0]['audience']==='client'&&(int)$rows[0]['recipient_id']===1&&$rows[0]['title']==='New invoice available');
nc_ok('Invoice notification excludes reference title amount and instructions',!str_contains($payload,'PRIVATE-NOTIFY-1')&&!str_contains($payload,'Confidential invoice title')&&!str_contains($payload,'12345')&&!str_contains($payload,'Secret invoice instructions'));
notification_invoice_issued(1,$invoice);nc_ok('Issued invoice replay is deterministic',(int)query('SELECT COUNT(*) FROM '.table('notification_feed').' WHERE ref=?',['invoice:'.$invoice])->fetchColumn()===1);
notification_invoice_issued(2,$invoice);nc_ok('Invoice projection requires the committed project binding',(int)query('SELECT COUNT(*) FROM '.table('notification_feed').' WHERE ref=?',['invoice:'.$invoice])->fetchColumn()===1);
query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=1');nc_ok('Inactive owner cannot use a retained invoice notification',notification_recipient('client',1,'invoice:'.$invoice,'billing')===null);query('UPDATE '.table('studio_clients').' SET active=1 WHERE id=1');
query('UPDATE '.table('studio_jobs').' SET client_id=2 WHERE id=1');nc_ok('Transferred project hides the retained invoice notification from its prior owner',notification_recipient('client',1,'invoice:'.$invoice,'billing')===null);query('UPDATE '.table('studio_jobs').' SET client_id=1 WHERE id=1');
$paidReference='PRIVATE-NOTIFY-PAID';query('INSERT INTO '.table('studio_invoices')." (job_id,reference,title,amount_cents,status,instructions,created_at,paid_at) VALUES (1,?,?,?,'paid',?,?,?)",[$paidReference,'Already paid invoice',100,'Private paid instructions',$now,$now]);$paid=(int)db()->lastInsertId();notification_invoice_issued(1,$paid);nc_ok('Issued event adapter refuses an already-paid invoice',!one('SELECT id FROM '.table('notification_feed').' WHERE ref=?',['invoice:'.$paid]));
