#!/usr/bin/env node
// Builds l10n/<lang>.json (backend) and l10n/<lang>.js (frontend) from
// bin/translations-<lang>.json and fails if a string used in the code
// has no translation. Run: npm run l10n
import { readFileSync, readdirSync, writeFileSync } from 'node:fs'
import { join } from 'node:path'

const root = new URL('..', import.meta.url).pathname
const pluralForm = 'nplurals=2; plural=(n != 1);'

function collectSourceStrings(dir, found = new Set()) {
	for (const entry of readdirSync(dir, { withFileTypes: true })) {
		const path = join(dir, entry.name)
		if (entry.isDirectory()) {
			collectSourceStrings(path, found)
		} else if (/\.php$/.test(entry.name)) {
			// backend: $l->t('...') in notifications etc.
			for (const match of readFileSync(path, 'utf8').matchAll(/->t\('((?:[^'\\]|\\.)*)'/g)) {
				found.add(match[1].replace(/\\'/g, "'"))
			}
		} else if (/\.(vue|js)$/.test(entry.name)) {
			const source = readFileSync(path, 'utf8')
			const unescape = (text) => text.replace(/\\'/g, "'")
			for (const match of source.matchAll(/\bt\('vaccinations', '((?:[^'\\]|\\.)*)'/g)) {
				found.add(unescape(match[1]))
			}
			// plurals: n('vaccinations', singular, plural, count) → key "_singular_::_plural_"
			for (const match of source.matchAll(/\bn\('vaccinations', '((?:[^'\\]|\\.)*)', '((?:[^'\\]|\\.)*)'/g)) {
				found.add(`_${unescape(match[1])}_::_${unescape(match[2])}_`)
			}
		}
	}
	return found
}

const sourceStrings = collectSourceStrings(join(root, 'src'))
collectSourceStrings(join(root, 'lib'), sourceStrings)
sourceStrings.add('Vaccinations') // navigation entry in appinfo/info.xml

// de = informal (Du), de_DE = formal (Sie) in Nextcloud; same texts apart from the address
const formal = {
	'Du': 'Sie',
	'Trage die Einträge aus dem Impfpass ein – am besten mit den neuesten beginnen.': 'Tragen Sie die Einträge aus dem Impfpass ein – am besten mit den neuesten beginnen.',
	'Gib das Geburtsdatum ein, damit die Impfungen mit dem Impfkalender abgeglichen werden können.': 'Geben Sie das Geburtsdatum ein, damit die Impfungen mit dem Impfkalender abgeglichen werden können.',
}
const variants = {
	de: (text) => text,
	de_DE: (text) => (typeof text === 'string' ? formal[text] ?? text : text),
}

const translations = JSON.parse(readFileSync(join(root, 'bin/translations-de.json'), 'utf8'))
const missing = [...sourceStrings].filter((text) => !(text in translations))
const unused = Object.keys(translations).filter((text) => !sourceStrings.has(text))
if (unused.length > 0) {
	console.error('Translations no longer used in the code (remove them):\n' + unused.map((text) => `  - ${text}`).join('\n'))
	process.exit(1)
}
if (missing.length > 0) {
	console.error('Missing German translations:\n' + missing.map((text) => `  - ${text}`).join('\n'))
	process.exit(1)
}

for (const [language, transform] of Object.entries(variants)) {
	const localized = Object.fromEntries(Object.entries(translations).map(([key, value]) => [key, transform(value)]))
	writeFileSync(join(root, `l10n/${language}.json`), JSON.stringify({ translations: localized, pluralForm }, null, '\t') + '\n')
	writeFileSync(join(root, `l10n/${language}.js`),
		`OC.L10N.register(\n\t"vaccinations",\n\t${JSON.stringify(localized, null, '\t').replace(/\n/g, '\n\t')},\n\t"${pluralForm}"\n);\n`)
}
console.log(`✓ ${sourceStrings.size} strings, l10n written for ${Object.keys(variants).join(', ')}`)
