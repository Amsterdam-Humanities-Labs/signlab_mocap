# signlab_mocap
Capture register for the mocap pipeline: which NGT recordings exist, their state, and scripts that load capture data into MySQL.

## What it does
- `index.html` + `getRecords.php`: paged/searchable register over `mocap_files`; modal plays take + LiveLink video and a Babylon.js GLB viewer.
- `opnameLijst.html` + `getCaptures.php`, `addCaptures.php`: what still has to be captured, per theme (`captures`, schema in `database.sql`).
- `index_csl.html` + `getCSLRecords.php`, `updateCSLRecord.php`: separate record set over `csl_glosses`.
- `fetch_all.php`: counts and gloss pickers over `mocap_data` (also used by mocapStudio). `uploadOBS.php`: OBS video upload into `mocapVideos/`.
- Batch scripts (not web): `matchRecords.py` (LiveLink JSON to `mocap_files`), `matchVicon.py` (Vicon FBX/CSV pairs), `convert.py` (LiveLink take videos), `fbxtoglb.js`.

## Where it runs
core (production): `/web/mocap`, https://signcollect.nl/mocap/. Demo: dev2 `/web/mocap`, dev-1 `/srv/signcollect/web/mocap`. The Python scripts run on core too.

## Status
production

## How to run / deploy
Deployed by the stack (repos.tsv row `mocap`): https://github.com/Amsterdam-Humanities-Labs/signlab_signcollect-stack
No build step. The Python scripts are scheduled by signlab_pythonCron.

## Configuration
- `../mysql_config.php` (docroot, not in git): used by every PHP endpoint.
- `db_credentials.py` (not in git): `DB_PASSWORD` for the Python scripts.
- `SC_WEB_ROOT` (env or `/web/.env`) / vendored `sc_paths.php` and `sc_paths.py` (edit them in signcollect-lib, not here).

## Dependencies
- MySQL `admin_gebarenoverleg`: `mocap_files`, `mocap_data`, `captures`, `csl_glosses`.
- `gebarenoverleg_media` (demo: signlab_demo-media); signlab_signcollect-lib (optional, `/web/lib`).
- signlab_pythonCron schedules the scripts; `ClientMonitor` falls back to `/home/gomer/pythonCron/python_client.py`.
- Used by signlab_mocapStudio. Pages load `/userProtect.js` from the docroot.
