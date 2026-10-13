<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function delivery_admin_action(string $action): never {
    if($action==='delivery_setup'){
        if(posted('confirm',1)!=='1')throw new InvalidArgumentException('Confirm that you saved a current database and private-storage backup.');
        delivery_migrate(posted('owner_password',72));flash('Website deliveries enabled.');redirect(admin_url('deliveries'));
    }
    if($action==='delivery_upload'){
        $file=$_FILES['archive']??null;if(!is_array($file)||($file['error']??-1)!==UPLOAD_ERR_OK||!is_string($file['tmp_name']??null))throw new InvalidArgumentException('Choose a ZIP within your hosting upload limit. If the upload is too large, ask your host to raise the limit.');
        $id=delivery_upload(gint($_POST,'job_id',1,2147483647),$_POST,$file['tmp_name'],posted('request_key',64));flash('Private draft uploaded. Review the files before publishing.');redirect(admin_url('deliveries',['release'=>$id]));
    }
    if($action==='delivery_publish'){
        if(posted('confirm',1)!=='1'||posted('rights',1)!=='1')throw new InvalidArgumentException('Confirm the client, contents and transfer rights after reviewing the archive.');
        $id=posted('release_id',32);delivery_publish($id,posted('review_hash',64),posted('owner_password',72));flash('Website release published to this client.');redirect(admin_url('deliveries',['release'=>$id]));
    }
    throw new InvalidArgumentException('Unknown delivery action.');
}
function delivery_list(int $client=0,int $job=0,bool $admin=false): void {
    $where=[];$values=[];if(!$admin){$where[]="r.status='published'";$where[]='r.client_id=?';$values[]=$client;}
    if($job){$where[]='r.job_id=?';$values[]=$job;}
    $base=$where;$baseValues=$values;$before=is_string($_GET['before']??null)?$_GET['before']:'';
    if(preg_match('/^[a-f0-9]{32}$/D',$before)){
        $cursor=one('SELECT r.created_at,r.id FROM '.table('website_releases').' r WHERE '.implode(' AND ',[...$base,'r.id=?']),[...$baseValues,$before]);
        if($cursor){$where[]='(r.created_at<? OR (r.created_at=? AND r.id<?))';array_push($values,$cursor['created_at'],$cursor['created_at'],$cursor['id']);}
    }
    $rows=all('SELECT r.*,j.title AS project_title,(SELECT MAX(n.release_no) FROM '.table('website_releases')." n WHERE n.job_id=r.job_id AND n.status='published') AS latest_no FROM ".table('website_releases').' r JOIN '.table('studio_jobs').' j ON j.id=r.job_id AND j.client_id=r.client_id'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY r.created_at DESC,r.id DESC LIMIT 51',$values);
    $more=count($rows)>50;foreach(array_slice($rows,0,50) as $r){
        $url=$admin?admin_url('deliveries',['release'=>$r['id']]):studio_url(['release'=>$r['id']]);
        echo '<article class="workspace-version"><span class="workspace-status">'.esc($r['status']==='draft'?'Private draft':((int)$r['release_no']===(int)$r['latest_no']?'Latest published release':'Earlier published release')).'</span><h3><a href="'.esc($url).'">'.esc($r['version'].' · '.$r['title']).'</a></h3><p>'.esc($r['project_title']).' · '.esc(delivery_scopes()[$r['scope']]??'Files only').'</p><p>'.number_format($r['bytes']/1048576,2).' MiB · '.esc($r['published_at']??$r['created_at']).' UTC</p></article>';
    }
    if(!$rows)echo '<p>No website releases here yet. Published packages will include version notes, installation instructions and a download.</p>';
    if($more){$params=['before'=>$rows[49]['id']];if($job)$params[$admin?'job_id':'job']=$job;if(!$admin)$params['downloads']=1;$url=$admin?admin_url('deliveries',$params):studio_url($params);echo '<p><a class="btn outline" href="'.esc($url).'">Earlier releases →</a></p>';}
}
function delivery_detail(array $r,bool $admin): void {
    $manifest=json_decode($r['manifest'],true,512,JSON_THROW_ON_ERROR);$job=studio_job((int)$r['job_id']);$canDownload=$admin||!support_current()||support_can('support.download');$available=true;
    try{$fh=delivery_open($r);fclose($fh);}catch(RuntimeException){$available=false;}
    echo '<article class="workspace-card delivery-detail"><span class="eyebrow">'.esc($job['title']).' / '.esc($r['status']).'</span><h2>'.esc($r['version'].' · '.$r['title']).'</h2><p><strong>'.esc(delivery_scopes()[$r['scope']]).'</strong></p><div class="prose">'.nl2br(esc($r['notes'])).'</div><p>'.number_format($r['bytes']/1048576,2).' MiB ZIP · '.count($manifest['files']).' files · '.number_format($manifest['expanded_bytes']/1048576,2).' MiB expanded</p><p class="delivery-hash">SHA-256: <code>'.esc($r['sha256']).'</code></p>';
    if($r['source_revision']!=='')echo '<p class="delivery-hash">Source revision: <code>'.esc($r['source_revision']).'</code></p>';
    echo '<p>'.($r['published_at']?'Published '.esc($r['published_at']):'Uploaded '.esc($r['created_at'])).' UTC</p>';
    if(!$available)echo '<div class="notice error">This file is temporarily unavailable or failed its integrity check. Contact the studio.</div>';
    elseif($canDownload){$url=$admin?admin_url('deliveries',['download_release'=>$r['id']]):studio_url(['download_release'=>$r['id']]);echo '<p><a class="btn gold" href="'.esc($url).'">'.($r['status']==='draft'?'Download draft for review':'Download website ZIP').' ↓</a></p>';}
    else echo '<p>Support download permission is required to retrieve this package.</p>';
    echo '<p class="hint">Keep a copy of this ZIP and checksum. Read HANDOVER.md and LICENSES.md before installation. Files-only packages exclude databases and separately licensed services.</p><details><summary>File manifest ('.count($manifest['files']).')</summary><ul class="delivery-manifest">';
    foreach($manifest['files'] as $file)echo '<li><strong>'.esc($file['path']).'</strong> · '.number_format($file['bytes']).' bytes<br><code>'.esc($file['sha256']).'</code></li>';
    echo '</ul></details></article>';
    if($admin&&$r['status']==='draft'){
        if(staff_can('staff.manage')){echo '<section class="workspace-card"><h2>Owner publication review</h2><p>Download and inspect this exact draft. Automated checks cannot prove all secrets are removed or all licenses are transferable. Published packages cannot be edited or replaced; corrections require a new version.</p><form method="post">';admin_action('delivery_publish');studio_hidden('release_id',$r['id']);studio_hidden('review_hash',delivery_review_hash($r));admin_check('confirm','I reviewed the manifest and ZIP: it belongs to this client and contains no private credentials, agency data or another client’s files.',false);admin_check('rights','I verified the handover instructions, scope and rights to transfer the included files and dependencies.',false);admin_input('owner_password','Current owner password','','password','required autocomplete="current-password"');echo '<button class="btn gold">Publish to client</button></form></section>';}
        else echo '<p>An owner must review and publish this draft.</p>';
    }
}
function delivery_admin_render(array $user,string $error): never {
    if(isset($_GET['download_release']))delivery_download(is_string($_GET['download_release'])?$_GET['download_release']:'',true);
    admin_head('Website deliveries','deliveries',$user);if($error)echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    try{
        if(!delivery_ready()){
            echo '<section class="workspace-card"><h2>Enable website deliveries</h2><p>Add private, versioned website ZIP downloads to the client workspace. Existing projects, invoices and files are preserved. Private storage must be outside the web document root.</p>';
            if(staff_can('staff.manage')){echo '<form method="post">';admin_action('delivery_setup');admin_check('confirm','I saved a current database and private-storage backup.',false);admin_input('owner_password','Current owner password','','password','required autocomplete="current-password"');echo '<button class="btn gold">Enable website deliveries</button></form>';}
            else echo '<p>Ask the owner to enable this feature.</p>';echo '</section>';
        }else{
            delivery_require();$id=is_string($_GET['release']??null)?$_GET['release']:'';$job=isset($_GET['job_id'])?gint($_GET,'job_id',1,2147483647):0;
            echo '<p><a href="'.esc(admin_url('deliveries')).'">All website releases</a> · <a href="'.esc(admin_url('studio-clients')).'">Client workspace</a></p>';
            if($id!=='')delivery_detail(delivery_record($id),true);
            else{
                echo '<section class="workspace-card"><h2>Prepare a private draft</h2><p>Upload a sanitized client package with HANDOVER.md and LICENSES.md at its root. Choose static only when the site works without a database. Uploads are reviewed before clients can see them.</p><p class="hint">Maximum ZIP: 50 MiB; your host currently allows '.esc(ini_get('upload_max_filesize')).' per file / '.esc(ini_get('post_max_size')).' per request. Expanded limit: 200 MiB; 1000 entries; 32 MiB per file. Storage: 250 MiB/project and 1 GiB/site. Nested archives, databases, logs, private configurations and recognizable credentials are rejected.</p>';
                if(!class_exists('ZipArchive'))echo '<div class="notice warning">Ask your host to enable the PHP ZIP extension for uploads.</div>';
                echo '<form method="post" enctype="multipart/form-data">';admin_action('delivery_upload');studio_hidden('request_key',bin2hex(random_bytes(32)));admin_select('job_id','Client project',array_column(all('SELECT j.id,CONCAT(c.name,\' — \',j.title) AS label FROM '.table('studio_jobs').' j JOIN '.table('studio_clients').' c ON c.id=j.client_id WHERE c.active=1 ORDER BY c.name,j.id DESC'),'label','id'),$job);admin_input('version','Version (for example 1.0.0)','','text','required maxlength="40" pattern="[a-z0-9][a-z0-9._-]{0,39}"');admin_input('title','Release title','','text','required maxlength="160"');admin_select('scope','Package scope',delivery_scopes(),'static');admin_textarea('notes','Release notes and installation guidance','',5,5000);admin_input('source_revision','Source Git hash (optional)','','text','maxlength="64"');echo '<label>Prepared website ZIP<input type="file" name="archive" accept=".zip,application/zip" required></label><button class="btn gold">Upload private draft</button></form></section><h2>Release history</h2>';delivery_list(0,$job,true);
            }
        }
    }catch(PDOException $e){echo '<div class="notice error">Request could not be loaded. Reference: '.esc(report_error($e)).'</div>';}catch(InvalidArgumentException|RuntimeException $e){echo '<div class="notice error">'.esc($e->getMessage()).'</div>';}
    admin_foot();exit;
}
function delivery_portal_render(): never {
    if(!($client=studio_client()))fail_page('Sign in to view website releases.',403);
    $id=is_string($_GET['release']??null)?$_GET['release']:'';$job=0;$r=null;
    try{
        if(!delivery_ready())throw new RuntimeException('Website deliveries unavailable.');
        if($id!==''){$r=delivery_client_record($id);$job=(int)$r['job_id'];}elseif(isset($_GET['job'])){$job=gint($_GET,'job',1,2147483647);studio_job($job,(int)$client['id']);}
        if(support_current())support_record($r?'release.view':'release.list',$r?'release':'client',$r['id']??(string)$client['id']);
    }catch(InvalidArgumentException|RuntimeException){if(support_current())support_record('release.view','release',preg_match('/^[a-f0-9]{32}$/D',$id)?$id:'','denied');fail_page('Website release not found or access denied.',404);}
    site_head('Website downloads','portal');support_banner();echo '<section class="section"><div class="container"><span class="eyebrow">YOUR WEBSITE. YOUR COPY.</span><h1>Website downloads</h1><div class="button-row"><a class="btn outline" href="'.esc(studio_url()).'">All projects</a><a class="btn outline" href="'.esc(studio_url(['downloads'=>1])).'">All website releases</a>';
    if($job)echo '<a class="btn outline" href="'.esc(studio_url(['job'=>$job])).'">Return to project</a>';echo '</div>';
    if($r)delivery_detail($r,false);else delivery_list((int)$client['id'],$job);
    echo '</div></section>';site_foot(false);exit;
}
