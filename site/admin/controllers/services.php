<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
    if($action==='service_save'){
        $recordId=(int)posted('id',12);$title=posted('title',120);$slug=slugify(posted('slug',100)?:$title);$summary=posted('summary',2000);$body=posted('body',16000);$ic=posted('icon',30);$order=(int)posted('sort_order',6);$active=isset($_POST['active'])?1:0;
        if($title===''||$slug===''||$summary===''||!in_array($ic,['monitor','code','bag','diamond','gear','growth','shield','globe','layers'],true))throw new InvalidArgumentException('Add a title, URL slug, summary and valid icon.');
        if(one('SELECT id FROM '.table('services').' WHERE slug=? AND id<>?',[$slug,$recordId]))throw new InvalidArgumentException('Another service already uses this URL slug.');
        $data=[$slug,$title,$summary,$body,$ic,$order,$active];if($recordId)query('UPDATE '.table('services').' SET slug=?,title=?,summary=?,body=?,icon=?,sort_order=?,active=? WHERE id=?',[...$data,$recordId]);else{query('INSERT INTO '.table('services').' (slug,title,summary,body,icon,sort_order,active) VALUES (?,?,?,?,?,?,?)',$data);$recordId=(int)db()->lastInsertId();}
        log_event('service.save','Service '.$recordId);flash('Service saved.');redirect(admin_url('services'));
    }
    if($action==='service_delete'){query('DELETE FROM '.table('services').' WHERE id=?',[(int)posted('id',12)]);log_event('service.delete','Service '.posted('id',12));flash('Service deleted.');redirect(admin_url('services'));}
