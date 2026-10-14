#!/usr/bin/env node
/**
 * Docs screenshots and CI only: answers like the Supertext AI file translation API v1 and
 * returns Swiss German, French and Italian for the demo's sample product and product model
 * (samples.json: translations of the sample content, keyed by the target's primary subtag).
 * Unknown text comes back as "[<target>] text". Like the real API, it accepts only Supertext's own
 * codes (regional targets for de/en/fr/it/pt, zh-Hans/zh-Hant, sr-Latn/sr-Cyrl, gsw-u-sd-…, bare for
 * the others; bare sources) and rejects anything else with INVALID_LANGUAGE_PAIR.
 *
 * It also stands in for the Supertext Commando Center: POST /report (or /api/v1/report) answers 202
 * and keeps the report; GET /reports lists them (start the demo with
 * SUPERTEXT_REPORT_URL=http://127.0.0.1:8765/report so nothing reaches the live center).
 * Listens on :8765 (any API key).
 */
import http from 'node:http'
import { randomBytes } from 'node:crypto'
import { readFileSync } from 'node:fs'

const norm = (s) => s.replace(/\s+/g, ' ').replace(/> </g, '><').trim()
const decode = (s) => s.replace(/&quot;/g, '"').replace(/&#0?39;|&apos;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&')
const encode = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
const samples = Object.fromEntries(
  Object.entries(JSON.parse(readFileSync(new URL('samples.json', import.meta.url), 'utf8'))).map(([lang, pairs]) => [
    lang,
    new Map(Object.entries(pairs).map(([k, v]) => [norm(k), v])),
  ]),
)
const files = new Map()
const reports = []
// Supertext's target codes (GET features, October 2026): regional only where Supertext has regions.
const REGIONAL = /^(de-(AT|CH|DE)|en-(GB|US)|fr-(CH|FR)|it-(CH|IT)|pt-(BR|PT)|zh-(Hans|Hant)|sr-(Cyrl|Latn)|gsw-u-sd-(chzh|chbe))$/
const BARE = /^(bg|cs|da|el|es|fi|hr|hu|ja|ko|nb|nl|pl|rm|ru|sk|sl|sq|sv|tr)$/
const SOURCES = /^(bg|cs|da|de|el|en|es|fi|fr|gsw|hr|hu|it|ja|ko|nb|nl|pl|pt|rm|ru|sk|sl|sq|sr|sv|tr|zh)$/

http
  .createServer(async (req, res) => {
    const path = new URL(req.url, 'http://x').pathname.replace(/^\/v1/, '')
    const send = (status, body, type = 'application/json') => {
      res.writeHead(status, { 'Content-Type': type })
      res.end(type === 'application/json' ? JSON.stringify(body) : body)
    }
    if (req.method === 'POST' && (path === '/report' || path === '/api/v1/report')) {
      const chunks = []
      for await (const chunk of req) chunks.push(chunk)
      try { reports.push(JSON.parse(Buffer.concat(chunks).toString('utf8'))) } catch { return send(400, { ok: false, error: 'not JSON' }) }
      return send(202, { ok: true })
    }
    if (req.method === 'GET' && path === '/reports') return send(200, reports)
    if (!/^Supertext-Auth-Key \S+$/.test(req.headers.authorization || '')) return send(401, { detail: 'missing key' })
    if (path === '/features') return send(200, {})
    if (req.method === 'POST') {
      const chunks = []
      for await (const chunk of req) chunks.push(chunk)
      const form = await new Request('http://x', { method: 'POST', headers: req.headers, body: Buffer.concat(chunks) }).formData()
      const file = form.get('file')
      if (!file || file.type !== 'text/html') return send(415, { detail: 'FILETYPE_NOT_ALLOWED' })
      // Like the real API: only Supertext's codes ("de" or "es-ES" as target, "en-US" as source are rejected).
      const target = String(form.get('target_lang') ?? '')
      const source = form.get('source_lang')
      if (!(REGIONAL.test(target) || BARE.test(target)) || (source !== null && !SOURCES.test(String(source)))) {
        return send(400, { detail: 'INVALID_LANGUAGE_PAIR' })
      }
      const id = randomBytes(6).toString('hex')
      files.set(id, { html: await file.text(), target })
      return send(200, { file_id: id })
    }
    const match = path.match(/file\/([a-f0-9]+)(\/status|\/translation)?$/)
    const file = match && files.get(match[1])
    if (!file) return send(404, {})
    if (req.method === 'DELETE') { files.delete(match[1]); return send(200, {}) }
    if (match[2] === '/status') return send(200, { status: 'done' })
    const known = samples[file.target.split('-')[0]] ?? new Map()
    const html = file.html.replace(/(<div data-st-id="\d+">)([\s\S]*?)(<\/div>\n)/g, (all, open, inner, close) => {
      const hit = known.get(norm(inner)) ?? known.get(norm(decode(inner)))
      if (hit !== undefined) return open + (known.has(norm(inner)) ? hit : encode(hit)) + close
      return open + inner.replace(/>([^<]+)</g, (m, t) => (t.trim() ? `>[${file.target}] ${t}<` : m)).replace(/^([^<]+)/, `[${file.target}] $1`) + close
    })
    send(200, html, 'text/html')
  })
  .listen(Number(process.env.PORT || 8765), '127.0.0.1', () => console.log(`Stand-in API on http://127.0.0.1:${process.env.PORT || 8765}/v1/`))
