<?php
/** Current-resource project event projection inside the notification native fixture. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||!function_exists('nc_ok'))exit(2);

notification_project_created(1);$created=one('SELECT * FROM '.table('notification_feed')." WHERE audience='client' AND recipient_id=1 AND ref='job:1' AND title='Project workspace opened'");
nc_ok('Committed project creation projects a generic client notification',$created!==null&&$created['body']==='Sign in to review your project workspace.');$count=(int)query('SELECT COUNT(*) FROM '.table('notification_feed')." WHERE ref='job:1' AND title='Project workspace opened'")->fetchColumn();notification_project_created(1);nc_ok('Project creation replay is deterministic',(int)query('SELECT COUNT(*) FROM '.table('notification_feed')." WHERE ref='job:1' AND title='Project workspace opened'")->fetchColumn()===$count);

$now=gmdate('Y-m-d H:i:s');query('INSERT INTO '.table('studio_versions')." (job_id,kind,version_no,title,body,preview_url,decision,feedback,created_at) VALUES (1,'design',1,'Confidential review title','Secret review body','','pending','',?)",[$now]);$version=(int)db()->lastInsertId();notification_project_version_ready(1,$version);$review=one('SELECT * FROM '.table('notification_feed')." WHERE audience='client' AND recipient_id=1 AND ref='job:1' AND title='Project review ready'");
nc_ok('Pending project version projects a generic client review notice',$review!==null&&$review['kind']==='approval-request'&&!str_contains(json_encode($review,JSON_THROW_ON_ERROR),'Confidential review title')&&!str_contains(json_encode($review,JSON_THROW_ON_ERROR),'Secret review body'));

$staffFile=str_repeat('3',32);$clientFile=str_repeat('4',32);$contactFile=str_repeat('5',32);$supportFile=str_repeat('6',32);foreach([[$staffFile,'admin','Staff secret.pdf'],[$clientFile,'client','Client secret.pdf'],[$contactFile,'contact','Contact secret.pdf'],[$supportFile,'admin','Support secret.pdf']] as [$file,$actor,$name])query('INSERT INTO '.table('studio_files').' (id,job_id,author_type,original_name,stored_name,mime,bytes,sha256,created_at) VALUES (?,1,?,?,?,\'application/pdf\',4,?,?)',[$file,$actor,$name,'notification-'.$file.'.php',str_repeat('a',64),$now]);
notification_project_file(1,$staffFile,'staff');notification_project_file(1,$clientFile,'client');notification_project_file(1,$contactFile,'contact');notification_project_file(1,$supportFile,'support');
$fileRows=all('SELECT * FROM '.table('notification_feed')." WHERE ref='job:1' AND title IN ('New project file','New client project file') ORDER BY audience,recipient_id,id");$encoded=json_encode($fileRows,JSON_THROW_ON_ERROR);
nc_ok('Staff file notifies only the owning primary client',count(array_filter($fileRows,static fn($row)=>$row['audience']==='client'&&$row['title']==='New project file'))===1);
nc_ok('Client and contact files notify current project staff',count(array_filter($fileRows,static fn($row)=>$row['audience']==='staff'&&$row['title']==='New client project file'))===4);
nc_ok('Project file notifications exclude filenames and support-session uploads',!str_contains($encoded,'Staff secret.pdf')&&!str_contains($encoded,'Client secret.pdf')&&!str_contains($encoded,'Contact secret.pdf')&&!str_contains($encoded,'Support secret.pdf')&&count($fileRows)===5);
