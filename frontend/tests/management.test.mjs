import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { Window } from 'happy-dom';
test('connected management reviews publications and accounts without exposing passwords in review', async () => {
 const browser = new Window({ url: 'http://127.0.0.1:8000/workspace#/staff' });
 browser.document.write(await readFile(new URL('../connected.html', import.meta.url), 'utf8'));
 for (const key of ['window','document','location','HTMLElement','Element','HTMLInputElement','HTMLSelectElement','HTMLFormElement','HTMLDialogElement','FormData']) globalThis[key] = key === 'window' ? browser : browser[key];
 Object.defineProperty(globalThis,'navigator',{value:browser.navigator,configurable:true}); browser.scrollTo=()=>{};
 const document=browser.document; const writes=[]; const users=[]; const publications=[];
 const user={id:1,name:'QA Administrator',email:'qa@example.test',role:'Capability fixture',permissions:['users.manage','availability.publish','reports.read']};
 const collection=data=>({data,meta:{total:data.length,current_page:1,last_page:1}});
 const reply=(data,status=200)=>new Response(JSON.stringify(data),{status,headers:{'Content-Type':'application/json'}});
 globalThis.fetch=async(path,options={})=>{
  const route=new URL(path,'http://127.0.0.1:8000').pathname;const method=options.method||'GET'; const body=options.body?JSON.parse(options.body):{};
  if(route==='/sanctum/csrf-cookie')return new Response(null,{status:204});
  if(route==='/api/v1/me')return reply({data:user});
  if(method!=='GET')writes.push({route,body,key:options.headers['Idempotency-Key']});
  if(route==='/api/v1/staff'){if(method==='POST'){users.push({...body,id:2,version:1,active:true});return reply({data:users[0]},201)}return reply({...collection(users),roles:['Red Cross Personnel','System Administrator']})}
  if(route==='/api/v1/publications'){if(method==='POST'){publications.push({id:'snapshot',payload:[],published_at:new Date().toISOString(),valid_until:new Date(Date.now()+60000).toISOString()});return reply({data:publications[0]},201)}return reply({data:publications,locations:['QA Store']})}
  if(route.endsWith('/withdraw')){publications[0].withdrawn_at=new Date().toISOString();return reply({message:'Withdrawn'})}
  if(route==='/api/v1/reports/inventory')return reply({...collection([{blood_type:'O+',component:'QA Component',location:'QA Store',stock_state:'available',units:3}]),scope:'Inventory aggregates',generated_at:new Date().toISOString()});
  throw new Error('Unexpected fixture request '+route);
 };
 const wait=async fn=>{for(let i=0;i<120;i++){if(fn())return;await new Promise(r=>setTimeout(r,5))}assert.ok(fn(),'Expected UI state')};
 const click=s=>{const element=document.querySelector(s);assert.ok(element,s);element.click()};
 const fill=(n,v)=>{document.querySelector('[name="'+n+'"]').value=v};
 const submit=async()=>{document.querySelector('.operation-form').requestSubmit();await new Promise(r=>setTimeout(r,20))};
 await import('../src/workspace.js'); await wait(()=>document.querySelector('[data-op="staff-new"]'));
 click('[data-op="staff-new"]');fill('name','QA Staff');fill('email','staff@example.test');fill('password','Fictional-Password9!');fill('password_confirmation','Fictional-Password9!');fill('current_password','Administrator9!');fill('reason','Fictional account');
 await submit();assert.equal(writes.length,0);assert.ok(!document.querySelector('.ops-review').textContent.includes('Fictional-Password9!'));assert.ok(!document.querySelector('.ops-review').textContent.includes('Administrator9!'));
 await submit();await wait(()=>!document.querySelector('#record-dialog').open);assert.equal(users.length,1);assert.equal(document.querySelector('#record-dialog').innerHTML,'');
 browser.location.hash='#/publications';await wait(()=>document.querySelector('[data-op="publication-new"]'));
 click('[data-op="publication-new"]');fill('public_label','QA Public Center');fill('valid_minutes','20');fill('reason','Fictional publication');document.querySelector('[name="confirmed"]').checked=true;
 await submit();assert.equal(publications.length,0);await submit();await wait(()=>!document.querySelector('#record-dialog').open);assert.equal(publications.length,1);assert.equal(writes.at(-1).body.confirmed,true);
 click('[data-op="publication-withdraw"]');fill('reason','Fictional withdrawal');await submit();await submit();await wait(()=>!document.querySelector('#record-dialog').open);assert.ok(publications[0].withdrawn_at);
 browser.location.hash='#/reports';await wait(()=>document.querySelector('#main').textContent.includes('QA Component'));assert.ok(document.querySelector('[data-op="report-export"]'));
 await browser.close();
});
