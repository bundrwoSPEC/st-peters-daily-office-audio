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
