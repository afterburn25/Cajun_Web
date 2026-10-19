<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/marketing/pipeline.php';
    if($action==='lead_save'){
        $recordId=(int)posted('id',12);pipeline_transition($recordId,posted('status',20),posted('notes',12000),setting('growth_follow_'.$recordId),$user);log_event('lead.update','Inquiry '.$recordId);flash('Inquiry updated.');redirect(admin_url('lead',['id'=>$recordId]));
    }
    if($action==='lead_delete'){reviews_delete_lead((int)posted('id',12));flash('Inquiry permanently deleted.');redirect(admin_url('leads'));}
    if($action==='lead_retry'){$recordId=(int)posted('id',12);$lead=one('SELECT * FROM '.table('leads').' WHERE id=?',[$recordId]);if(!$lead)throw new InvalidArgumentException('Inquiry not found.');try{$status=notify_lead($lead);}catch(Throwable $e){query('UPDATE '.table('leads').' SET notification_status=\'failed\' WHERE id=?',[$recordId]);throw $e;}query('UPDATE '.table('leads').' SET notification_status=? WHERE id=?',[$status,$recordId]);log_event('lead.notify','Inquiry '.$recordId.' / '.$status);flash($status==='accepted'?'The mail server accepted the notification. Check the recipient inbox.':'Configure email delivery first.',$status==='accepted'?'success':'warning');redirect(admin_url('lead',['id'=>$recordId]));}
    if($action==='leads_export'){
        log_event('leads.export');header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="cajunwebpro-inquiries-'.gmdate('Y-m-d').'.csv"');$out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");$keys=['reference','created_at','status','name','email','company','phone','service','budget','timeline','message','notes'];fputcsv($out,$keys,',','"','');
        $stmt=query('SELECT '.implode(',',$keys).' FROM '.table('leads').' ORDER BY id DESC');while($row=$stmt->fetch()){foreach($row as &$v){$v=(string)$v;if(preg_match('/^[\s]*[=+@\-]/u',$v)||preg_match('/^[\t\r\n]/',$v))$v="'".$v;}unset($v);fputcsv($out,array_values($row),',','"','');}fclose($out);exit;
    }
