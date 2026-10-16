# Changelog

All notable changes to Supertext Translation for AtroPIM.

## Unreleased

### Changed
- **Language codes: no guessing.** Supertext accepts only its own codes: German, English, French, Italian and Portuguese with a region (`de-CH`, `en-US`, `pt-BR`), `zh-Hans`/`zh-Hant`, `sr-Latn`/`sr-Cyrl`, Swiss German dialects, and every other language **without** a region (`es`, `nl`, `sl`, …). The module no longer adds default regions (`es-ES`, `sl-SI`, `zh-CN` were rejected by Supertext). A language is sent with the code in the connection's **Language mapping** (formerly *Language codes*, same field), else with the Supertext code it equals apart from case and `_`/`-` (`de_CH` → `de-CH`); any other language fails with "The language … has no Supertext language yet" and nothing is sent. Sites with languages such as `es_ES`, `nl_NL`, `zh_CN` or a bare `de` need a line in the mapping (`es_ES = es`).
- The bundled Supertext SDK is now version 0.3.0 (its `V1` namespace and the API's language list).
- Version reports, the API key check result and the reachability ping now use Supertext Plugin Health 0.1.0 (bundled in `lib/supertext-plugin-health/`); Supertext SDK updated to 0.3.0. No change for sites: settings and stored state stay as they are.

### Added
- Support for PHP 8.5 (AtroCore 2.4.7 runs on PHP 8.4 and 8.5); lint and unit tests run on both.
- Read-only field **Languages** on the Supertext connection: each AtroPIM language with the Supertext code it is sent as, or *not mapped*.
- Formal/informal address is sent only for languages where Supertext offers it; for others (e.g. English) the result says "Supertext offers no formal or informal tone for … : translated with the default tone."
- New messages "The language … has no Supertext language yet …" and "Supertext offers no formal or informal tone for this language …", in English, German, French and Italian.
- Automatic API key check: when the connection is saved, on *Test connection*, on `supertext check` and when Supertext rejects the key; the latest result and its time are shown in the connection's new read-only field **API key check** (with the account and API key links for a rejected or missing key). *Test connection* now shows the check's message.
- Setting **Send version information to Supertext** (on by default; `SUPERTEXT_VERSION_REPORTS=0` turns it off for the whole installation): sends the site URL, the module, AtroPIM, PHP and SDK versions, whether a key is set and the key check's result to the Supertext Commando Center when the connection is saved, after an update and when the key check's result changes. No content, no API key and no personal data. `supertext check` sends a `check` report.
- Public ping endpoint `GET /api/supertext/ping` for the Commando Center (answers only while version reports are on).
- French and Italian interface (and German where it was missing): labels, tooltips and options, plus the action results and error messages, in the user's interface language.
- First version for AtroCore 2.4 and AtroPIM 1.16 (tested with AtroCore 2.4.7 and AtroPIM 1.16.10). All Supertext API calls go through the official Supertext PHP SDK, shipped inside the module (nothing extra to install).
- Its own message for an unsupported language pair, naming the codes ("Supertext does not support this language pair (en → xx-YY)"), in English, German, French and Italian.
- Action type *Translate with Supertext*: a button on the record (or in its *More* menu) and a mass action in the list, for products or any other entity; runs in the background with *Execute in background*; every run is logged under the action's *Executions*.
- Translates multilingual text, Markdown and rich text fields and attribute values, rich text with its formatting; fields that already have text in a target language are kept unless *Overwrite existing translations* is set.
- Action options: Supertext connection, source language, target languages, overwrite, form of address.
- Connection type *Supertext*: API key (encrypted by AtroCore, with or without the `Supertext-Auth-Key` prefix; the tooltip links to the Supertext signup and the API key page, *supertext.com → Integrations → API*, Admin role required), Live/Staging/Testing API or a custom URL, timeout, language mapping, *Test connection*. `SUPERTEXT_API_KEY` is used when the connection has no key; `SUPERTEXT_API_URL` overrides the URL.
- Saves through the entity's record service: AtroCore's permissions (*Execute as: Same user*), validation and history apply.
- The message after a run lists the result per language ("de_CH, fr_CH: translated (4 fields)").
- Console commands `supertext translate` and `supertext check` (with the installed version, linked to its GitHub release).
- Retries when the Supertext API answers HTTP 429 (rate limit).
- English and German labels.
- Demo for Railway (`demo/`) with demo accounts, four languages, a multilingual attribute, two sample products, the connection and the action created on every start.
