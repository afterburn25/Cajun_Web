'use strict';
const sidebar=document.querySelector('.admin-sidebar');
const btn=document.querySelector('.sidebar-toggle');
const navigation=document.querySelector('[data-admin-navigation]');

if(navigation){
 const groups=Array.from(navigation.querySelectorAll('[data-nav-group]'));
 const key='cwp:admin-navigation:v1:'+location.pathname+':'+navigation.dataset.navAccount;
 let saved={};
 try{const value=JSON.parse(localStorage.getItem(key)||'{}');if(value&&typeof value==='object'&&!Array.isArray(value))saved=value;}catch(e){/* Native disclosures remain usable when storage is unavailable. */}
 for(const group of groups){
  const name=group.dataset.navGroup;
  if(!group.hasAttribute('data-active')&&typeof saved[name]==='boolean')group.open=saved[name];
  group.addEventListener('toggle',()=>{
   const state={};for(const item of groups)state[item.dataset.navGroup]=item.open;
   try{localStorage.setItem(key,JSON.stringify(state));}catch(e){/* Preferences are optional. */}
  });
 }
}

if(sidebar&&btn){
 const mobile=matchMedia('(max-width:760px)');
 const closeButton=sidebar.querySelector('[data-admin-nav-close]');
 function sync(){
  const open=mobile.matches&&sidebar.classList.contains('open');
  sidebar.inert=mobile.matches&&!open;
  btn.setAttribute('aria-expanded',String(open));
  document.body.classList.toggle('admin-nav-open',open);
 }
 function close(restoreFocus=false){
  sidebar.classList.remove('open');sync();
  if(restoreFocus)btn.focus();
 }
 btn.addEventListener('click',()=>{
  if(sidebar.classList.contains('open')){close(true);return;}
  sidebar.classList.add('open');sync();
  const current=sidebar.querySelector('a[aria-current="page"]');
  const closedGroup=current?.closest('details:not([open])');
  const target=closedGroup?.querySelector('summary')||current||sidebar.querySelector('nav summary,nav a');
  if(target)target.focus();
 });
 if(closeButton)closeButton.addEventListener('click',()=>close(true));
 document.addEventListener('click',e=>{if(!sidebar.contains(e.target)&&!btn.contains(e.target))close();});
 document.addEventListener('keydown',e=>{
  if(!mobile.matches||!sidebar.classList.contains('open'))return;
  if(e.key==='Escape'){e.preventDefault();close(true);return;}
  if(e.key!=='Tab')return;
  const focusable=Array.from(sidebar.querySelectorAll('a[href],button,summary')).filter(el=>el.getClientRects().length&&getComputedStyle(el).visibility!=='hidden');
  const first=focusable[0],last=focusable[focusable.length-1];
  if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}
  else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}
 });
 mobile.addEventListener('change',()=>close());
 sync();
}
