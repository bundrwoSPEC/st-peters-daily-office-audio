"""Validate all brief assets before publishing their reusable recording release."""
import hashlib
import json
import math
import argparse
import os
import subprocess
from pathlib import Path
from devotions import DEVOTIONS, devotion
from publish import gh, release as release_by_tag, require

TAG = 'brief-devotions-v2'
REPO = 'bundrwoSPEC/st-peters-daily-office-audio'


def release(tag):
    found = release_by_tag(tag)
    if found:
        return found
    # A draft whose tag has not been created is absent from the by-tag API.
    page = 1
    while True:
        items = json.loads(gh('api', f'repos/{REPO}/releases?per_page=100&page={page}'))
        found = next((item for item in items if item['tag_name'] == tag), None)
        if found or len(items) < 100:
            return found
        page += 1


def restore(folder):
    existing = release(TAG)
    if not existing:
        return False
    folder.mkdir(parents=True, exist_ok=True)
    for asset in existing['assets']:
        name = asset['name']
        require(Path(name).name == name and name not in ('.', '..'), 'Unsafe asset name')
        with (folder / name).open('wb') as output:
            subprocess.run(['gh', 'api', f"repos/{REPO}/releases/assets/{asset['id']}",
                            '-H', 'Accept: application/octet-stream'], stdout=output, check=True)
    validate(folder)
    return True


def validate(folder):
    expected = {f'{o}-{v}-brief-v1{tail}' for o in DEVOTIONS for v in ('michael', 'heart') for tail in ('.mp3', '.json', '-prayer.mp3', '-prayer-only.mp3', '-without-intro.mp3')}
    require({p.name for p in folder.iterdir()} == expected, 'Expected exactly 40 brief-devotion assets')
    for office in DEVOTIONS:
        for voice in ('michael', 'heart'):
            stem = f'{office}-{voice}-brief-v1'
            data = json.loads((folder / (stem + '.json')).read_text())
            source = devotion(office)
            require(all(data[k] == v for k, v in source.items()), 'Devotion text or structure mismatch')
            require(set(data['listening']) == {'intro_prayer', 'prayer_only', 'prayer_guide', 'intro_prayer_guide'}, 'Missing listening variants')
            for variant in data['listening'].values():
                require(variant['file'] in expected and '/' not in variant['file'], 'Invalid listening filename')
                clip = (folder / variant['file']).read_bytes()
                require(len(clip) > 10000 and hashlib.sha256(clip).hexdigest() == variant['sha256'], 'Listening checksum mismatch')
                require(variant['url'] == f"https://github.com/{REPO}/releases/download/{TAG}/{variant['file']}" and variant['duration'] > 0, 'Invalid listening URL or duration')
            require(data['voice'] == voice, 'Incorrect voice')
            require(data['audio_url'] == f'https://github.com/{REPO}/releases/download/{TAG}/{stem}.mp3', 'Incorrect recording URL')
            sound = (folder / (stem + '.mp3')).read_bytes()
            require(len(sound) > 10000 and (sound.startswith(b'ID3') or (sound[0] == 255 and sound[1] & 224 == 224)), 'Invalid MP3')
            require(data['audio_sha256'] == hashlib.sha256(sound).hexdigest(), 'Audio checksum mismatch')
            require(data['text_sha256'] == hashlib.sha256(json.dumps(data['blocks'], ensure_ascii=False).encode()).hexdigest(), 'Text checksum mismatch')
            duration = data['duration']
            require(math.isfinite(duration) and duration > 0, 'Invalid duration')
            previous, last = 0, -1
            for c in data['cues']:
                require(abs(c['start'] - previous) <= .001 and 0 <= c['start'] < c['end'] <= duration + .001 and c['first'] == last + 1 and c['first'] <= c['last'] < len(data['blocks']), 'Invalid timing map')
                previous, last = c['end'], c['last']
            require(last == len(data['blocks']) - 1 and abs(previous - duration) <= .001, 'Incomplete recording')
            for key, index in [('office_start', source['segments']['office_first']), ('study_start', source['segments']['study_first'])]:
                require(data['timing'][key] == next(c['start'] for c in data['cues'] if c['first'] >= index), 'Invalid segment timing')
            require(data['timing']['family_pause'] == next(c['end'] for c in data['cues'] if c['first'] <= source['family_pause_after'] <= c['last']), 'Invalid family pause')
    return expected


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--restore', action='store_true')
    args = parser.parse_args()
    folder = Path('brief-output')
    if args.restore:
        ready = restore(folder)
        with open(os.environ['GITHUB_OUTPUT'], 'a') as output:
            output.write(f'ready={str(ready).lower()}\n')
        return
    expected = validate(folder)
    existing = release(TAG)
    if existing and not existing['draft']:
        require({a['name'] for a in existing['assets']} == expected, 'Existing public release is incomplete')
        print('Preserving existing complete brief recordings')
        return
    if not existing:
        gh('release', 'create', TAG, '--repo', REPO, '--draft', '--latest=false', '--title', 'Brief Daily Devotions — Michael and Heart', '--notes', 'BCP 1979, Daily Devotions for Individuals and Families, pp. 136–140. Four reusable devotions in Michael and Heart. Guide is separate from the liturgy. Synthetic speech generated with Kokoro.')
    gh('release', 'upload', TAG, '--repo', REPO, *map(str, sorted(folder.iterdir())), '--clobber')
    staged = release(TAG)
    require(staged and staged['draft'] and {a['name'] for a in staged['assets']} == expected, 'Uploaded inventory mismatch')
    for a in staged['assets']:
        p = folder / a['name']
        require(a['size'] == p.stat().st_size and a.get('digest') == 'sha256:' + hashlib.sha256(p.read_bytes()).hexdigest(), 'Uploaded checksum mismatch')
    gh('release', 'edit', TAG, '--repo', REPO, '--draft=false', '--latest=false')
    print('Published 40 validated brief-devotion assets')


if __name__ == '__main__':
    main()
