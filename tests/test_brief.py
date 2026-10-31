import hashlib
import json
from pathlib import Path
import tempfile
import unittest
from devotions import DEVOTIONS, devotion
from publish_brief import validate


class BriefValidationTests(unittest.TestCase):
    def fixture(self, folder):
        for office in DEVOTIONS:
            for voice in ('michael','heart'):
                data = devotion(office)
                data['voice'] = voice
                stem = f'{office}-{voice}-brief-v1'
                # Synthetic bytes test inventory/checksum validation, not speech quality.
                sound = b'ID3' + b'0' * 11000
                (folder / (stem + '.mp3')).write_bytes(sound)
                data['audio_url'] = f'https://github.com/bundrwoSPEC/st-peters-daily-office-audio/releases/download/brief-devotions-v2/{stem}.mp3'
                data['audio_sha256'] = hashlib.sha256(sound).hexdigest()
                data['text_sha256'] = hashlib.sha256(json.dumps(data['blocks'],ensure_ascii=False).encode()).hexdigest()
                data['cues'] = [{'start':i,'end':i+1,'first':i,'last':i} for i in range(len(data['blocks']))]
                data['duration'] = len(data['blocks'])
                data['timing'] = {'office_start':data['segments']['office_first'],'study_start':data['segments']['study_first'],'family_pause':data['family_pause_after']+1}
                data['listening'] = {}
                for key, suffix in [('intro_prayer','-prayer'),('prayer_only','-prayer-only'),('prayer_guide','-without-intro'),('intro_prayer_guide','')]:
                    name=stem+suffix+'.mp3';(folder/name).write_bytes(sound)
                    data['listening'][key]={'file':name,'url':f'https://github.com/bundrwoSPEC/st-peters-daily-office-audio/releases/download/brief-devotions-v2/{name}','duration':10,'sha256':hashlib.sha256(sound).hexdigest()}
                (folder / (stem + '.json')).write_text(json.dumps(data))

    def test_complete_inventory(self):
        with tempfile.TemporaryDirectory() as directory:
            folder=Path(directory);self.fixture(folder)
            self.assertEqual(len(validate(folder)),40)

    def test_missing_recording_rejected(self):
        with tempfile.TemporaryDirectory() as directory:
            folder=Path(directory);self.fixture(folder)
            next(folder.glob('*.mp3')).unlink()
            with self.assertRaisesRegex(ValueError,'40 brief-devotion'):
                validate(folder)

    def test_wrong_text_rejected(self):
        with tempfile.TemporaryDirectory() as directory:
            folder=Path(directory);self.fixture(folder)
            p=next(folder.glob('*.json'));data=json.loads(p.read_text());data['blocks'][3]['text']='Wrong prayer';p.write_text(json.dumps(data))
            with self.assertRaisesRegex(ValueError,'text or structure'):
                validate(folder)

    def test_incorrect_pause_rejected(self):
        with tempfile.TemporaryDirectory() as directory:
            folder=Path(directory);self.fixture(folder)
            p=next(folder.glob('*.json'));data=json.loads(p.read_text());data['timing']['family_pause']=999;p.write_text(json.dumps(data))
            with self.assertRaisesRegex(ValueError,'family pause'):
                validate(folder)


if __name__=='__main__':
    unittest.main()
