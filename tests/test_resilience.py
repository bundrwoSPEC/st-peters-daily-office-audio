import hashlib
import io
import json
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import patch
from urllib.error import HTTPError, URLError

import generate
import publish


def fixture(folder, date='2026-11-01'):
    folder.mkdir(parents=True, exist_ok=True)
    for office, rite in publish.SERVICES:
        for voice in publish.VOICES:
            stem = f'{office}-{rite}-{voice}-guide-v1'
            audio = b'ID3' + b'x' * 10000
            blocks = [{'kind': 'heading', 'text': f'Part {n}'} for n in range(8)]
            data = dict(date=date, office=office, rite='traditional' if rite == 'Rite-I' else 'contemporary',
                        voice=voice, blocks=blocks, duration=8, companion_version=1,
                        cues=[dict(start=n, end=n+1, first=n, last=n) for n in range(8)],
                        segments=dict(office_first=2, office_last=5, study_first=6),
                        timing=dict(office_start=2, study_start=6), study={}, liturgy={}, introduction='Introduction',
                        text_sha256=hashlib.sha256(json.dumps(blocks, ensure_ascii=False).encode()).hexdigest(),
                        audio_sha256=hashlib.sha256(audio).hexdigest(),
                        audio_url=f'https://github.com/{publish.REPO}/releases/download/office-{date}/{stem}.mp3')
            (folder / (stem + '.mp3')).write_bytes(audio)
            (folder / (stem + '.json')).write_text(json.dumps(data))


class RetryTests(unittest.TestCase):
    @patch('generate.time.sleep')
    def test_recovers_beyond_old_three_attempt_limit(self, sleep):
        errors = [HTTPError('url', 503, 'Unavailable', {}, None) for _ in range(3)]
        with patch('generate.urlopen', side_effect=errors + [io.BytesIO(b'{"ok":true}')]) as request:
            self.assertEqual(generate.get_json('https://example.test'), {'ok': True})
        self.assertEqual(request.call_count, 4)
        self.assertEqual(sleep.call_count, 3)

    @patch('generate.time.sleep')
    def test_permanent_http_and_bad_json_fail_immediately(self, sleep):
        for response in [HTTPError('url', 400, 'Bad Request', {}, None), io.BytesIO(b'not json')]:
            with patch('generate.urlopen', side_effect=[response] if isinstance(response, Exception) else None,
                       return_value=response) as request:
                with self.assertRaises((HTTPError, json.JSONDecodeError)):
                    generate.get_json('https://example.test')
                self.assertEqual(request.call_count, 1)
        sleep.assert_not_called()

    @patch('generate.time.sleep')
    def test_exhaustion_is_bounded(self, sleep):
        with patch('generate.urlopen', side_effect=URLError('timeout')) as request:
            with self.assertRaises(URLError):
                generate.get_json('https://example.test')
            self.assertEqual(request.call_count, 8)
        self.assertEqual(sleep.call_count, 7)

    def test_retry_after_and_rite_validation(self):
        with patch('generate.random.uniform', return_value=0):
            self.assertEqual(generate.retry_delay(0, {'Retry-After': '90'}), 90)
            self.assertEqual(generate.retry_delay(7, {'Retry-After': '9999'}), 300)
            self.assertEqual(generate.retry_delay(0, {'Retry-After': 'garbage'}), 5)
            import datetime as dt
            now = dt.datetime(2026, 11, 1, tzinfo=dt.timezone.utc)
            future = now + dt.timedelta(seconds=120)
            with patch('generate.parsedate_to_datetime', return_value=future), patch('generate.dt.datetime') as clock:
                clock.now.return_value = now
                self.assertEqual(generate.retry_delay(0, {'Retry-After': 'Sun, 01 Nov 2026 00:02:00 GMT'}), 120)
        data = dict(date='2026-11-01', office='morning-prayer', rite='traditional', blocks=[{}]*8)
        generate.validate_narration(data, '2026-11-01', 'morning-prayer', 'Rite-I')
        for date, office, rite in [('2026-11-02', 'morning-prayer', 'Rite-I'),
                                   ('2026-11-01', 'compline', 'Rite-I'),
                                   ('2026-11-01', 'morning-prayer', 'Rite-II')]:
            with self.assertRaises(ValueError):
                generate.validate_narration(data, date, office, rite)


class PublicationTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.folder = Path(self.temp.name) / '2026-11-01'
        fixture(self.folder)

    def edit(self, change):
        path = next(self.folder.glob('*.json'))
        data = json.loads(path.read_text())
        change(data)
        path.write_text(json.dumps(data))

    def test_complete_date_and_calendar_boundaries(self):
        self.assertEqual(len(publish.validate_date(self.folder, '2026-11-01')), 24)
        self.assertEqual(publish.dates('2026-12-31'), ['2026-12-31', '2027-01-01'])
        with self.assertRaises(ValueError):
            publish.dates('20261101')

    @patch('publish.gh')
    def test_missing_asset_never_touches_release(self, gh):
        next(self.folder.glob('*.mp3')).unlink()
        with self.assertRaises(ValueError):
            publish.publish_date(self.folder, '2026-11-01')
        gh.assert_not_called()

    def test_mismatches_and_tampering_fail(self):
        changes = [lambda d: d.update(date='2026-11-02'), lambda d: d.update(voice='wrong'),
                   lambda d: d.update(rite='wrong'), lambda d: d.update(office='wrong'),
                   lambda d: d['blocks'][0].update(text='Changed'), lambda d: d.update(audio_url='wrong'),
                   lambda d: d.update(audio_sha256='wrong'), lambda d: d['cues'][0].update(end=99),
                   lambda d: d['timing'].update(study_start=1), lambda d: d['study'].update(summary='different')]
        for change in changes:
            with self.subTest(change=change):
                fixture(self.folder)
                self.edit(change)
                with self.assertRaises(ValueError):
                    publish.validate_date(self.folder, '2026-11-01')

    @patch('publish.gh')
    @patch('publish.release')
    def test_complete_public_release_is_preserved(self, release, gh):
        release.return_value = {'draft': False, 'assets': [{'name': n} for n in publish.expected_names()]}
        publish.publish_date(self.folder, '2026-11-01')
        gh.assert_not_called()

    @patch('publish.gh')
    @patch('publish.release')
    def test_draft_is_verified_before_publication(self, release, gh):
        assets = [dict(name=p.name, size=p.stat().st_size,
                       digest='sha256:'+hashlib.sha256(p.read_bytes()).hexdigest()) for p in self.folder.iterdir()]
        release.side_effect = [None, {'draft': True, 'assets': assets}]
        publish.publish_date(self.folder, '2026-11-01')
        self.assertIn('--draft', gh.call_args_list[0].args)
        self.assertIn('--draft=false', gh.call_args_list[-1].args)

    @patch('publish.gh')
    @patch('publish.release')
    def test_upload_failure_or_remote_corruption_stays_draft(self, release, gh):
        release.return_value = None
        gh.side_effect = [None, subprocess.CalledProcessError(1, 'gh')]
        with self.assertRaises(subprocess.CalledProcessError):
            publish.publish_date(self.folder, '2026-11-01')
        self.assertFalse(any('--draft=false' in call.args for call in gh.call_args_list))
        gh.reset_mock(side_effect=True)
        release.side_effect = [None, {'draft': True, 'assets': [{'name': n, 'size': 1, 'digest': 'wrong'}
                                                              for n in publish.expected_names()]}]
        with self.assertRaises(ValueError):
            publish.publish_date(self.folder, '2026-11-01')
        self.assertFalse(any('--draft=false' in call.args for call in gh.call_args_list))

    @patch('publish.publish_date')
    def test_one_failed_date_does_not_block_complete_date(self, publish_date):
        publish_date.side_effect = [ValueError('incomplete'), None]
        with patch('sys.argv', ['publish.py', '--date', '2026-11-01', '--output', self.temp.name]):
            with self.assertRaises(SystemExit) as result:
                publish.main()
        self.assertEqual(result.exception.code, 1)
        self.assertEqual(publish_date.call_count, 2)


if __name__ == '__main__':
    unittest.main()
