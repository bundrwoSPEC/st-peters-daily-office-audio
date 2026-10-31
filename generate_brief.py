"""Prepare reusable Michael and Heart recordings for the four BCP brief devotions."""
import datetime as dt
import hashlib
import json
from pathlib import Path
import subprocess
from devotions import DEVOTIONS, devotion
from generate import groups, VOICES

REPO = 'bundrwoSPEC/st-peters-daily-office-audio'
TAG = 'brief-devotions-v2'


def main():
    import numpy as np
    import soundfile as sf
    import torch
    from kokoro import KPipeline
    torch.set_num_threads(2)
    pipeline = KPipeline(lang_code='a', repo_id='hexgrad/Kokoro-82M', device='cpu')
    folder = Path('brief-output')
    folder.mkdir(exist_ok=True)
    for office in DEVOTIONS:
        data = devotion(office)
        blocks = data['blocks']
        for label, voice in VOICES.items():
            stem = f'{office}-{label}-brief-v1'
            samples, cues = 0, []
            wav = folder / (stem + '.wav')
            with sf.SoundFile(wav, 'w', samplerate=24000, channels=1, subtype='PCM_16') as out:
                for text, first, last in groups(blocks):
                    start, produced = samples / 24000, False
                    for _, _, sound in pipeline(text, voice=voice, speed=.96):
                        sound = np.asarray(sound, dtype=np.float32)
                        if sound.size:
                            out.write(sound)
                            samples += sound.size
                            produced = True
                    if not produced:
                        raise ValueError('Voice returned empty audio')
                    pause = np.zeros(7200 if blocks[first]['kind'] == 'heading' else 3600, dtype=np.float32)
                    out.write(pause)
                    samples += pause.size
                    cues.append({'start': round(start, 3), 'end': round(samples / 24000, 3), 'first': first, 'last': last})
            mp3 = folder / (stem + '.mp3')
            subprocess.run(['ffmpeg', '-nostdin', '-y', '-loglevel', 'error', '-i', str(wav), '-codec:a', 'libmp3lame', '-b:a', '64k', str(mp3)], check=True)
            wav.unlink()
            if mp3.stat().st_size < 10000:
                raise ValueError('Unexpectedly short recording')
            timing = {name: next(c['start'] for c in cues if c['first'] >= index) for name, index in [('office_start', data['segments']['office_first']), ('study_start', data['segments']['study_first'])]}
            timing['family_pause'] = next(c['end'] for c in cues if c['first'] <= data['family_pause_after'] <= c['last'])
            manifest = {**data, 'timing': timing, 'voice': label, 'duration': round(samples / 24000, 3),
                        'text_sha256': hashlib.sha256(json.dumps(blocks, ensure_ascii=False).encode()).hexdigest(),
                        'audio_sha256': hashlib.sha256(mp3.read_bytes()).hexdigest(),
                        'generated_at': dt.datetime.now(dt.timezone.utc).isoformat(),
                        'audio_url': f'https://github.com/{REPO}/releases/download/{TAG}/{stem}.mp3', 'cues': cues}
            listening = {}
            for key, suffix, start, end in [
                ('intro_prayer', 'prayer', 0, timing['study_start']),
                ('prayer_only', 'prayer-only', timing['office_start'], timing['study_start']),
                ('prayer_guide', 'without-intro', timing['office_start'], samples / 24000),
            ]:
                clip = folder / (stem + '-' + suffix + '.mp3')
                subprocess.run(['ffmpeg', '-nostdin', '-y', '-loglevel', 'error', '-ss', str(start), '-i', str(mp3), '-t', str(end-start), '-codec:a', 'libmp3lame', '-b:a', '64k', str(clip)], check=True)
                listening[key] = {'file': clip.name, 'url': f'https://github.com/{REPO}/releases/download/{TAG}/{clip.name}', 'sha256': hashlib.sha256(clip.read_bytes()).hexdigest(), 'duration': round(end-start, 3)}
            listening['intro_prayer_guide'] = {'file': mp3.name, 'url': manifest['audio_url'], 'sha256': manifest['audio_sha256'], 'duration': manifest['duration']}
            manifest['listening'] = listening
            (folder / (stem + '.json')).write_text(json.dumps(manifest, ensure_ascii=False), encoding='utf-8')
            print(f'{stem}: {samples / 24000:.0f} seconds', flush=True)


if __name__ == '__main__':
    main()
