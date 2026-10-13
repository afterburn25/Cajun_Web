'use strict';
const sidebar=document.querySelector('.admin-sidebar');const btn=document.querySelector('.sidebar-toggle');
if(sidebar&&btn){function close(){sidebar.classList.remove('open');btn.setAttribute('aria-expanded','false');}btn.addEventListener('click',()=>{const open=sidebar.classList.toggle('open');btn.setAttribute('aria-expanded',String(open));});document.addEventListener('click',e=>{if(!sidebar.contains(e.target)&&!btn.contains(e.target))close();});document.addEventListener('keydown',e=>{if(e.key==='Escape'){close();btn.focus();}});}
