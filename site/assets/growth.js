'use strict';
(() => {
 const form=document.querySelector('[data-growth-form]');
 if(form) form.addEventListener('submit',async event=>{
  event.preventDefault();const calc=event.submitter?.hasAttribute('data-estimate');
  const err=document.querySelector('[data-growth-error]'),success=document.querySelector('[data-growth-success]');
  const data=new FormData(form);data.set('action',calc?'calculate':'growth_submit');
  const buttons=[...form.querySelectorAll('button')];buttons.forEach(b=>b.disabled=true);err.hidden=true;
  try{const response=await fetch(form.action,{method:'POST',body:data,credentials:'same-origin',headers:{Accept:'application/json'}});const result=await response.json();if(!response.ok||!result.ok)throw new Error(result.message||'Your request could not be saved.');
   if(calc){document.querySelector('[data-estimate-label]').textContent=result.estimate.label;document.querySelector('[data-estimate-note]').textContent=result.estimate.note;}
   else{success.textContent=result.result.message+' Reference: '+result.result.reference;success.hidden=false;form.hidden=true;success.scrollIntoView({block:'center',behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth'});}
  }catch(error){err.textContent=error.message==='Failed to fetch'?'Connection interrupted. Your entries are preserved. Retry; duplicate submissions use the same reference.':error.message;err.hidden=false;err.scrollIntoView({block:'center'});}
  finally{buttons.forEach(b=>b.disabled=false);}
 });
 document.querySelectorAll('[data-compare-slider]').forEach(slider=>{const box=slider.closest('.portfolio-compare');slider.addEventListener('input',()=>box.style.setProperty('--split',slider.value+'%'));});
 document.querySelectorAll('[data-device]').forEach(btn=>btn.addEventListener('click',()=>{const root=btn.closest('.portfolio-tools');const image=root.querySelector('[data-device-image]');image.src=btn.dataset.src;image.alt=btn.dataset.alt;root.querySelectorAll('[data-device]').forEach(b=>b.setAttribute('aria-pressed',String(b===btn)));}));
})();
