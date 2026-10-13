<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
    if($action==='project_save'){
        $recordId=(int)posted('id',12);$title=posted('title',160);$slug=slugify(posted('slug',160)?:$title);$category=posted('category',80);$summary=posted('summary',2000);$description=posted('description',16000);$challenge=posted('challenge',6000);$solution=posted('solution',6000);$deliver=posted('deliverables',6000);$website=safe_url(posted('website_url',500));$status=posted('status',20);$order=(int)posted('sort_order',6);$demo=isset($_POST['is_demo'])?1:0;$featured=isset($_POST['featured'])?1:0;
        if($title===''||$slug===''||$category===''||$summary===''||!in_array($status,['draft','published'],true))throw new InvalidArgumentException('A title, URL slug, category, summary and valid publication status are required.');
        $old=$recordId?one('SELECT * FROM '.table('projects').' WHERE id=?',[$recordId]):null;if($recordId&&!$old)throw new InvalidArgumentException('Project not found.');
        $collision=one('SELECT id FROM '.table('projects').' WHERE slug=? AND id<>?',[$slug,$recordId]);if($collision)throw new InvalidArgumentException('Another project already uses this URL slug. Choose a different slug.');
        $media=upload_image()?:($old['media_id']??null);$cover=$old['cover']??'';$now=gmdate('Y-m-d H:i:s');
        if(isset($_POST['remove_image'])){$media=null;$cover='';}
        $values=[$slug,$title,$category,$summary,$description,$challenge,$solution,$deliver,$cover,$media,$website,$status,$demo,$featured,$order,$now];
        if($recordId){query('UPDATE '.table('projects').' SET slug=?,title=?,category=?,summary=?,description=?,challenge=?,solution=?,deliverables=?,cover=?,media_id=?,website_url=?,status=?,is_demo=?,featured=?,sort_order=?,updated_at=? WHERE id=?',[...$values,$recordId]);}
        else{query('INSERT INTO '.table('projects').' (slug,title,category,summary,description,challenge,solution,deliverables,cover,media_id,website_url,status,is_demo,featured,sort_order,updated_at,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[...$values,$now]);$recordId=(int)db()->lastInsertId();}
        log_event('project.save','Project '.$recordId);flash('Project saved.');redirect(admin_url('project',['id'=>$recordId]));
    }
    if($action==='project_delete'){query('DELETE FROM '.table('projects').' WHERE id=?',[(int)posted('id',12)]);log_event('project.delete','Project '.posted('id',12));flash('Project deleted.');redirect(admin_url('projects'));}
