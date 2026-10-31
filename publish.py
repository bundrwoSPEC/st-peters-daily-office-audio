"""Publish complete, validated dates only; stage new releases as private drafts."""
import argparse
import datetime as dt
import hashlib
import json
import math
from pathlib import Path
import subprocess

REPO = 'bundrwoSPEC/st-peters-daily-office-audio'
SERVICES = [('morning-prayer', 'Rite-I'), ('morning-prayer', 'Rite-II'),
            ('evening-prayer', 'Rite-I'), ('evening-prayer', 'Rite-II'),
            ('noonday-prayer', 'Rite-II'), ('compline', 'Rite-II')]
VOICES = ('michael', 'heart')


def dates(start):
    day = dt.date.fromisoformat(start)
    if day.isoformat() != start:
        raise ValueError('Use a YYYY-MM-DD starting date')
    return [(day + dt.timedelta(days=n)).isoformat() for n in (0, 1)]


def expected_names():
    return {f'{office}-{rite}-{voice}-guide-v1.{ext}'
            for office, rite in SERVICES for voice in VOICES for ext in ('mp3', 'json')}


def require(condition, message):
    if not condition:
        raise ValueError(message)


def validate_date(folder, date):
    require(folder.is_dir(), f'{date}: missing output folder')
    require({p.name for p in folder.iterdir()} == expected_names(),
            f'{date}: expected exactly 24 MP3/JSON files; incomplete or unexpected output')
    for office, rite in SERVICES:
        transcripts = []
        for voice in VOICES:
            stem = f'{office}-{rite}-{voice}-guide-v1'
            mp3 = folder / (stem + '.mp3')
            data = json.loads((folder / (stem + '.json')).read_text(encoding='utf-8'))
            require((data['date'], data['office'], data['rite'], data['voice']) ==
                    (date, office, 'traditional' if rite == 'Rite-I' else 'contemporary', voice),
                    f'{stem}: date/service/rite/voice mismatch')
            require(data['audio_url'] == f'https://github.com/{REPO}/releases/download/office-{date}/{stem}.mp3',
                    f'{stem}: incorrect audio URL')
            blocks, cues, duration = data['blocks'], data['cues'], data['duration']
            require(len(blocks) >= 8 and all(b['text'].strip() for b in blocks), f'{stem}: empty transcript')
            digest = hashlib.sha256(json.dumps(blocks, ensure_ascii=False).encode()).hexdigest()
            require(data['text_sha256'] == digest, f'{stem}: transcript checksum mismatch')
            audio = mp3.read_bytes()
            require(len(audio) >= 10000 and (audio.startswith(b'ID3') or
                    (audio[0] == 255 and audio[1] & 224 == 224)), f'{stem}: invalid MP3')
            require(data['audio_sha256'] == hashlib.sha256(audio).hexdigest(), f'{stem}: audio checksum mismatch')
            require(math.isfinite(duration) and duration > 0 and cues, f'{stem}: invalid duration/cues')
            end, last = 0, -1
            for cue in cues:
                require(0 <= cue['start'] < cue['end'] <= duration + .001
                        and abs(cue['start'] - end) <= .001
                        and last < cue['first'] <= cue['last'] < len(blocks), f'{stem}: invalid timing cue')
                # Nonempty blocks must all be covered by the generated timing map.
                require(cue['first'] == last + 1, f'{stem}: missing transcript cues')
                end, last = cue['end'], cue['last']
            require(last == len(blocks) - 1 and abs(end - duration) <= .001, f'{stem}: truncated timing map')
            segments, timing = data['segments'], data['timing']
            require(data['companion_version'] == 1 and
                    0 < segments['office_first'] <= segments['office_last'] < segments['study_first'] < len(blocks),
                    f'{stem}: invalid introduction/office/study boundaries')
            for name, index in [('office_start', segments['office_first']), ('study_start', segments['study_first'])]:
                start = next(c['start'] for c in cues if c['first'] >= index)
                require(timing[name] == start, f'{stem}: segment timing mismatch')
            transcripts.append((blocks, data['segments'], data['study'], data['liturgy'], data['introduction']))
        require(transcripts[0] == transcripts[1], f'{office}-{rite}: Michael and Heart transcripts differ')
    return sorted(folder.iterdir())


def gh(*args):
    return subprocess.run(['gh', *args], check=True, capture_output=True, text=True).stdout


def release(tag):
    try:
        return json.loads(gh('api', f'repos/{REPO}/releases/tags/{tag}'))
    except subprocess.CalledProcessError as exc:
        if 'HTTP 404' not in exc.stderr:
            raise
        return None


def publish_date(folder, date):
    files = validate_date(folder, date)  # Validate before any release mutation.
    tag = f'office-{date}'
    existing = release(tag)
    if existing and not existing['draft']:
        require({a['name'] for a in existing['assets']} == expected_names(),
                f'{tag}: existing public release is incomplete; refusing to overwrite it')
        print(f'{tag}: already public with 24 assets; preserving existing recordings', flush=True)
        return
    if not existing:
        gh('release', 'create', tag, '--repo', REPO, '--draft', '--title', f'Daily Office — {date}',
           '--notes', 'Michael and Heart readings. BCP 1979; public-domain scripture text supplied by Gracelight. Synthetic speech generated with Kokoro. Prepared for St. Peter’s Episcopal Church, Brenham.', '--latest=false')
    # Only a draft may be replaced. A failed upload stays invisible to the player.
    gh('release', 'upload', tag, '--repo', REPO, *map(str, files), '--clobber')
    staged = release(tag)
    require(staged and staged['draft'] and {a['name'] for a in staged['assets']} == expected_names(),
            f'{tag}: draft inventory mismatch')
    local = {p.name: p.stat().st_size for p in files}
    require(all(a['size'] == local[a['name']] and
                a.get('digest') == 'sha256:' + hashlib.sha256((folder / a['name']).read_bytes()).hexdigest()
                for a in staged['assets']), f'{tag}: uploaded size/checksum mismatch')
    gh('release', 'edit', tag, '--repo', REPO, '--draft=false', '--latest=false')
    print(f'{tag}: published 24 validated assets', flush=True)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--date', required=True)
    parser.add_argument('--output', type=Path, default=Path('output'))
    args = parser.parse_args()
    expected_dates = dates(args.date)
    require(args.output.is_dir(), 'No generation artifacts were downloaded')
    require({p.name for p in args.output.iterdir()} <= set(expected_dates), 'Unexpected output date; refusing publication')
    failed = False
    for date in expected_dates:
        try:
            publish_date(args.output / date, date)
        except (ValueError, KeyError, TypeError, StopIteration, OSError, subprocess.CalledProcessError) as exc:
            print(f'::error::{date}: {exc}', flush=True)
            failed = True
    if failed:
        raise SystemExit(1)  # Never hide an incomplete date behind a green run.


if __name__ == '__main__':
    main()
