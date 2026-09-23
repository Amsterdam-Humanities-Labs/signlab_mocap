# signlab_mocap
The register of the mocap pipeline: which NGT recordings exist and what state they are in. It also holds the scripts that load recording data into MySQL.

## What it does
- `index.html` with `getRecords.php`: a searchable register of `mocap_files`, page by page. A pop-up plays the recording and its LiveLink video and shows the GLB in a Babylon.js viewer.
- `opnameLijst.html` with `getCaptures.php` and `addCaptures.php`: what still needs to be recorded, per theme. Table `captures`; the schema is in `database.sql`.
- `index_csl.html` with `getCSLRecords.php` and `updateCSLRecord.php`: a separate register for `csl_glosses`.
- `fetch_all.php`: counts and gloss pickers over `mocap_data`. mocapStudio uses it too.
- `getAllMocapFiles.php`: every `mocap_files` row as JSON, newest first. No repo calls it. The old name `get50mocapfiles.php` is a stub.
- `uploadOBS.php`: uploads an OBS video to `gebarenoverleg_media/mocapVideos/`. It needs the header `X-Api-Token: $SC_UPLOAD_TOKEN`, set in the env file (read with `sc_env()`) or with `SetEnv`.
- Batch scripts, not web pages: `matchRecords.py` (LiveLink JSON into `mocap_files`), `matchVicon.py` (pairs of Vicon FBX and CSV), `convert.py` (LiveLink recording videos) and `fbxtoglb.js`.

## Where it runs
Core server: `/web/mocap`, https://signcollect.nl/mocap/. The Python scripts also run there.
Demo hosts: dev2 `/web/mocap`, dev-1 `/srv/signcollect/web/mocap`.

## Status
Production.

## How to run / deploy
[signlab_signcollect-stack](https://github.com/Amsterdam-Humanities-Labs/signlab_signcollect-stack) deploys it (`repos.tsv` row `mocap`). There is no build step.
[signlab_pythonCron](https://github.com/Amsterdam-Humanities-Labs/signlab_pythonCron) schedules the Python scripts.

## Configuration
- `../mysql_config.php` at the docroot (not in git). Every PHP endpoint uses it.
- `db_credentials.py` (not in git): `DB_PASSWORD` for the Python scripts.
- `SC_WEB_ROOT` (environment or `/web/.env`). `sc_paths.php` and `sc_paths.py` are copied from signcollect-lib; edit them there, not here.

## Dependencies
- MySQL `admin_gebarenoverleg`: `mocap_files`, `mocap_data`, `captures`, `csl_glosses`.
- `gebarenoverleg_media` (on demo hosts: [signlab_demo-media](https://github.com/Amsterdam-Humanities-Labs/signlab_demo-media)). [signlab_signcollect-lib](https://github.com/Amsterdam-Humanities-Labs/signlab_signcollect-lib) at `/web/lib` is optional.
- The Python scripts import `ClientMonitor` from `signlab_client_monitor`. If that package is missing they fall back to `/home/gomer/pythonCron/python_client.py`.
- [signlab_mocapStudio](https://github.com/Amsterdam-Humanities-Labs/signlab_mocapStudio) uses it. The pages load `/userProtect.js` from the docroot.
