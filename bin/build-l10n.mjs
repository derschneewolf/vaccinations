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
		} else if (/\.(vue|js)$/.test(entry.name)) {
			for (const match of readFileSync(path, 'utf8').matchAll(/t\('vaccinations', '((?:[^'\\]|\\.)*)'/g)) {
				found.add(match[1].replace(/\\'/g, "'"))
			}
		}
	}
	return found
}

const sourceStrings = collectSourceStrings(join(root, 'src'))
sourceStrings.add('Vaccinations') // navigation entry in appinfo/info.xml

// de = informal (Du), de_DE = formal (Sie) in Nextcloud; same texts apart from the address
const variants = {
	de: (text) => text,
	de_DE: (text) => text
		.replace(/^Du$/, 'Sie')
		.replace('Trage die Einträge aus dem Impfpass ein – am besten mit den neuesten beginnen.', 'Tragen Sie die Einträge aus dem Impfpass ein – am besten mit den neuesten beginnen.'),
}

const translations = JSON.parse(readFileSync(join(root, 'bin/translations-de.json'), 'utf8'))
const missing = [...sourceStrings].filter((text) => !(text in translations))
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
