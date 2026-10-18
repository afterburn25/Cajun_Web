<?php
declare(strict_types=1);
if (!defined('CWP_SETUP') || !defined('CWP_BOOT')) { http_response_code(404); exit; }
?>
'use strict';
const form=document.getElementById('install-form');
const errorBox=document.getElementById('setup-error');
const secure=<?=json_encode($secure)?>;
const installerBuild=<?=json_encode(CWP_INSTALLER_BUILD)?>;
const csrfToken=document.querySelector('meta[name="csrf-token"]').content;
const freshStart=document.getElementById('fresh-start');
const freshPrefix=freshStart.dataset.freshPrefix;
let originalPrefix=form.elements.db_prefix.value;
let busy=false;
function showError(message){errorBox.textContent=message;errorBox.hidden=false;errorBox.scrollIntoView({block:'nearest'});}
function setBusy(value,label='Installing… Keep this page open.',freezeFields=false){
 busy=value;form.setAttribute('aria-busy',String(value));
 form.querySelectorAll('button').forEach(el=>el.disabled=value||!secure);
 form.querySelectorAll('input').forEach(el=>el.disabled=value&&freezeFields);
 document.getElementById('working').hidden=!value;
 document.getElementById('working-text').textContent=label;
 document.getElementById('install-button').textContent=value&&freezeFields?'Installing…':'Install CajunWebPro ↗';
}
async function send(action,data){
 data.set('action',action);data.set('csrf',csrfToken);
 const controller=new AbortController();const timer=setTimeout(()=>controller.abort(),action==='install'?180000:30000);
 let res,result;
 try{
  const endpoint=window.location.pathname+'?build='+encodeURIComponent(installerBuild);
  res=await fetch(endpoint,{method:'POST',body:data,cache:'no-store',credentials:'same-origin',signal:controller.signal,headers:{Accept:'application/json'}});
  try{result=await res.json();}catch(e){if(e.name==='AbortError')throw e;throw new Error('The server returned an unexpected response. Check admin/index.php before retrying an interrupted installation.');}
 }catch(e){if(e.name==='AbortError')throw new Error(action==='install'?'The request timed out; installation may still be running on the server. Open admin/index.php to check before trying again.':'The server check timed out. All your fields remain available. Recheck after reviewing the hosting error log.');throw e;}
 finally{clearTimeout(timer);}
 if(result.installer_build!==installerBuild)throw new Error('This response came from another installer build. Replace install.php in this website folder and reload. The page must say ONE-PAGE INSTALLER '+installerBuild+'.');
 if(!res.ok&&!result.message)result.message='This request could not complete. Check the host error log.';
 return result;
}
function renderChecks(result){
 if(result.package){
  const p=result.package,list=document.getElementById('package-issues');list.replaceChildren();
  document.getElementById('package-summary').textContent=p.ok?p.passed+' / '+p.expected+' required files verified.':p.issues.length+' missing or mismatched files. Your form remains available.';
  list.hidden=p.ok;
  for(const issue of p.issues){const li=document.createElement('li'),code=document.createElement('code');code.textContent=issue.path;li.append(code,document.createTextNode(' — '+issue.problem));list.append(li);}
 }
 if(!Array.isArray(result.checks))return;
 const holder=document.getElementById('system-checks');holder.replaceChildren();
 for(const c of result.checks){
  const row=document.createElement('div');row.className='check-row '+(c.ok?'passed':'failed');
  const icon=document.createElement('span');icon.className='check-icon';icon.setAttribute('aria-hidden','true');icon.textContent=c.ok?'✓':'!';
  const copy=document.createElement('div'),title=document.createElement('strong'),desc=document.createElement('small');
  title.textContent=c.label;desc.textContent=c.message;copy.append(title,desc);
  if(c.details){const p=document.createElement('small');p.className='path';p.textContent=c.details.path+' · owner '+(c.details.owner??'unknown')+' · group '+(c.details.group??'unknown')+' · '+(c.details.mode??'unknown');copy.append(p);}
  row.append(icon,copy);holder.append(row);
 }
 const ok=result.checks.every(c=>c.ok),summary=document.getElementById('server-summary');
 summary.className='server-summary '+(ok?'ready':'attention');summary.textContent=ok?'Server ready. Complete your details and press Install.':'Some checks need attention. All setup fields remain available on this page.';
 document.getElementById('storage-note').textContent=result.storage_note||'';
 const diagnostics=document.getElementById('storage-diagnostics');diagnostics.hidden=false;
 document.getElementById('worker-note').textContent='PHP worker: '+(result.host_report?.identity||('UID '+(result.php_uid??'unavailable')))+'. Only dedicated application storage is prepared; parent-directory permissions are not changed.';
 const permissionsFailed=result.checks.some(c=>['Website directory','Private storage','Protected media','Server preparation'].includes(c.label)&&!c.ok);
 diagnostics.open=permissionsFailed;
 const list=document.getElementById('storage-attempts');list.replaceChildren();
 for(const a of result.attempts||[]){const li=document.createElement('li');li.textContent=a.path+' — '+a.message;list.append(li);}
}
async function checkServer(){
 if(busy||!secure)return;const data=new FormData();data.set('private_parent',form.elements.private_parent.value);
 if(freshStart.checked)data.set('fresh_start','1');
 errorBox.hidden=true;setBusy(true,'Checking server permissions and preparing a permanent private folder…');
 try{const r=await send('checks',data);renderChecks(r);if(!r.checks&&!r.ok)showError(r.message||'Server checks failed.');}
 catch(e){document.getElementById('server-summary').textContent='Checks could not finish. Your form remains available.';showError(e.message);}
 finally{setBusy(false);}
}
document.getElementById('run-checks').addEventListener('click',checkServer);
document.getElementById('private-parent').addEventListener('input',()=>{document.getElementById('server-summary').textContent='Storage preference changed. It will be rechecked when you install.';});
freshStart.addEventListener('change',()=>{
 const prefix=form.elements.db_prefix;
 if(freshStart.checked){originalPrefix=prefix.value;prefix.value=freshPrefix;}else prefix.value=originalPrefix;
 prefix.readOnly=freshStart.checked;
 document.getElementById('db-result').textContent='Setup choice changed. Test the connection again or press Install to check it.';
 checkServer();
});
document.getElementById('test-database').addEventListener('click',async()=>{
 if(busy||!secure)return;const data=new FormData();
 if(freshStart.checked)data.set('fresh_start','1');
 for(const field of form.querySelectorAll('[name^="db_"]')){if(!field.reportValidity())return;data.set(field.name,field.value);}
 setBusy(true,'Testing your database connection…');errorBox.hidden=true;const box=document.getElementById('db-result');
 try{const r=await send('test_database',data);box.textContent=r.message||'Database test completed.';box.className='inline-result '+(r.ok?'':'bad');}
 catch(e){box.textContent=e.message;box.className='inline-result bad';}finally{setBusy(false);}
});
form.addEventListener('submit',async event=>{
 event.preventDefault();if(busy||!secure)return;if(!form.reportValidity())return;
 if(form.elements.owner_password.value!==form.elements.owner_confirm.value){showError('The administrator passwords do not match.');form.elements.owner_confirm.focus();return;}
 const data=new FormData(form);errorBox.hidden=true;setBusy(true,'Installing… Checking the server and database, creating your website, and removing setup files. Keep this page open.',true);
 try{
  const r=await send('install',data);renderChecks(r);if(!r.ok){showError(r.message||'Installation could not complete. Your entries are still here.');return;}
  form.querySelectorAll('input[type="password"]').forEach(input=>input.value='');
  const log=document.getElementById('completion-log');log.replaceChildren();for(const text of r.events||[]){const li=document.createElement('li');li.textContent=text;log.append(li);}
  const warnings=document.getElementById('completion-warnings');warnings.replaceChildren();for(const text of r.warnings||[]){const p=document.createElement('p');p.className='warning';p.textContent=text;warnings.append(p);}
  document.getElementById('open-dashboard').href=r.admin;document.getElementById('open-website').href=r.site;
  form.hidden=true;const done=document.getElementById('completion');done.hidden=false;done.scrollIntoView({block:'start'});
 }catch(e){showError(e.message+' If the final request was interrupted, open admin/index.php before starting another installation.');}
 finally{setBusy(false);}
});
if(secure)checkServer();else document.getElementById('server-summary').textContent='Enable HTTPS, then reload to run the server checks.';
