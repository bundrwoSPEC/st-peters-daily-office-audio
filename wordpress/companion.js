// Included inside the existing Daily Office player closure.
const companion=document.createElement('section');
companion.id='qp-companion';
companion.innerHTML=`<details id="qp-study"><summary>Reflection and guide to this office</summary><p class="qp-note">AI-generated study commentary · separate from the Book of Common Prayer liturgy.</p><div id="qp-study-content" aria-live="polite"></div></details>
<details id="qp-share-panel"><summary>Share or print this prayer</summary><div class="qp-share-options"><label><input id="qp-export-prayer" type="checkbox" checked> Prayer service</label><label><input id="qp-export-study" type="checkbox" checked> Reflection and section explanations</label><label><input id="qp-export-notes" type="checkbox" checked> Ruled lines for notes and thoughts</label></div><div class="qp-actions"><button type="button" id="qp-share">Share link</button><button type="button" id="qp-copy">Copy link</button><button type="button" id="qp-print">Print / Save as PDF</button></div><p class="qp-note">The link opens this date, service, and rite. Print or save a PDF to share the prayer and explanation together.</p><p id="qp-share-status" role="status"></p></details>`;
reading.appendChild(companion);
const companionStyle=document.createElement('style');
companionStyle.textContent=`#stp-office #qp-companion{margin-top:18px;color:#21495d;background:#fffdf8;border:2px solid #c9a14a;border-radius:7px;padding:14px 18px;font:15px/1.7 Georgia,serif}#stp-office #qp-companion details+details{border-top:1px solid #c9a14a;margin-top:12px;padding-top:12px}#stp-office #qp-companion summary{cursor:pointer;font-weight:bold;min-height:36px}#stp-office #qp-companion h3{font:normal 20px/1.4 Georgia,serif;color:#21495d;margin:18px 0 5px}#stp-office #qp-companion p{margin:8px 0}#stp-office #qp-companion button{background:#21495d;color:#fffdf8;border:1px solid #c9a14a;border-radius:5px;padding:10px 14px;font:14px/1.4 Georgia,serif;min-height:44px}#stp-office .qp-share-options{display:grid;gap:8px;margin:12px 0}#stp-office .qp-share-options label{display:flex;align-items:center;gap:9px}#stp-office .qp-share-options input{width:18px;height:18px;margin:0;accent-color:#21495d}`;
section.appendChild(companionStyle);
const options=document.createElement('div');
options.innerHTML='<label class="qp-check"><input id="qp-read-intro" type="checkbox" checked> Read the introduction</label><label class="qp-check"><input id="qp-read-study" type="checkbox"> Read the reflection and explanations after prayer</label><p class="qp-note">The introduction names the service, date, season or special day, and rite. Commentary begins only after the office has concluded. These options use the prepared Michael or Heart recording.</p><p class="qp-note" id="qp-companion-audio-status" role="status"></p>';
settings.querySelector('.qp-options').insertBefore(options,settings.querySelector('.qp-actions'));
let companionStopped=false;
for(const id of ['qp-read-intro','qp-read-study']){
 try{const saved=localStorage.getItem(id);if(saved!==null)$(id).checked=saved==='true';}catch(e){}
 $(id).onchange=()=>{try{localStorage.setItem(id,String($(id).checked));}catch(e){}};
}
function companionClear(){companionStopped=false;$('qp-study-content').replaceChildren();$('qp-share-status').textContent='';}
function companionRender(){
 const target=$('qp-study-content');target.replaceChildren();
 if(!manifest)return;
 const study=manifest.study;
 const available=!!manifest.timing;
 $('qp-read-intro').disabled=!available;$('qp-read-study').disabled=!available;
 $('qp-companion-audio-status').textContent=available?'Introduction and commentary are ready for this recording.':'Introduction and commentary are not yet available for this recording.';
 $('qp-export-study').disabled=!study;$('qp-export-study').checked=!!study;
 if(study){
  const p=document.createElement('p');p.textContent=study.summary;target.appendChild(p);
  for(const item of study.sections||[]){const h=document.createElement('h3'),p=document.createElement('p');h.textContent=item.title;p.textContent=item.text;target.append(h,p);}
 }else{const p=document.createElement('p');p.textContent='The reflection and section guide are not yet available for this selection. The prayer text remains available. Use Reload saved recording in Prayer settings to check again.';target.appendChild(p);}
}
function companionTitle(){const s=selection();const name=s.office==='compline'?'Night Prayer (Compline)':names[s.office];const day=new Date(s.date+'T12:00:00Z').toLocaleDateString('en-US',{weekday:'long',year:'numeric',month:'long',day:'numeric',timeZone:'UTC'});const rite=['morning-prayer','evening-prayer'].includes(s.office)?(s.rite==='Rite-I'?'Rite I':'Rite II'):'Book of Common Prayer';return name+' · '+day+' · '+rite;}
function companionLink(){const u=new URL(location.pathname,location.origin),s=selection();for(const k of ['date','office','rite'])u.searchParams.set(k,s[k]);if($('qp-export-study').checked)u.searchParams.set('study','1');return u.href;}
const escapePrint=value=>String(value).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
$('qp-copy').onclick=async()=>{try{await navigator.clipboard.writeText(companionLink());$('qp-share-status').textContent='Link copied.';}catch(e){$('qp-share-status').textContent='Copy this link: '+companionLink();}};
$('qp-share').onclick=async()=>{if(!navigator.share){$('qp-copy').click();return;}try{await navigator.share({title:companionTitle(),text:'Join me in the Daily Office at St. Peter’s Episcopal Church.',url:companionLink()});$('qp-share-status').textContent='Sharing finished.';}catch(e){if(e.name!=='AbortError')$('qp-share-status').textContent='Sharing could not open. Use Copy link instead.';}};
$('qp-print').onclick=()=>{
 if(!manifest){$('qp-share-status').textContent='Please wait for the selected prayer text to load.';return;}
 if(!$('qp-export-prayer').checked&&!$('qp-export-study').checked&&!$('qp-export-notes').checked){$('qp-share-status').textContent='Choose at least one item to print.';return;}
 if($('qp-export-study').checked&&!manifest.study){$('qp-share-status').textContent='The reflection is still being prepared. Uncheck reflection to print the prayer now, or reload the saved recording later.';return;}
 const win=window.open('','_blank');if(!win){$('qp-share-status').textContent='Allow this site to open the print preview, then try again.';return;}
 const e=escapePrint,s=selection();let body='';
 if($('qp-export-prayer').checked){const first=manifest.segments?manifest.segments.office_first:0,last=manifest.segments?manifest.segments.office_last:manifest.blocks.length-1;body+='<section><h2>Daily Office</h2>'+manifest.blocks.slice(first,last+1).map(b=>'<'+(b.kind==='heading'?'h3':'p')+'>'+e(b.text)+'</'+(b.kind==='heading'?'h3':'p')+'>').join('')+'</section>';}
 if($('qp-export-study').checked){const study=manifest.study;body+='<section class="study"><h2>Reflection and guide to this office</h2><p class="small">'+e(study.label)+'</p><p>'+e(study.summary)+'</p>'+(study.sections||[]).map(x=>'<h3>'+e(x.title)+'</h3><p>'+e(x.text)+'</p>').join('')+'</section>';}
 if($('qp-export-notes').checked)body+='<section class="notes"><h2>My Notes and Thoughts</h2><p>What stood out? What might I carry into today?</p>'+Array.from({length:14},()=>'<div class="line"></div>').join('')+'</section>';
 const shields=Array.from(section.querySelectorAll('.qp-shield-link img'));
 const season=$('office-season').textContent,observance=$('office-observance').textContent;
 win.document.open();win.document.write('<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'+e(companionTitle())+'</title><style>@page{size:letter;margin:.65in}*{box-sizing:border-box}body{max-width:760px;margin:24px auto;padding:0 20px;font:12pt/1.55 Georgia,serif;color:#142f3c}header{display:flex;align-items:center;justify-content:space-between;gap:16px;border-top:4px solid #21495d;border-bottom:2px solid #c9a14a;padding:14px 0;text-align:center}header img{width:48px;height:60px;object-fit:contain}header div{flex:1}h1{font-size:20pt;margin:0}h2{font-size:17pt;border-bottom:1px solid #c9a14a;margin-top:24px}h3{font-size:13pt;margin-bottom:4px;break-after:avoid}p{margin:7px 0;orphans:3;widows:3}.small,footer{font-size:9pt}section.study{break-before:page}.notes{break-before:page}.line{height:.35in;border-bottom:1px solid #aaa;break-inside:avoid}footer{border-top:1px solid #c9a14a;margin-top:20px;padding-top:8px;overflow-wrap:anywhere}.print-controls{text-align:center;padding:20px}.print-controls button{padding:12px 18px;background:#21495d;color:white;border:1px solid #c9a14a;font:16px Georgia}@media print{body{margin:0;padding:0;max-width:none}.print-controls{display:none}}</style></head><body><div class="print-controls"><button onclick="window.print()">Print / Save as PDF</button></div><header>'+(shields[0]?'<img src="'+e(shields[0].src)+'" alt="St. Peter’s shield">':'')+'<div><h1>St. Peter’s Episcopal Church</h1><div>Brenham, Texas</div></div>'+(shields[1]?'<img src="'+e(shields[1].src)+'" alt="Episcopal shield">':'')+'</header><p><strong>'+e(companionTitle())+'</strong><br>'+e([season,observance].filter(Boolean).join(' · '))+'</p>'+body+'<footer>'+e(manifest.attribution||'Book of Common Prayer (1979). Scripture supplied by Gracelight.')+'<br>'+e(companionLink())+'</footer></body></html>');win.document.close();
 $('qp-share-status').textContent='Print preview opened. Use its Print / Save as PDF button.';
};
audio.addEventListener('play',()=>{
 if(!manifest||!manifest.timing)return;
 if(companionStopped){audio.currentTime=$('qp-read-intro').checked?0:manifest.timing.office_start;companionStopped=false;}
 if(!$('qp-read-intro').checked&&audio.currentTime<manifest.timing.office_start)audio.currentTime=manifest.timing.office_start;
});
audio.addEventListener('timeupdate',()=>{
 if(!manifest||!manifest.timing||audio.paused||$('qp-read-study').checked)return;
 if(audio.currentTime>=manifest.timing.study_start){audio.pause();companionStopped=true;playerState();playLabel.textContent='Listen again';play.setAttribute('aria-label','Listen again');$('qp-finish').hidden=false;tell('Prayer complete. The optional reflection and guide are below.');}
});
const sharedSelection=new URLSearchParams(location.search);
if(/^\d{4}-\d{2}-\d{2}$/.test(sharedSelection.get('date')||'')){
 const candidate=sharedSelection.get('date'),parsed=new Date(candidate+'T12:00:00Z');
 if(!isNaN(parsed)&&parsed.toISOString().slice(0,10)===candidate&&Math.abs(Date.now()-parsed.getTime())<3*366*86400000){date.value=candidate;date.dispatchEvent(new Event('change',{bubbles:true}));}
}
if(['Rite-I','Rite-II'].includes(sharedSelection.get('rite'))){$('office-rite').value=sharedSelection.get('rite');$('office-rite').dispatchEvent(new Event('change',{bubbles:true}));}
if(names[sharedSelection.get('office')]){const button=Array.from(oldServices.querySelectorAll('button')).find(b=>b.dataset.office===sharedSelection.get('office'));if(button)button.click();}
if(sharedSelection.get('study')==='1')$('qp-study').open=true;
