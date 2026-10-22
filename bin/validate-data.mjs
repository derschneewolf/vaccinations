#!/usr/bin/env node
// Validates data/diseases.json and data/vaccines/*.json:
//  - JSON schema
//  - file name == id
//  - unique ids
//  - every referenced disease exists
// Run: npm run validate:data

import { readFileSync, readdirSync } from 'node:fs'
import { basename, join } from 'node:path'
import Ajv2020 from 'ajv/dist/2020.js'
import addFormats from 'ajv-formats'

const dataDir = new URL('../data/', import.meta.url).pathname
const readJson = (path) => JSON.parse(readFileSync(path, 'utf8'))

const ajv = new Ajv2020({ allErrors: true })
addFormats(ajv)
const validateVaccine = ajv.compile(readJson(join(dataDir, 'schema/vaccine.schema.json')))
const validateDiseases = ajv.compile(readJson(join(dataDir, 'schema/diseases.schema.json')))
const validateSchedule = ajv.compile(readJson(join(dataDir, 'schema/schedule.schema.json')))

const errors = []

const diseaseCatalogue = readJson(join(dataDir, 'diseases.json'))
if (!validateDiseases(diseaseCatalogue)) {
	errors.push(`diseases.json: ${ajv.errorsText(validateDiseases.errors)}`)
}
const diseaseIds = new Set()
for (const disease of diseaseCatalogue.diseases ?? []) {
	if (diseaseIds.has(disease.id)) errors.push(`diseases.json: duplicate id "${disease.id}"`)
	diseaseIds.add(disease.id)
}

const vaccineIds = new Set()
const vaccineFiles = readdirSync(join(dataDir, 'vaccines')).filter((file) => file.endsWith('.json'))
for (const file of vaccineFiles) {
	const vaccine = readJson(join(dataDir, 'vaccines', file))
	if (!validateVaccine(vaccine)) {
		errors.push(`vaccines/${file}: ${ajv.errorsText(validateVaccine.errors)}`)
		continue
	}
	if (basename(file, '.json') !== vaccine.id) errors.push(`vaccines/${file}: file name must be "${vaccine.id}.json"`)
	if (vaccineIds.has(vaccine.id)) errors.push(`vaccines/${file}: duplicate id "${vaccine.id}"`)
	vaccineIds.add(vaccine.id)
	for (const diseaseId of vaccine.diseases) {
		if (!diseaseIds.has(diseaseId)) errors.push(`vaccines/${file}: unknown disease "${diseaseId}"`)
	}
}

const scheduleFiles = readdirSync(join(dataDir, 'schedules')).filter((file) => file.endsWith('.json'))
for (const file of scheduleFiles) {
	const schedule = readJson(join(dataDir, 'schedules', file))
	if (!validateSchedule(schedule)) {
		errors.push(`schedules/${file}: ${ajv.errorsText(validateSchedule.errors)}`)
		continue
	}
	if (basename(file, '.json') !== schedule.id) errors.push(`schedules/${file}: file name must be "${schedule.id}.json"`)
	const ruleIds = new Set()
	for (const rule of schedule.rules) {
		if (ruleIds.has(rule.id)) errors.push(`schedules/${file}: duplicate rule id "${rule.id}"`)
		ruleIds.add(rule.id)
		for (const diseaseId of rule.diseases) {
			if (!diseaseIds.has(diseaseId)) errors.push(`schedules/${file}: rule "${rule.id}" uses unknown disease "${diseaseId}"`)
		}
	}
	for (const rule of schedule.rules) {
		if (rule.alignWith && !ruleIds.has(rule.alignWith)) errors.push(`schedules/${file}: rule "${rule.id}" aligns with unknown rule "${rule.alignWith}"`)
	}
}

if (errors.length > 0) {
	console.error(errors.map((error) => `✗ ${error}`).join('\n'))
	process.exit(1)
}
console.log(`✓ ${diseaseIds.size} diseases, ${vaccineIds.size} vaccines, ${scheduleFiles.length} schedule(s) valid`)
