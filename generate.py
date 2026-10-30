"""Generate date-matched Kokoro recordings from the church's public narration API."""
import argparse
import datetime as dt
import hashlib
import json
import os
from pathlib import Path
import subprocess
import time
from urllib.parse import urlencode
from urllib.request import Request, urlopen
from zoneinfo import ZoneInfo
from study import make_study, enrich, language_model
import gc

REPO = "bundrwoSPEC/st-peters-daily-office-audio"
VOICES = {"michael": "am_michael", "heart": "af_heart"}


def get_json(url):
    for attempt in range(3):
        try:
            req = Request(url, headers={"User-Agent": "StPetersDailyOffice/1.0"})
            with urlopen(req, timeout=60) as response:
                return json.load(response)
        except Exception:
            if attempt == 2:
                raise
            time.sleep(3 * (attempt + 1))


def groups(blocks):
    """Join short verse lines into sentences, keeping headings as separate groups."""
    result = []
    pending = []
    start = 0
    for i, block in enumerate(blocks):
        text = block["text"].strip()
        if not text:
            continue
        if block["kind"] == "heading":
            if pending:
                result.append((" ".join(pending), start, i - 1))
                pending = []
            result.append((text + ".", i, i))
        else:
            if not pending:
                start = i
            pending.append(text)
            if text.endswith((".", "!", "?", '.”', '."')) or len(" ".join(pending)) > 550:
                result.append((" ".join(pending), start, i))
                pending = []
    if pending:
        result.append((" ".join(pending), start, len(blocks) - 1))
    return result


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--office", required=True)
    parser.add_argument("--rite", choices=["Rite-I", "Rite-II"], required=True)
    parser.add_argument("--date", default="")
    args = parser.parse_args()
    today = dt.date.fromisoformat(args.date) if args.date else dt.datetime.now(ZoneInfo("America/Chicago")).date()
    import numpy as np
    import soundfile as sf
    import torch
    from kokoro import KPipeline
    torch.set_num_threads(2)
    prepared = []
    for day in [today, today + dt.timedelta(days=1)]:
        date = day.isoformat()
        params = urlencode({"date": date, "office": args.office, "rite": args.rite})
        data = get_json("https://stpetersbrenham.org/wp-json/stp-office/v1/narration?" + params)
        if data.get("date") != date or data.get("office") != args.office or len(data.get("blocks", [])) < 8:
            raise ValueError("The narration did not match the requested date and service")
        study = make_study(data)
        prepared.append(enrich(data, study))
        print(f"Prepared reflection for {date} {args.office}", flush=True)
    language_model.cache_clear()
    gc.collect()
    pipeline = KPipeline(lang_code="a", repo_id="hexgrad/Kokoro-82M", device="cpu")
    for data in prepared:
        date = data["date"]
        blocks = data["blocks"]
        digest = hashlib.sha256(json.dumps(blocks, ensure_ascii=False).encode()).hexdigest()
        folder = Path("output") / date
        folder.mkdir(parents=True, exist_ok=True)
        for label, voice in VOICES.items():
            stem = f"{args.office}-{args.rite}-{label}-guide-v1"
            samples = 0
            cues = []
            wav = folder / (stem + ".wav")
            with sf.SoundFile(wav, "w", samplerate=24000, channels=1, subtype="PCM_16") as out:
                for text, first, last in groups(blocks):
                    start = samples / 24000
                    produced = False
                    for _, _, audio in pipeline(text, voice=voice, speed=0.96):
                        audio = np.asarray(audio, dtype=np.float32)
                        if audio.size:
                            out.write(audio)
                            samples += audio.size
                            produced = True
                    if not produced:
                        raise ValueError("Voice returned empty audio")
                    pause = np.zeros(7200 if first == last and blocks[first]["kind"] == "heading" else 3600, dtype=np.float32)
                    out.write(pause)
                    samples += pause.size
                    cues.append({"start": round(start, 3), "end": round(samples / 24000, 3), "first": first, "last": last})
            mp3 = folder / (stem + ".mp3")
            subprocess.run(["ffmpeg", "-nostdin", "-y", "-loglevel", "error", "-i", str(wav), "-codec:a", "libmp3lame", "-b:a", "64k", str(mp3)], check=True)
            wav.unlink()
            if mp3.stat().st_size < 10000:
                raise ValueError("Recording is unexpectedly short")
            boundaries = {name: next(c["start"] for c in cues if c["first"] >= index) for name, index in {"office_start": data["segments"]["office_first"], "study_start": data["segments"]["study_first"]}.items()}
            manifest = {**data, "timing": boundaries, "voice": label, "duration": round(samples / 24000, 3), "text_sha256": digest, "generated_at": dt.datetime.now(dt.timezone.utc).isoformat(), "audio_url": f"https://github.com/{REPO}/releases/download/office-{date}/{stem}.mp3", "cues": cues}
            (folder / (stem + ".json")).write_text(json.dumps(manifest, ensure_ascii=False), encoding="utf-8")
            print(f"Prepared {date} {stem}: {samples / 24000:.0f} seconds", flush=True)


if __name__ == "__main__":
    main()
