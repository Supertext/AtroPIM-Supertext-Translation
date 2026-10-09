# Changelog

All notable changes to Supertext Translation for AtroPIM.

## Unreleased

### Added
- French and Italian interface (and German where it was missing): labels, tooltips and options, plus the action results and error messages, in the user's interface language.
- First version for AtroCore 2.4 and AtroPIM 1.16 (tested with AtroCore 2.4.7 and AtroPIM 1.16.10).
- Action type *Translate with Supertext*: a button on the record (or in its *More* menu) and a mass action in the list, for products or any other entity; runs in the background with *Execute in background*; every run is logged under the action's *Executions*.
- Translates multilingual text, Markdown and rich text fields and attribute values, rich text with its formatting; fields that already have text in a target language are kept unless *Overwrite existing translations* is set.
- Action options: Supertext connection, source language, target languages, overwrite, form of address.
- Connection type *Supertext*: API key (encrypted by AtroCore, with or without the `Supertext-Auth-Key` prefix; the tooltip links to the Supertext signup and the API key page, *supertext.com → Integrations → API*, Admin role required), Live/Staging/Testing API or a custom URL, timeout, Supertext language codes, *Test connection*. `SUPERTEXT_API_KEY` is used when the connection has no key; `SUPERTEXT_API_URL` overrides the URL.
- Saves through the entity's record service: AtroCore's permissions (*Execute as: Same user*), validation and history apply.
- The message after a run lists the result per language ("de_CH, fr_CH: translated (4 fields)").
- Console commands `supertext translate` and `supertext check` (with the installed version, linked to its GitHub release).
- Retries when the Supertext API answers HTTP 429 (rate limit).
- English and German labels.
- Demo for Railway (`demo/`) with demo accounts, four languages, a multilingual attribute, two sample products, the connection and the action created on every start.
