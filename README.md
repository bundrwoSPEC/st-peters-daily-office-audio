# St. Peter’s Daily Office audio

Prepared Michael and Heart recordings for [Today’s Worship](https://stpetersbrenham.org/the-daily-office/todays-worship/), St. Peter’s Episcopal Church, Brenham, Texas.

The public GitHub Actions workflow runs each day at 08:17 UTC and prepares today and tomorrow using the America/Chicago calendar. Morning and Evening Prayer include Rite I and Rite II. Noonday Prayer and Compline use their own BCP forms. Michael (`am_michael`) is the default; Heart (`af_heart`) is selectable.

The workflow reads the church’s public `/wp-json/stp-office/v1/narration` endpoint, generates MP3s with Kokoro, and publishes dated releases. Each recording has a matching JSON transcript and timing cues. No WordPress password, paid speech API, or visitor-side model download is required. The repository must remain public for this delivery method.

`wordpress/prepared-audio.php` is installed as an active PHP Code Snippets entry on WordPress. It supplies a read-only `/wp-json/stp-office/v1/prepared-audio` endpoint and the natural voice player on Today’s Worship. It validates the selected date, service, rite, and voice before returning a recording. When a dated recording is missing, the original device voice remains available. The original page, prayer journey storage, and Forward Movement prayer window remain in place.

## Running and checking

Open **Actions → Prepare Daily Office voices → Run workflow** to retry. Leave the date blank for today in Brenham, or enter an ISO date to prepare that date and the following date. The six generation jobs must succeed before publication. Check a failed job’s logs for details. The first run installs the model and speech dependencies, so allow time before playback testing.

Releases are tagged `office-YYYY-MM-DD`. Assets use `<office>-<rite>-<voice>.mp3` and `.json`. The WordPress manifest cache lasts one hour for successful responses and two minutes for missing recordings. The player checks missing selections again every two minutes, and changing a date, service, rite, or voice cancels playback and loads that selection.

## Sources

Prayers: Book of Common Prayer (1979), The Episcopal Church. Public-domain scripture text and dated prayer text supplied by [Gracelight](https://usedailyoffice.com/1979/). The listening version can differ from the embedded Forward Movement prayer window. Voices are AI-generated using [Kokoro](https://github.com/hexgrad/kokoro), whose model weights are Apache licensed.

## Quiet daily prayer experience

The public page is a place for daily prayer and scripture, with the reassuring heading “Be still. God is near.” Warm ivory and candlelight colors frame a minimal opening: date, service selector, liturgical season, and Begin prayer. Site navigation and the footer are hidden only on this prayer page; a small church link returns home. Prayer settings starts closed and contains voice, pace, text size, Rite I/II, playback position, additional prayer resources, and the existing local prayer journal.

Starting playback reveals the exact transcript used to generate the selected recording. Stored timing cues highlight passages and gently scroll within the reading panel, rather than moving the whole page. Manual wheel, touch, or keyboard scrolling pauses following; Follow the reading returns to the current passage. Reduced-motion preferences disable smooth scrolling. Date, service, rite, and voice changes stop the current audio and load the matching recording. Reading completion offers an optional Mark this time as prayed action; it does not automatically record a prayer.

The WordPress integration keeps a reusable MP3 copy on the church domain for phone playback. All public listening controls use saved recordings. They never dispatch generation jobs, create new speech, or modify the daily recording schedule. Preserve this quiet opening and keep generation controls out of the public prayer experience.
