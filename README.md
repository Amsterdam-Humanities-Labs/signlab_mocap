# signlab_mocap — Motion Capture NGT Recordings

The capture register for the SignCollect motion-capture pipeline: which NGT
recordings exist, what state each one is in, and the scripts that get external
capture data into the database.

## What it does

Two browser pages and a set of JSON endpoints over the MySQL tables that
describe motion capture takes.

- `index.html` — the register. Paged, searchable (by gloss) and filterable by
  date over `mocap_files`, with a modal per record that plays the take video
  and the LiveLink metadata video and embeds a Babylon.js viewer for the take's
  GLB. Served by `getRecords.php`; `get50mocapfiles.php` is the unpaged variant.
- `opnameLijst.html` — the recording list ("opnamelijst"): what still has to be
  captured, grouped by theme. Backed by `getCaptures.php` (`action=list`,
  `update_captured`, `update_video_url`) and `addCaptures.php`, over the
  `captures` table whose schema is in `database.sql`.
- `index_csl.html` with `getCSLRecords.php` and `updateCSLRecord.php` — a
  second, separate record set over the `csl_glosses` table.
  <!-- TODO: confirm what CSL stands for here. The committed reference lists
       (lsc_topics2.csv, situacions_comunicatives_filenames.csv) are Catalan,
       which suggests LSC / Catalan Sign Language, but nothing in the code says so. -->
- `fetch_all.php` — aggregate counts and gloss pickers over `mocap_data`, used
  by mocapStudio as well as by this repo's own pages.
- `uploadOBS.php` — accepts an OBS video plus thumbnail and writes them into
  `gebarenoverleg_media/mocapVideos/`.

Alongside the web code sit three batch scripts that are *not* part of the web
request path:

- `matchRecords.py` — matches LiveLink JSON against the FBX files and writes the
  result to `mocap_files`.
- `matchVicon.py` — matches Vicon FBX/CSV pairs to existing database records.
- `convert.py` — converts LiveLink take videos out of the `studioFiles/takes*`
  directories.
- `fbxtoglb.js` — batch FBX→GLB conversion with `fbx2gltf` (Node, not deployed
  as a page).

`vicon_avatar.glb` and `untitled.glb` are viewer assets; the `.csv` files are
reference vocabulary and topic lists.

## Where it runs

The **signcollect core server** (the production VPS), at `/web/mocap`, served
as `https://signcollect.nl/mocap/`. On the demo hosts it is `/web/mocap`
(dev2) and `/srv/signcollect/web/mocap` (dev-1).

The three Python scripts run on that **same server**, not on the Vicon PC:
they read absolute `/web/gebarenoverleg_media/...` paths, connect to MySQL on
`localhost`, and register a heartbeat with the client monitor as scheduled
jobs. They are scheduled by `signlab_pythonCron` (which expects them under
`/home/gomer/pythonCron`'s client-monitor conventions), not by anything in
this repo. The Vicon PC is the Windows recording machine upstream of all of
this; nothing in this repository executes there.

## Status

**Production.**

## How to run or deploy it

There is no build step. Deployment is a git clone performed by the stack:
`signlab_signcollect-stack`'s `interface_deploy/scripts/repos.tsv` lists

    mocap	signlab_mocap	main

and `install.sh` / `host-bootstrap.sh` clone this repository onto the host and
rsync it into `<webroot>/mocap`. To work on it locally, serve the directory
with PHP and MySQL available; there is nothing to compile.

Note that `index.html` hardcodes `https://signcollect.nl/mocap/getRecords.php`
and several `https://signcollect.nl/gebarenoverleg_media/...` asset URLs. On a
demo host those are rewritten to the host's own domain by the stack's
`rewrite-urls.sh` at deploy time, so the demo has no path back to production.

## Configuration

Nothing secret is in git.

- **`mysql_config.php`** — every PHP endpoint here does
  `include('../mysql_config.php')`, i.e. one level *above* this directory, at
  the docroot root (`/web/mysql_config.php`). It defines `$servername`,
  `$username`, `$password`, `$database` and is created per host by the deploy
  (`host-config.sh`); Apache is configured to return 403 for it.
- **`db_credentials.py`** — the Python scripts import `DB_PASSWORD` from it.
  Gitignored, and lives with the scripts on the host.
- **`SC_WEB_ROOT`** — `sc_paths.php` (a vendored copy of signcollect-lib's
  resolver, used by `uploadOBS.php`) finds `lib/paths.php` if the library is
  deployed next to it and otherwise falls back to `/web`. `fbxtoglb.js` reads
  the same value from the process environment. Do not edit `sc_paths.php` here
  — it is byte-identical across repos and is checksummed by the stack's
  `tests/path-test.sh`; edit the copy in `signlab_signcollect-lib`.

## Dependencies

- **MySQL** database `admin_gebarenoverleg` on localhost — tables `mocap_files`,
  `mocap_data`, `captures`, `csl_glosses`.
- **`signlab_signcollect-lib`** — deployed as `/web/lib`; optional (there is a
  hardcoded `/web` fallback) but it is what makes the install root movable.
- **`gebarenoverleg_media`** — the media tree this repo reads from and writes
  to. On the demo hosts it is supplied by `signlab_demo-media`.
- **`signlab_pythonCron`** — schedules `matchRecords.py` and `matchVicon.py`.
- **Consumed by `signlab_mocapStudio`**, which fetches `../mocap/getCaptures.php`
  and `../mocap/fetch_all.php` and links to `opnameLijst.html`. If this repo is
  absent, the studio recording page loads with an empty form.
- **`/userProtect.js`** — the estate's shared login guard, loaded from the
  docroot root by all three HTML pages here. It is not in this repository.
- The mocap portal at `mocap.signcollect.nl` is served by the separate
  `mocap_site` repository, not by this one.
