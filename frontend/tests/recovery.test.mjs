import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { Window } from 'happy-dom';
test('recovery explains disabled mail and submits a token without retaining it in the address bar', async () => {
 const browser=new Window({url:'http://127.0.0.1:8000/recovery'});browser.document.write(await readFile(new URL('../recovery.html',import.meta.url),'utf8'));
 for(const key of ['window','document','location','history','HTMLElement','Element','HTMLInputElement','HTMLFormElement','FormData'])globalThis[key]=key==='window'?browser:browser[key];
 const document=browser.document;const writes=[];
 globalThis.fetch=async(path,options={})=>{if(path==='/sanctum/csrf-cookie')return new Response(null,{status:204});if(options.method==='POST'){writes.push(JSON.parse(options.body));return new Response(JSON.stringify({message:'Password updated. Sign in with your new password.'}),{status:200})}return new Response(JSON.stringify({email_recovery_enabled:false}),{status:200})};
 const wait=async fn=>{for(let i=0;i<100;i++){if(fn())return;await new Promise(r=>setTimeout(r,5))}assert.ok(fn())};
 await import('../src/recovery.js?disabled');await wait(()=>document.querySelector('#main').textContent.includes('not configured'));assert.equal(document.querySelector('form'),null);assert.equal(writes.length,0);
 browser.document.body.innerHTML='<main id="main" tabindex="-1"></main>';browser.location.hash='#token=fictional-token&email=qa%40example.test';await import('../src/recovery.js?token');await wait(()=>document.querySelector('.recovery-form'));
 assert.equal(browser.location.hash,'');document.querySelector('[name="password"]').value='Fictional-Password9!';document.querySelector('[name="password_confirmation"]').value='Fictional-Password9!';document.querySelector('form').requestSubmit();await wait(()=>document.querySelector('#recovery-result'));
 assert.equal(writes.at(-1).token,'fictional-token');assert.equal(document.querySelector('[type="password"]'),null);await browser.close();
});
