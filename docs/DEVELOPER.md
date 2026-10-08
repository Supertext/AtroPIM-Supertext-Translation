# Developer guide — Supertext Translation for AtroPIM

An AtroCore module (Composer package `supertext/atropim-supertext-translation`, AtroCore module id and PHP namespace `SupertextTranslation`). It works in every AtroCore application; AtroPIM is the one it is built and tested for.

## Architecture

AtroCore loads a module through `composer.json` → `extra.atroId`: the class `\SupertextTranslation\Module` and everything below `app/` by convention.

| Part | Files | What it does |
| --- | --- | --- |
| Module | `app/Module.php` | Load order (after AtroPIM) and the console commands. |
| Action type | `app/ActionTypes/SupertextTranslate.php`, `app/Resources/metadata/action/types.json` | The action type `supertextTranslate`. AtroCore's action framework provides the record button, the mass action, background jobs (*Execute in background*), *Execute as*, conditions and the execution log; `execute()` translates one record (mass actions call it once per record) and writes the summary to the execution and its log. |
| Action routes | `app/Handlers/Action/SupertextTranslate{,Async}Handler.php` | `POST /api/Action/{id}/supertextTranslate` and `…Async`, the URLs AtroCore's front end calls for an action type. |
| Action options | `app/Resources/metadata/entityDefs/Action.json`, `app/Listeners/ActionLayout.php`, `app/Listeners/Metadata.php` | Real columns on `action` (`supertext_connection_id`, `supertext_source_language`, `supertext_target_languages`, `supertext_overwrite`, `supertext_politeness`), visible for this type only; the layout listener adds them to the form, the metadata listener fills the language options from the configured languages. |
| Connection type | `app/ConnectionType/ConnectionSupertext.php`, `app/Resources/metadata/app/connectionTypes.json`, `entityDefs/Connection.json`, `app/Listeners/ConnectionLayout.php` | Connection type `supertext`: API key (a `password` field, encrypted by AtroCore), environment, URL, timeout, language codes, all stored in the connection's `data` JSON (`dataField`). *Test connection* calls `GET features`. |
| Result message | `app/Listeners/ActionService.php` | `afterExecuteNow`: replaces AtroCore's "Action … was executed" with the summary ("de_CH, fr_CH: translated (4 fields)", or counts for a mass action). |
| Translation | `app/Translation/EntityTranslator.php` | Loads the record through its record service, plans, sends one HTML document per target language, saves with the record service's `updateEntity()` (ACL, validation, hooks, history). |
| Planning | `app/Translation/Planner.php`, `TextUnit.php` | Which fields are translated (no AtroCore classes: unit-tested). |
| Settings | `app/Translation/Settings.php`, `ConnectionResolver.php` | Connection data → API key, base URL, timeout, language codes; `SUPERTEXT_API_KEY` / `SUPERTEXT_API_URL` fallbacks. |
| Console | `app/Console/Check.php`, `Translate.php`, `app/Translation/CommandArguments.php` | `supertext check`, `supertext translate <args>`. |
| API client | `app/Api/` | Supertext AI file translation API v1 (no AtroCore classes: unit-tested). Same client as the Akeneo bundle. |
| Translations | `app/Resources/i18n/{en_US,de_DE}/` | Labels, options and tooltips of the new fields. |

### Field rules

`Planner::units()` decides what is translated (keep [USER_GUIDE.md → What is translated](USER_GUIDE.md#what-is-translated) in sync):

- AtroCore stores a multilingual field as a base field in the main language (`name`) plus one field per additional language (`nameDeCh`) with `multilangField: name` and `multilangLocale: de_CH`. Attribute values follow the same model; `EntityTranslator` merges the entity's own `entityDefs` (where the attribute fields are) into the metadata field definitions.
- Translated: base fields with `isMultilang` of type `varchar`, `text`, `markdown` (plain text) and `wysiwyg` (HTML) that have at least one language sibling.
- Skipped: `readOnly`, `disabled`, `notStorable` and `emHidden` fields; every other type.
- A target field with text (after removing tags and whitespace) is kept unless *overwrite* is set.
- Plain text is sent HTML-escaped with line breaks as `<br>`; rich text as is. Each field is one `data-st-id` element (whole sentences), so formatting inside a paragraph stays inline.
- Plain-text translations longer than the field's `maxLength` are not saved (named in the message); the rest is saved in one `updateEntity()` per language. `NotModified` (the record already has exactly this text) counts as success.

## Supertext API protocol

Shared with the WordPress plugin and every other Supertext plugin:

1. `POST {base}translate/ai/file`: multipart with `file` (part `Content-Type` exactly `text/html`, no charset, or the API answers 415), `target_lang` (BCP-47, e.g. `de-CH`), optional `source_lang` (primary subtag only, e.g. `en`, or the pair is rejected), optional `politeness` (`more`/`less`). Returns `{file_id}`.
2. `GET …/{file_id}/status` until `done` (`error`, `limit_exceeded`, `deleted` are terminal).
3. `GET …/{file_id}/translation` returns the translated HTML.
4. `DELETE …/{file_id}` (files also expire after 24 h).

Auth header: `Authorization: Supertext-Auth-Key <key>`. The header name must be `Authorization` (`Authentication` gets 403). Supertext shows the key with the prefix, so the client strips a pasted `Supertext-Auth-Key ` and always sends exactly one. Base URLs: `https://api.supertext.com/v1/` (live), `https://api.staging.supertext.com/v1/`, `https://api.testing.supertext.com/v1/`. `GET features` is the cost-free key check (*Test connection*, `supertext check`).

**Rate limit:** the API limits requests per second per key (HTTP 429). The client retries a 429 up to 4 times, waiting for `Retry-After` if sent, otherwise 1, 2, 4 and 8 seconds plus jitter. Target languages are translated one after the other.

Language codes: an AtroCore language (`de_CH`) is sent as `de-CH` unless the connection's *Language codes* set another code. The source is sent as its primary subtag (`en`).

## Local development

The quickest setup is the demo image (below), which installs AtroPIM with the module. To work on the module in your own AtroCore project, add this repository as a Composer path repository and run AtroCore's update:

```json
"repositories": [{"type": "path", "url": "/path/to/AtroPIM-Supertext-Translation", "options": {"symlink": true}}]
```

```bash
php atrocore-installer.phar require supertext/atropim-supertext-translation:@dev
php console.php "clear cache"     # after changing metadata, layouts or listeners
```

New fields in `entityDefs` get their database columns from AtroCore's schema diff (`php console.php "sql diff --run"`; the installer's update runs it).

## Tests

```bash
phpunit                                                   # PHPUnit 11, no AtroCore needed
find app tests demo -name '*.php' -print0 | xargs -0 -n1 php -l
```

The unit tests cover the API client, the HTML document, chunking, the planner, the settings and the console arguments. `tests/demo-check.sh` is the end-to-end test: it starts the demo image twice against MySQL with the stand-in API (`tests/docs/stand-in.mjs`), checks the demo accounts, translates a product with the console command and the other one with the REST API as the editor, and checks the stored values. CI (`.github/workflows/ci.yml`) runs both.

## Demo (Railway)

`demo/` builds one image (`demo/Dockerfile`, build context = repository root; `railway.json` points Railway at it): PHP 8.4 + Apache with AtroCore 2.4.7, AtroPIM 1.16.10 and this module, plus AtroCore's job runner (`console.php cron` every minute). MySQL is a separate service.

On every start `demo/docker/entrypoint.sh`:

1. links `data/` and `upload/` to the volume at `/data` (AtroCore keeps its configuration, languages and the encryption key of connection passwords in `data/`, so without a volume a restart forgets them) and refreshes the module list and cache from the image;
2. `install.php`: creates the database `ATROPIM_DB_NAME` on the MySQL server if needed and installs AtroPIM on the first start, with `DEMO_ADMIN_EMAIL` as administrator (the installer empties that database, so it refuses `railway` and other shared names);
3. `sql diff --run` for columns of newer module versions;
4. `setup.php model`: the languages German, French and Italian (Switzerland) and the multilingual product attribute *Tasting notes*;
5. `setup.php content`: two sample products, the Supertext connection (no key: it uses `SUPERTEXT_API_KEY`), the action *Translate with Supertext* for products (*Execute as: Same user*), the role *Product editor (Supertext demo)* and the `DEMO_*` accounts.

Everything is created only if missing; existing records and accounts are never changed. Step 4 and 5 are separate processes because new languages and attributes change the metadata the next step needs.

| Variable | |
| --- | --- |
| `DEMO_ADMIN_EMAIL`, `DEMO_ADMIN_PASSWORD` | Administrator (user name = e-mail). Replaces AtroCore's installation screen: with `DEMO_*` set, it never appears. |
| `DEMO_EDITOR_EMAIL`, `DEMO_EDITOR_PASSWORD` | Role *Product editor (Supertext demo)*: reads and edits products (attribute values included) in every language, reads categories, brands, attributes, files and actions. AtroCore has no editor role out of the box. |
| `MYSQL_URL` | `mysql://user:password@host:3306/…`; the database name in the URL is ignored. Railway: `mysql://root:${{MySQL-8.MYSQL_ROOT_PASSWORD}}@mysql8.railway.internal:3306/railway`. |
| `ATROPIM_DB_NAME` | The demo's own database (default `atropim`). |
| `SUPERTEXT_API_KEY` | The Supertext key the demo's connection falls back to. |
| `SUPERTEXT_API_URL` | Only for tests against the stand-in. |
| `DEMO_SITE_URL` | Public URL, if it isn't `https://$RAILWAY_PUBLIC_DOMAIN`. |
| `PORT` | Set by Railway (default 8080). |

Passwords must meet AtroCore's rule (at least 8 characters with an upper-case letter, a digit and a special character); otherwise that account is skipped with a warning and the demo still starts. The log names variables, never values. Values live only in Railway's variables; `demo/.env.example` lists them.

Railway: project *supertext-cms-demos-php*, service *AtroPIM* (volume `atropim-data` at `/data`), database `atropim` on the shared *MySQL-8* service, <https://atropim-production.up.railway.app/>. Pushes to `main` deploy. The `DEMO_*` and `SUPERTEXT_API_KEY` variables reference the *Contao* service's; `DEMO_ADMIN_PASSWORD` is `${{Contao.DEMO_ADMIN_PASSWORD}}-A1`, because the shared password doesn't meet AtroCore's password rule. The first start takes about ten minutes (each new language rebuilds AtroCore's schema).

Locally:

```bash
docker build -f demo/Dockerfile -t supertext-atropim-demo .
docker run --rm -p 8080:8080 --env-file demo/.env -v atropim-data:/data supertext-atropim-demo
# http://localhost:8080 (the first start installs AtroPIM: about a minute)
docker exec <container> demo-console supertext check
```

## Docs screenshots

`tests/docs/screenshots.mjs` (Playwright) regenerates `docs/images/` from a **fresh** demo (no translations yet) whose `SUPERTEXT_API_URL` points at `tests/docs/stand-in.mjs`. The stand-in answers like the Supertext API and returns real German, French and Italian translations of the sample content (`tests/docs/samples.json`; new demo content needs entries there), so the guides never show placeholder text.

```bash
cd tests/docs && npm install
node stand-in.mjs &
# a fresh demo with SUPERTEXT_API_URL=http://127.0.0.1:8765/v1/ and DEMO_* set, on port 8095
BASE_URL=http://127.0.0.1:8095 DEMO_ADMIN_EMAIL=… DEMO_ADMIN_PASSWORD=… DEMO_EDITOR_EMAIL=… DEMO_EDITOR_PASSWORD=… node screenshots.mjs
```

The images are 1× and cropped to the relevant part. The connection's API URL isn't shown (the demo's connection is set to *Live*; the stand-in comes from the environment variable).

## Releasing

Releases are published by `.github/workflows/release.yml` when the version is officially bumped; nobody tags or creates releases by hand.

1. Move the *Unreleased* entries in `CHANGELOG.md` under a new `## X.Y.Z — YYYY-MM-DD` section, and keep an empty *Unreleased* above it.
2. There is no version field to change: Composer takes the version from the Git tag the workflow creates (don't add `version` to `composer.json`). AtroCore shows it under *Administration → Updates & Modules* (from Composer's lock file); `supertext check` prints it and links X.Y.Z versions to their GitHub release.
3. Push to `main`. The workflow tags `vX.Y.Z` and creates the GitHub release with the CHANGELOG section as notes (0.x versions as pre-releases). A push that adds no new version does nothing, and a version that is already released is skipped. After fixing a failed run, start it again with *Run workflow* on the *Release* workflow.

## Conventions

- PSR-12, PHP 8.4, strict types; keep `app/Api/`, `Planner`, `TextUnit`, `Settings` and `CommandArguments` free of AtroCore classes (the unit tests run without AtroCore).
- New action or connection options: `entityDefs/Action.json` or `Connection.json`, the layout listener, both `i18n` folders (label, tooltip, options) **and** the settings tables in [INSTALLATION.md](INSTALLATION.md#settings).
- The module id `SupertextTranslation`, the action type `supertextTranslate`, the connection type `supertext` and the field names `supertext*` are stored in users' databases; renaming them is a breaking change.
- Keep the three docs in `docs/` current with every change (see `CLAUDE.md`).

## Known limitations / roadmap

- Each run translates one record per language request; a mass action over many records takes a while (use *Execute in background*).
- Only multilingual text fields. Not yet: option labels of list fields, file names and descriptions, translatable labels of AtroCore's metadata.
- Source and target languages are set per action, not chosen by the editor when clicking.
- Not on a package registry yet (installed from GitHub).
- Human (professional) translation orders are not supported yet (the WordPress plugin has them).
