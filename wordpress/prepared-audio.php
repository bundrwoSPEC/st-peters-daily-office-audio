// St. Peter's prepared Daily Office audio. Public reading only; no upload credential.
add_action('rest_api_init', function () {
    register_rest_route('stp-office/v1', '/prepared-audio', array(
        'methods' => 'GET',
        'permission_callback' => '__return_true',
        'callback' => function ($request) {
            $date = (string) $request->get_param('date');
            $office = (string) $request->get_param('office');
            $voice = (string) $request->get_param('voice');
            $rite = $request->get_param('rite') === 'Rite-I' ? 'Rite-I' : 'Rite-II';
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!$parsed || $parsed->format('Y-m-d') !== $date || abs($parsed->getTimestamp() - time()) > 3 * 366 * DAY_IN_SECONDS || !in_array($office, array('morning-prayer','noonday-prayer','evening-prayer','compline'), true) || !in_array($voice, array('michael','heart'), true)) {
                return new WP_Error('invalid_audio', 'Please choose a valid date, service, and voice.', array('status'=>400));
            }
            if (in_array($office, array('noonday-prayer','compline'), true)) { $rite = 'Rite-II'; }
            $stem = $office . '-' . $rite . '-' . $voice;
            $key = 'stp_prepared_3_' . md5($date . $stem);
            $cached = get_transient($key);
            if (is_array($cached)) { return rest_ensure_response($cached); }
            if ($cached === 'pending') { return new WP_Error('audio_pending', 'The natural recording is not prepared for this selection yet. You can wait for it or open the optional basic device voice below.', array('status'=>404)); }
            $base = 'https://github.com/bundrwoSPEC/st-peters-daily-office-audio/releases/download/office-' . $date . '/';
            $response = wp_remote_get($base . $stem . '.json', array('timeout'=>20, 'redirection'=>4, 'limit_response_size'=>600000));
            $data = is_wp_error($response) ? null : json_decode(wp_remote_retrieve_body($response), true);
            $expected_rite = $rite === 'Rite-I' ? 'traditional' : 'contemporary';
            if (!is_array($data) || $data['date'] !== $date || $data['office'] !== $office || $data['voice'] !== $voice || $data['rite'] !== $expected_rite || !isset($data['blocks'], $data['cues'], $data['duration']) || !is_array($data['blocks']) || count($data['blocks']) < 8 || !is_array($data['cues']) || $data['duration'] <= 0) {
                set_transient($key, 'pending', 2 * MINUTE_IN_SECONDS);
                return new WP_Error('audio_pending', 'The natural recording is not prepared for this selection yet. You can wait for it or open the optional basic device voice below.', array('status'=>404));
            }
            // Serve a saved copy from the church domain for reliable phone playback.
            $uploads = wp_upload_dir();
            if (!empty($uploads['error'])) { return new WP_Error('audio_storage', 'The recording could not be loaded. Please try again later.', array('status'=>503)); }
            $relative = '/stp-office-audio/' . $date;
            $directory = $uploads['basedir'] . $relative;
            $file = $directory . '/' . $stem . '.mp3';
            if (!wp_mkdir_p($directory)) { return new WP_Error('audio_storage', 'The recording could not be loaded. Please try again later.', array('status'=>503)); }
            if (!is_file($file) || filesize($file) < 10000) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                $temporary = wp_tempnam($stem . '.mp3');
                if (!$temporary) { return new WP_Error('audio_storage', 'Please try this recording again later.', array('status'=>503)); }
                $download = wp_remote_get($base . $stem . '.mp3', array('timeout'=>60, 'redirection'=>4, 'stream'=>true, 'filename'=>$temporary, 'limit_response_size'=>25000000));
                $valid = !is_wp_error($download) && wp_remote_retrieve_response_code($download) === 200 && is_file($temporary) && filesize($temporary) >= 10000 && filesize($temporary) < 25000000;
                if ($valid) {
                    $handle = fopen($temporary, 'rb');
                    $signature = $handle ? fread($handle, 3) : '';
                    if ($handle) { fclose($handle); }
                    $valid = substr($signature, 0, 3) === 'ID3' || (strlen($signature) >= 2 && ord($signature[0]) === 255 && (ord($signature[1]) & 224) === 224);
                }
                if (!$valid || !rename($temporary, $file)) {
                    if (is_file($temporary)) { unlink($temporary); }
                    return new WP_Error('audio_download', 'The recording could not be loaded. Please use Try recording again in a moment.', array('status'=>503));
                }
                chmod($file, 0644);
            }
            $data['audio_url'] = $uploads['baseurl'] . $relative . '/' . $stem . '.mp3';
            set_transient($key, $data, HOUR_IN_SECONDS);
            return rest_ensure_response($data);
        }
    ));
});

add_action('wp_footer', function () {
    if (!is_page('todays-worship')) { return; }
    ?>
<style>
body.stp-prayer-space{background:#f5eee2!important}body.stp-prayer-space #header,body.stp-prayer-space #footer,body.stp-prayer-space .sp-page-back{display:none!important}body.stp-prayer-space #lqd-contents-wrap{width:100%;max-width:none;padding:0}body.stp-prayer-space #lqd-site-content{padding:0!important;background:transparent!important}
#stp-office{max-width:none!important;margin:0!important;padding:0!important;color:#42372e!important;background:radial-gradient(ellipse at 50% 5%,#fff5d9 0,rgba(255,247,226,.6) 25%,transparent 62%),#f5eee2;min-height:100svh;font-family:Georgia,'Times New Roman',serif}
#stp-office #stp-natural-audio{max-width:900px;margin:auto;padding:24px 40px 64px;border:0;background:none;position:relative}#stp-office #stp-natural-audio *{box-sizing:border-box}#stp-office #stp-natural-audio [hidden]{display:none!important}
#stp-office .qp-top{display:flex;justify-content:space-between;align-items:start;gap:18px;min-height:40px;font:13px/1.5 system-ui,sans-serif}#stp-office .qp-church{color:#75624d;text-decoration:none;letter-spacing:.05em;padding:8px 0}#stp-office #qp-settings{margin:0;position:relative;z-index:3}#stp-office #qp-settings>summary{list-style:none;font:14px/1.5 system-ui,sans-serif;color:#66543e;padding:8px;cursor:pointer;white-space:nowrap}#stp-office #qp-settings>summary::-webkit-details-marker{display:none}#stp-office #qp-settings>summary span{font-size:20px;vertical-align:-1px;margin-right:5px}#stp-office #qp-settings[open]{z-index:5}#stp-office .qp-options{position:absolute;right:0;top:43px;width:min(560px,calc(100vw - 36px));padding:25px;background:#fffaf1;border:1px solid #d5c5a8;border-radius:12px;box-shadow:0 16px 50px #4d36172b;max-height:76vh;overflow:auto;text-align:left}#stp-office .qp-options h2{font:normal 24px/1.3 Georgia,serif;color:#4b3b2b;margin:0 0 20px}#stp-office .qp-fields{display:grid;grid-template-columns:1fr 1fr;gap:18px}#stp-office .qp-options label{font:14px/1.6 system-ui,sans-serif;color:#594a3b}#stp-office .qp-options select{width:100%;margin-top:5px;background:#fffdf8;color:#4a3a2a;border:1px solid #b9a98d;border-radius:6px;min-height:44px;padding:9px;font:16px system-ui,sans-serif}#stp-office .qp-note{font:13px/1.6 system-ui,sans-serif;color:#756550;margin:14px 0}#stp-office .qp-check{display:flex;align-items:center;gap:10px;margin:20px 0 5px}#stp-office .qp-check input{width:18px;min-height:18px;accent-color:#806338;margin:0}#stp-office .qp-actions{display:flex;flex-wrap:wrap;gap:8px;margin:20px 0}#stp-office .qp-options button{font:14px/1.4 system-ui,sans-serif;background:transparent!important;color:#654d31!important;border:1px solid #bca57b;border-radius:6px;padding:10px 12px}#stp-office .qp-more{border-top:1px solid #e0d5c1;padding-top:14px;margin-top:14px;font:14px/1.5 system-ui,sans-serif}#stp-office .qp-more summary{font-weight:500;color:#655239}#stp-office audio{width:100%;margin-top:14px}
#stp-office .qp-intro{text-align:center;padding:30px 0 22px}#stp-office .qp-intro h1{font:normal clamp(30px,5vw,44px)/1.25 Georgia,serif!important;color:#57432e!important;letter-spacing:-.02em;margin:25px 0 8px!important}#stp-office .qp-candle{height:70px;width:50px;position:relative;margin:0 auto;filter:drop-shadow(0 0 22px #f6c26788)}#stp-office .qp-wax{position:absolute;left:14px;bottom:0;width:22px;height:35px;background:linear-gradient(90deg,#e6d5ae,#fff8de 45%,#dfcea8);border-radius:4px 4px 2px 2px;box-shadow:0 8px 20px #b58b3e1a}#stp-office .qp-flame{position:absolute;left:18px;top:6px;width:14px;height:25px;border-radius:65% 35% 55% 45%;transform:rotate(3deg);background:radial-gradient(ellipse at 50% 70%,#fffde8 5%,#ffe6a2 40%,#e7af4e 80%);box-shadow:0 0 22px 10px #f8d48277}
#stp-office .qp-selection{display:flex;justify-content:center;align-items:center;gap:18px;flex-wrap:wrap;margin:6px 0 12px}#stp-office .qp-selection label{font:16px/1.5 Georgia,serif}#stp-office .qp-selection .date-group{min-width:0}#stp-office .qp-selection .date-group>label{position:absolute;clip-path:inset(50%);width:1px;height:1px;overflow:hidden}#stp-office #office-date{background:transparent!important;color:#5b4934!important;border:0!important;border-bottom:1px solid #cdbd9d!important;border-radius:0;padding:8px 3px!important;min-height:44px;font:16px/1.5 Georgia,serif!important;width:160px;color-scheme:light}#stp-office #office-date::-webkit-calendar-picker-indicator{filter:none!important;opacity:.6}#stp-office #office-color{display:none!important}#stp-office .qp-service{display:flex;align-items:center;gap:7px;color:#59452e}#stp-office .qp-service select{background:transparent;border:0;border-bottom:1px solid #cdbd9d;border-radius:0;min-height:44px;padding:8px 4px;color:#59452e;font:16px/1.5 Georgia,serif;max-width:230px}#stp-office .qp-list{font:22px Georgia,serif;color:#8c7551}#stp-office .qp-liturgy{display:flex;justify-content:center;align-items:center;gap:8px;flex-wrap:wrap;color:#8a765a;font:13px/1.5 system-ui,sans-serif;min-height:20px;text-align:center}#stp-office #office-season{font:inherit;margin:0}#stp-office #office-observance{font:inherit;margin:0}#stp-office #office-observance:empty{display:none}#stp-office #qp-color{width:7px;height:7px;border-radius:50%;border:1px solid #b5a383;display:inline-block}#stp-office .qp-listen{text-align:center;margin:28px 0 30px}#stp-office #qp-play{border:1px solid #9d7945;background:#6c5233;color:#fff8e9!important;border-radius:40px;padding:14px 29px;min-height:54px;font:500 16px/1.5 system-ui,sans-serif;box-shadow:0 5px 22px #8460321a;display:inline-flex;align-items:center;gap:11px}#stp-office #qp-play:hover{background:#594329}#stp-office #qp-play:disabled{opacity:.6}#stp-office #qp-play-icon{font-size:15px}#stp-office .qp-error{max-width:540px;margin:16px auto;font:14px/1.6 system-ui,sans-serif;color:#7b3c2d}
#stp-office #qp-reading{border-top:1px solid #d8c7a5;padding-top:12px}#stp-office .qp-reading-top{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:0 12px 10px}#stp-office #qp-reading-title{font:12px/1.5 system-ui,sans-serif;letter-spacing:.1em;text-transform:uppercase;color:#978264;margin:0}#stp-office #qp-resume-follow{font:12px/1.5 system-ui,sans-serif;color:#72552e!important;background:#fff8e8;border:1px solid #c4ad83;border-radius:20px;padding:7px 11px}#stp-office .qp-scroll{height:clamp(300px,55svh,620px);overflow:auto;overscroll-behavior:contain;scrollbar-width:thin;scrollbar-color:#cbbb9f transparent;padding:24px 32px 44px;background:rgba(255,253,246,.7);border-radius:12px;color:#5e5141;font:21px/1.9 Georgia,serif;scroll-padding:50px}#stp-office .qp-scroll p{margin:0 0 18px;padding:4px 10px;border-radius:5px;transition:background-color .5s,color .5s}#stp-office .qp-scroll h2{font:normal 24px/1.45 Georgia,serif;color:#6e5636;margin:32px 0 18px;padding:5px 10px;border-radius:5px}#stp-office .qp-scroll .being-read{background:#f2e2ba!important;color:#352a1e!important;outline:0!important;box-shadow:inset 2px 0 #c49a57}#stp-office .qp-scroll.qp-large{font-size:25px}#stp-office #qp-finish{text-align:center;padding:30px 12px 8px;color:#796347}#stp-office #qp-finish p{font-style:italic;font-size:19px}#stp-office #qp-record-prayer{background:transparent;color:#70552e!important;border:1px solid #b7a078;border-radius:24px;font:14px system-ui,sans-serif;padding:12px 20px}
#stp-office #qp-legacy>.intro,#stp-office #qp-legacy>#office-heading,#stp-office #qp-legacy>#office-services,#stp-office #qp-legacy>.small{display:none}#stp-office #qp-legacy .tools{margin:14px 0}#stp-office #qp-legacy iframe{height:550px}#stp-office #qp-legacy .journey{padding:14px;margin:18px 0}#stp-office #qp-legacy #journey-import{font-size:13px}#stp-office .qp-sr{position:absolute!important;width:1px!important;height:1px!important;padding:0!important;margin:-1px!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;clip-path:inset(50%)!important;white-space:nowrap!important;border:0!important}#stp-office :focus-visible{outline:2px solid #9f7e4d!important;outline-offset:4px}
@media(max-width:600px){#stp-office #stp-natural-audio{padding:17px 18px 36px}#stp-office .qp-intro{padding-top:24px}#stp-office .qp-intro h1{font-size:30px!important}#stp-office .qp-selection{gap:8px 15px}#stp-office .qp-scroll{height:54svh;padding:16px 10px 36px;font-size:19px;line-height:1.8}#stp-office .qp-scroll.qp-large{font-size:23px}#stp-office .qp-fields{gap:13px}#stp-office .qp-options{padding:19px}#stp-office .qp-top{font-size:11px}#stp-office #qp-settings>summary{font-size:12px}#stp-office .qp-church{letter-spacing:.02em}#stp-office .qp-reading-top{padding-left:4px;padding-right:4px}#stp-office #office-date{font-size:15px!important;width:150px}#stp-office .qp-service select{font-size:15px;max-width:185px}}
@media(prefers-reduced-motion:reduce){#stp-office .qp-scroll p{transition:none}}
</style>
<script>
(function(){'use strict';
const root=document.getElementById('stp-office');
if(!root||document.getElementById('stp-natural-audio'))return;
const $=id=>document.getElementById(id),original=root.querySelector('.office-narration');
if(!original)return;
const oldNodes=Array.from(root.children),date=$('office-date'),oldServices=$('office-services');
const names={'morning-prayer':'Morning Prayer','noonday-prayer':'Noonday Prayer','evening-prayer':'Evening Prayer','compline':'Compline'};
const section=document.createElement('section');section.id='stp-natural-audio';section.className='quiet-prayer';section.setAttribute('aria-label','A quiet space for daily prayer');
section.innerHTML=`
<div class="qp-top"><a class="qp-church" href="/">St. Peter’s · Brenham</a><details id="qp-settings"><summary><span aria-hidden="true">☷</span> Prayer settings</summary><div class="qp-options">
<h2>Make this time your own</h2><div class="qp-fields"><label>Reading voice<select id="stp-natural-voice"><option value="michael">Michael</option><option value="heart">Heart</option></select></label><label>Reading pace<select id="stp-natural-speed"><option value="0.75">Slower · 0.75×</option><option value="1" selected>Natural · 1×</option><option value="1.25">Faster · 1.25×</option><option value="1.5">Fast · 1.5×</option></select></label><div id="qp-rite-mount"></div><label>Text size<select id="qp-text-size"><option value="regular">Comfortable</option><option value="large">Larger</option></select></label></div>
<label class="qp-check"><input id="qp-follow" type="checkbox" checked> Gently follow the reading</label><p class="qp-note">The matching prayer text highlights a passage at a time. Scrolling by hand pauses following; use “Follow the reading” to return.</p>
<div class="qp-actions"><button type="button" id="stp-natural-stop">Start from the beginning</button><button type="button" id="stp-natural-retry">Reload saved recording</button><button type="button" id="qp-show-text">Show prayer text</button></div>
<details class="qp-more"><summary>Audio position and playback controls</summary><audio id="stp-natural-player" controls preload="none" aria-label="Daily prayer recording"></audio></details>
<details class="qp-more" id="qp-resources"><summary>Prayer resources &amp; my prayer journal</summary><div id="qp-legacy"></div></details>
<p class="qp-note">Listen as often as you like. Playing, restarting, changing the pace, or choosing another voice uses saved recordings; it does not generate new prayers.</p><p class="qp-note">AI-generated voices · Book of Common Prayer (1979), with public-domain scripture supplied by <a href="https://usedailyoffice.com/1979/" target="_blank" rel="noopener">Gracelight</a>. The matching text below follows this recording. Other prayer resources may use different readings or optional prayers.</p>
</div></details></div>
<header class="qp-intro"><div class="qp-candle" aria-hidden="true"><span class="qp-flame"></span><span class="qp-wax"></span></div><h1>Be still. God is near.</h1></header>
<div class="qp-selection"><div id="qp-date-mount"></div><label class="qp-service"><span class="qp-sr">Choose a prayer service</span><span aria-hidden="true" class="qp-list">☷</span><select id="qp-service" aria-label="Choose a prayer service"><option value="morning-prayer">Morning Prayer</option><option value="noonday-prayer">Noonday Prayer</option><option value="evening-prayer">Evening Prayer</option><option value="compline">Compline</option></select></label></div>
<div class="qp-liturgy"><span id="qp-color" aria-hidden="true"></span><span id="qp-season-mount"></span><span id="qp-observance-mount"></span></div>
<div class="qp-listen"><button type="button" id="qp-play" disabled><span id="qp-play-icon" aria-hidden="true">▶</span><span id="qp-play-label">Preparing prayer…</span></button><p id="stp-natural-status" class="qp-sr" role="status">Preparing your prayer.</p></div>
<div id="qp-reading" hidden><div class="qp-reading-top"><p id="qp-reading-title"></p><button type="button" id="qp-resume-follow" hidden>Follow the reading</button></div><div id="stp-natural-text" class="qp-scroll" tabindex="0" aria-label="Prayer text, highlighted with the recording"></div><div id="qp-finish" hidden><p>Go in peace. Carry this stillness with you.</p><button type="button" id="qp-record-prayer">Mark this time as prayed</button></div></div>`;
root.prepend(section);
const legacy=$('qp-legacy');oldNodes.forEach(node=>{if(!['STYLE','SCRIPT','NOSCRIPT'].includes(node.tagName))legacy.appendChild(node);});
// Move the existing controls so their calendar and prayer-journal behavior stays intact.
$('qp-date-mount').appendChild(date.parentElement);
$('qp-season-mount').appendChild($('office-season'));
$('qp-observance-mount').appendChild($('office-observance'));
$('qp-rite-mount').appendChild($('office-rite').parentElement);
original.querySelector('h3').textContent='Optional basic device voice';
const basic=document.createElement('details');basic.className='qp-more';basic.innerHTML='<summary>Optional basic phone/device voice</summary><p>This is your device’s voice, rather than Michael or Heart.</p>';original.before(basic);basic.appendChild(original);original.hidden=false;
document.body.classList.add('stp-prayer-space');
const audio=$('stp-natural-player'),status=$('stp-natural-status'),voice=$('stp-natural-voice'),speed=$('stp-natural-speed'),text=$('stp-natural-text'),reading=$('qp-reading'),play=$('qp-play'),playLabel=$('qp-play-label'),playIcon=$('qp-play-icon'),service=$('qp-service'),follow=$('qp-follow'),resume=$('qp-resume-follow'),settings=$('qp-settings');
let version=0,controller=null,manifest=null,lastKey='',ready=false,lastCue=-1,manualScroll=false,programScrollUntil=0;
const reduced=window.matchMedia('(prefers-reduced-motion: reduce)').matches;
try{voice.value=localStorage.getItem('stp-natural-voice')==='heart'?'heart':'michael';const size=localStorage.getItem('stp-prayer-text-size');if(size==='large')$('qp-text-size').value='large';}catch(e){}
function sizeText(){text.classList.toggle('qp-large',$('qp-text-size').value==='large');}sizeText();
function selection(){return {date:date.value,office:oldServices.querySelector('[aria-pressed="true"]').dataset.office,rite:$('office-rite').value,voice:voice.value};}
function stop(){audio.pause();if(audio.readyState)audio.currentTime=0;}
function tell(message,error){status.textContent=message;status.className=error?'qp-error':'qp-sr';}
function playerState(){play.disabled=!ready;const running=!audio.paused&&!audio.ended;playLabel.textContent=running?'Pause prayer':audio.ended?'Pray again':audio.currentTime>0?'Continue prayer':ready?'Begin prayer':'Preparing prayer…';playIcon.textContent=running?'Ⅱ':'▶';play.setAttribute('aria-label',playLabel.textContent);}
function syncSelection(){const s=selection();service.value=s.office;$('qp-color').style.backgroundColor=date.style.backgroundColor;$('qp-reading-title').textContent=names[s.office];}
function scrollToCue(force){if(!manifest||!follow.checked||manualScroll)return;const cue=manifest.cues[lastCue],el=cue&&text.children[cue.first];if(!el)return;const outer=text.getBoundingClientRect(),inner=el.getBoundingClientRect(),top=inner.top-outer.top+text.scrollTop;const target=Math.max(0,top-text.clientHeight*0.3);if(force||inner.top<outer.top+28||inner.bottom>outer.bottom-45){programScrollUntil=performance.now()+1000;text.scrollTo({top:target,behavior:reduced?'auto':'smooth'});}}
function highlight(force){if(!manifest)return;const n=manifest.cues.findIndex(c=>audio.currentTime>=c.start&&audio.currentTime<c.end);if(n===lastCue&&!force)return;lastCue=n;const cue=manifest.cues[n];Array.from(text.children).forEach((el,i)=>{const active=!!cue&&i>=cue.first&&i<=cue.last;el.classList.toggle('being-read',active);if(active)el.setAttribute('aria-current','true');else el.removeAttribute('aria-current');});scrollToCue(force);}
function manual(){if(!audio.paused&&follow.checked){manualScroll=true;resume.hidden=false;}}
text.addEventListener('wheel',manual,{passive:true});text.addEventListener('touchmove',manual,{passive:true});text.addEventListener('keydown',e=>{if(['ArrowUp','ArrowDown','PageUp','PageDown','Home','End',' '].includes(e.key))manual();});text.addEventListener('scroll',()=>{if(performance.now()>programScrollUntil&&!audio.paused)manual();},{passive:true});
resume.onclick=()=>{manualScroll=false;resume.hidden=true;follow.checked=true;highlight(true);};follow.onchange=()=>{manualScroll=false;resume.hidden=true;if(follow.checked)highlight(true);};
async function refresh(force){syncSelection();const selected=selection(),key=JSON.stringify(selected);if(key===lastKey&&!force)return;lastKey=key;const current=++version;if(controller)controller.abort();controller=new AbortController();ready=false;stop();audio.removeAttribute('src');audio.load();manifest=null;lastCue=-1;text.replaceChildren();reading.hidden=true;$('qp-finish').hidden=true;manualScroll=false;resume.hidden=true;playerState();tell('Preparing your prayer.');try{const response=await fetch('/wp-json/stp-office/v1/prepared-audio?delivery=3&'+new URLSearchParams(selected),{signal:controller.signal});const data=await response.json();if(current!==version)return;if(!response.ok)throw Error(data.message||'This recording is not ready yet. Please choose another service or try again shortly.');if(data.date!==selected.date||data.office!==selected.office||data.voice!==selected.voice||!Array.isArray(data.blocks)||!Array.isArray(data.cues))throw Error('The recording did not match this prayer. Please try again.');manifest=data;data.blocks.forEach(b=>{const el=document.createElement(b.kind==='heading'?'h2':'p');el.textContent=b.text;text.appendChild(el);});audio.src=data.audio_url;audio.playbackRate=Number(speed.value);ready=true;playerState();tell('Your prayer is ready. Press Begin prayer whenever you are ready.');}catch(e){if(current!==version||e.name==='AbortError')return;playLabel.textContent='Prayer not ready';tell(e.message||'Your recording could not load. Open Prayer settings to reload it.',true);}}
play.onclick=()=>{if(!ready)return;if(!audio.paused){audio.pause();return;}if(audio.ended)audio.currentTime=0;reading.hidden=false;settings.open=false;manualScroll=false;resume.hidden=true;const promise=audio.play();if(promise)promise.catch(()=>{tell('Please try Begin prayer again. You can also reload the saved recording in Prayer settings.',true);playerState();});};
audio.addEventListener('play',()=>{if(window.speechSynthesis)window.speechSynthesis.cancel();reading.hidden=false;playerState();tell('Reading '+manifest.title+' with '+(voice.value==='heart'?'Heart':'Michael')+'.');highlight(true);});audio.addEventListener('pause',playerState);audio.addEventListener('ended',()=>{playerState();$('qp-finish').hidden=false;tell('Prayer complete. Go in peace.');});audio.addEventListener('error',()=>{if(!ready)return;tell('This recording could not play. Open Prayer settings and choose Reload saved recording, or try the other voice.',true);playerState();});audio.addEventListener('timeupdate',()=>highlight(false));audio.addEventListener('seeked',()=>highlight(true));
voice.onchange=()=>{try{localStorage.setItem('stp-natural-voice',voice.value);}catch(e){}refresh(true);};speed.onchange=()=>{audio.playbackRate=Number(speed.value);};$('qp-text-size').onchange=()=>{sizeText();try{localStorage.setItem('stp-prayer-text-size',$('qp-text-size').value);}catch(e){}highlight(true);};
$('stp-natural-stop').onclick=()=>{stop();playerState();manualScroll=false;highlight(true);tell('Returned to the beginning.');};$('stp-natural-retry').onclick=()=>refresh(true);$('qp-show-text').onclick=()=>{if(manifest){reading.hidden=false;settings.open=false;highlight(true);}else tell('Choose a prayer with a prepared recording to read along.',true);};$('qp-record-prayer').onclick=()=>{$('office-complete').click();$('qp-record-prayer').textContent='This time is recorded ✓';$('qp-record-prayer').disabled=true;};
service.onchange=()=>{const button=Array.from(oldServices.querySelectorAll('button')).find(b=>b.dataset.office===service.value);if(button)button.click();settings.open=false;};
['office-date','office-rite'].forEach(id=>$(id).addEventListener('change',()=>queueMicrotask(()=>refresh(false))));['office-prev','office-today','office-next','office-services'].forEach(id=>$(id).addEventListener('click',()=>queueMicrotask(()=>refresh(false))));
const dayObserver=new MutationObserver(()=>{syncSelection();const current=selection();if(JSON.stringify(current)!==lastKey)refresh(false);});dayObserver.observe($('office-heading'),{childList:true,characterData:true,subtree:true});
window.addEventListener('pagehide',stop);setInterval(()=>{if(!ready)refresh(true);},120000);refresh(false);
})();
</script>
    <?php
}, 30);
