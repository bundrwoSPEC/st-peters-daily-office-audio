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
#stp-natural-audio{border:1px solid #b39752;border-left:5px solid #b39752;border-radius:7px;background:#f8f6f0;padding:20px;margin:24px 0}#stp-natural-audio h3{margin:0 0 10px}#stp-natural-audio audio{width:100%;margin:12px 0}#stp-natural-audio .natural-transcript{max-height:420px;overflow:auto;padding:18px;background:#fff;font:19px/1.7 Georgia,serif}#stp-natural-audio .natural-transcript h4{font-size:23px;color:#142c49}#stp-natural-audio .being-read{background:#fff0bb;outline:2px solid #b39752;outline-offset:2px}#stp-natural-audio [hidden]{display:none!important}@media(max-width:600px){#stp-natural-audio{padding:12px}#stp-natural-audio .natural-transcript{padding:12px;font-size:18px}}
</style>
<script>
(function(){'use strict';
const root=document.getElementById('stp-office');if(!root||document.getElementById('stp-natural-audio'))return;
const original=root.querySelector('.office-narration');if(!original)return;original.querySelector('h3').textContent='Device voice';
const section=document.createElement('section');section.id='stp-natural-audio';section.setAttribute('aria-labelledby','stp-natural-title');
section.innerHTML='<h3 id="stp-natural-title">Read Aloud — Michael &amp; Heart</h3><p class="small">Choose Michael or Heart, then tap the triangle ▶ in the audio player. You can listen again or change voices as often as you like; these controls play saved recordings and do not generate new prayers.</p><div class="tools"><label>Voice<select id="stp-natural-voice"><option value="michael">Michael</option><option value="heart">Heart</option></select></label><label>Reading speed<select id="stp-natural-speed"><option value="0.75">Slower · 0.75×</option><option value="1" selected>Normal · 1×</option><option value="1.25">Faster · 1.25×</option><option value="1.5">Fast · 1.5×</option></select></label><button type="button" class="quiet" id="stp-natural-stop" hidden>Start over</button><button type="button" class="quiet" id="stp-natural-retry">Try recording again</button></div><p class="status" id="stp-natural-status" role="status">Checking the recording for your selected service…</p><audio id="stp-natural-player" controls preload="none" hidden aria-label="Daily Office natural voice recording"></audio><details id="stp-natural-words" hidden><summary>Show reading text</summary><div class="natural-transcript" id="stp-natural-text" tabindex="0" aria-label="Natural voice reading text"></div></details><p class="small" id="stp-natural-credit">AI-generated voices. Prayers from the 1979 BCP and public-domain Bible translations, supplied by <a href="https://usedailyoffice.com/1979/" target="_blank" rel="noopener">Gracelight</a>. Wording and optional prayers may differ from the prayer window below.</p>';
original.before(section);
const fallback=document.createElement('details');fallback.id='stp-basic-voice';fallback.innerHTML='<summary>Other option: basic phone/device voice</summary><p class="small">This optional voice comes from your device. It is not Michael or Heart.</p>';original.before(fallback);fallback.appendChild(original);original.hidden=false;
const $=id=>document.getElementById(id),audio=$('stp-natural-player'),status=$('stp-natural-status'),voice=$('stp-natural-voice'),speed=$('stp-natural-speed'),text=$('stp-natural-text'),words=$('stp-natural-words'),restart=$('stp-natural-stop');
let version=0,controller=null,manifest=null,lastKey='',ready=false,lastCue=-1;
try{voice.value=localStorage.getItem('stp-natural-voice')==='heart'?'heart':'michael';}catch(e){}
function selection(){return {date:$('office-date').value,office:root.querySelector('#office-services [aria-pressed="true"]').dataset.office,rite:$('office-rite').value,voice:voice.value};}
function stop(){audio.pause();if(audio.readyState)audio.currentTime=0;}
function highlight(){if(!manifest)return;const n=manifest.cues.findIndex(c=>audio.currentTime>=c.start&&audio.currentTime<c.end);if(n===lastCue)return;lastCue=n;const cue=manifest.cues[n];Array.from(text.children).forEach((el,i)=>el.classList.toggle('being-read',!!cue&&i>=cue.first&&i<=cue.last));if(cue&&words.open){const el=text.children[cue.first];if(el)text.scrollTop=Math.max(0,el.offsetTop-text.offsetTop-25);}}
async function refresh(force){const selected=selection(),key=JSON.stringify(selected);if(key===lastKey&&!force)return;lastKey=key;const current=++version;if(controller)controller.abort();controller=new AbortController();stop();audio.removeAttribute('src');audio.load();audio.hidden=true;words.hidden=true;restart.hidden=true;ready=false;manifest=null;lastCue=-1;text.replaceChildren();fallback.hidden=false;status.textContent='Checking the recording for your selected service…';try{const response=await fetch('/wp-json/stp-office/v1/prepared-audio?delivery=3&'+new URLSearchParams(selected),{signal:controller.signal});const data=await response.json();if(current!==version)return;if(!response.ok)throw Error(data.message||'This recording is not available yet.');if(data.date!==selected.date||data.office!==selected.office||data.voice!==selected.voice||!Array.isArray(data.blocks)||!Array.isArray(data.cues))throw Error('The recording does not match this selection.');manifest=data;data.blocks.forEach(b=>{const el=document.createElement(b.kind==='heading'?'h4':'p');el.textContent=b.text;text.appendChild(el);});audio.src=data.audio_url;audio.playbackRate=Number(speed.value);audio.hidden=false;words.hidden=false;restart.hidden=false;fallback.hidden=true;ready=true;status.textContent='Ready: '+data.title+' · '+data.date+' · '+(selected.voice==='heart'?'Heart':'Michael')+'. Press play to begin.';}catch(e){if(current!==version||e.name==='AbortError')return;status.textContent=e.message||'The natural recording could not load. Use Try recording again, or open the optional basic device voice below.';}}
$('stp-natural-retry').addEventListener('click',()=>refresh(true));
voice.addEventListener('change',()=>{try{localStorage.setItem('stp-natural-voice',voice.value);}catch(e){}refresh(true);});speed.addEventListener('change',()=>{audio.playbackRate=Number(speed.value);});restart.addEventListener('click',()=>{stop();highlight();status.textContent='Returned to the beginning. Press play to begin.';});
audio.addEventListener('play',()=>{if(window.speechSynthesis)window.speechSynthesis.cancel();status.textContent='Reading '+manifest.title+' with '+(voice.value==='heart'?'Heart':'Michael')+'.';});audio.addEventListener('pause',()=>{if(ready&&!audio.ended)status.textContent='Paused. Press play to continue.';});audio.addEventListener('ended',()=>{status.textContent='Reading finished. You can mark this service as prayed below.';});audio.addEventListener('error',()=>{if(!ready)return;status.textContent='The recording could not play. Use Try recording again, or open the optional basic device voice below.';fallback.hidden=false;});audio.addEventListener('timeupdate',highlight);words.addEventListener('toggle',highlight);
['office-date','office-rite'].forEach(id=>$(id).addEventListener('change',()=>queueMicrotask(()=>refresh(false))));['office-prev','office-today','office-next','office-services'].forEach(id=>$(id).addEventListener('click',()=>queueMicrotask(()=>refresh(false))));window.addEventListener('pagehide',stop);setInterval(()=>refresh(!ready),120000);refresh(false);
})();
</script>
    <?php
}, 30);
