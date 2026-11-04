import { getLanguage, n, t } from '@nextcloud/l10n'
import { ruleName } from './attention.js'
import { formatDate, fromIsoDate, toIsoDate } from './dates.js'

/**
 * Presentation of the status API result, shared by the status table and the print sheet.
 */

// most urgent first
const ORDER = ['overdue', 'due', 'not_documented', 'upcoming', 'missing_birth_date', 'planned', 'complete', 'documented']

/** Statuses that need doing something – the "open" part of the print sheet. */
export const OPEN_STATUSES = ['overdue', 'due', 'not_documented', 'upcoming', 'missing_birth_date']

export function scheduleName(schedule) {
	const names = schedule?.names ?? {}
	return names[getLanguage().split(/[-_]/)[0]] ?? names.en ?? schedule?.id
}

/**
 * Rows of the status table, most urgent first. Recommendations more than two
 * years ahead (e.g. 60+ for a 45-year-old) are returned separately as laterRows.
 */
export function statusRows(status, catalog) {
	const all = status.rules
		// rules without a current recommendation are visible in the history anyway
		.filter((rule) => rule.status !== 'documented')
		.map((rule) => ({ ...rule, name: ruleName(rule, catalog) }))
		.sort((a, b) => ORDER.indexOf(a.status) - ORDER.indexOf(b.status)
			|| (a.next?.dueFrom ?? '9999').localeCompare(b.next?.dueFrom ?? '9999')
			|| a.name.localeCompare(b.name))

	const threshold = new Date()
	threshold.setFullYear(threshold.getFullYear() + 2)
	const laterThreshold = toIsoDate(threshold)
	const isLater = (row) => row.status === 'planned' && row.dosesCounted === 0 && (row.next?.dueFrom ?? '') > laterThreshold

	return {
		rows: all.filter((row) => !isLater(row)),
		laterRows: all.filter(isLater),
	}
}

/**
 * Doses the status rows do not account for (e.g. typhoid, hepatitis A, polio in
 * adults, a MenC-only dose next to the MenACWY row); identical histories share a row.
 */
export function otherRows(records, catalog, rows) {
	const datesByDisease = new Map()
	for (const record of records) {
		const vaccineDiseases = catalog.vaccine(record.vaccineId)?.diseaseIds ?? []
		// a row accounts for a dose only if the vaccine covers all diseases of the row
		const isAccountedFor = (diseaseId) => rows.some((row) => row.diseaseIds.includes(diseaseId)
			&& row.diseaseIds.every((id) => vaccineDiseases.includes(id)))
		for (const diseaseId of vaccineDiseases) {
			if (!isAccountedFor(diseaseId)) {
				datesByDisease.set(diseaseId, new Set([...(datesByDisease.get(diseaseId) ?? []), record.administeredOn]))
			}
		}
	}
	const groups = new Map()
	for (const [diseaseId, dates] of datesByDisease) {
		const sorted = [...dates].sort()
		const key = sorted.join('|')
		groups.set(key, { key, dates: sorted, diseaseIds: [...(groups.get(key)?.diseaseIds ?? []), diseaseId] })
	}
	return [...groups.values()]
		.map((group) => ({
			key: group.key,
			name: group.diseaseIds.map(catalog.diseaseName).join(', '),
			doses: group.dates.length,
			lastDose: group.dates.at(-1),
		}))
		.sort((a, b) => a.name.localeCompare(b.name))
}

export function timeSince(isoDate) {
	const date = fromIsoDate(isoDate)
	const now = new Date()
	const months = (now.getFullYear() - date.getFullYear()) * 12 + now.getMonth() - date.getMonth() - (now.getDate() < date.getDate() ? 1 : 0)
	if (months < 1) {
		return t('vaccinations', 'less than a month ago')
	}
	if (months < 24) {
		return n('vaccinations', '%n month ago', '%n months ago', months)
	}
	return n('vaccinations', '%n year ago', '%n years ago', Math.floor(months / 12))
}

export function statusLabel(status) {
	return {
		overdue: t('vaccinations', 'Overdue'),
		due: t('vaccinations', 'Due'),
		not_documented: t('vaccinations', 'Not documented'),
		upcoming: t('vaccinations', 'Soon'),
		planned: t('vaccinations', 'Up to date'),
		complete: t('vaccinations', 'Complete'),
		documented: t('vaccinations', 'Documented'),
		missing_birth_date: t('vaccinations', 'Date of birth missing'),
	}[status] ?? status
}

/** "3 doses, last 19.09.2014" */
export function documentedText(row) {
	return row.dosesCounted > 0
		? `${n('vaccinations', '%n dose', '%n doses', row.dosesCounted)}, ${t('vaccinations', 'last {date}', { date: formatDate(row.lastDose) })}`
		: ''
}

/** Doses that are documented but do not count for the row, with their dates. */
export function ignoredText(row) {
	return row.dosesIgnored > 0
		? t('vaccinations', 'Not counted, given too early for this dose: {dates}', { dates: (row.ignoredDates ?? []).map(formatDate).join(', ') })
		: ''
}

/** What is next for the row: "Dose 2 from …", "Booster from …", "recommended" … */
export function nextText(row) {
	if (!row.next) {
		return ''
	}
	const date = formatDate(row.next.dueFrom)
	if (row.status === 'not_documented') {
		// a date long ago (e.g. the 60th birthday 15 years back) says nothing useful
		const yearAgo = new Date()
		yearAgo.setFullYear(yearAgo.getFullYear() - 1)
		return fromIsoDate(row.next.dueFrom) < yearAgo
			? t('vaccinations', 'recommended')
			: t('vaccinations', 'recommended since {date}', { date })
	}
	switch (row.next.type) {
	case 'dose':
		return t('vaccinations', 'Dose {number} from {date}', { number: row.next.doseNumber, date })
	case 'booster':
		return t('vaccinations', 'Booster from {date}', { date })
	case 'season':
		return t('vaccinations', 'Season from {date}', { date })
	default:
		// a named vaccination (Tdap, MenACWY …); a label equal to the row name ("RSV") adds nothing
		return row.next.label && row.next.label.toLowerCase() !== row.name.toLowerCase()
			? t('vaccinations', '{dose} from {date}', { dose: row.next.label, date })
			: t('vaccinations', 'from {date}', { date })
	}
}

/**
 * Why a row is open, for the print sheet: when it became or becomes overdue,
 * or that nothing has been recorded.
 */
export function reasonText(row) {
	switch (row.status) {
	case 'overdue':
		return row.next?.overdueFrom ? t('vaccinations', 'overdue since {date}', { date: formatDate(row.next.overdueFrom) }) : ''
	case 'due':
	case 'upcoming':
		return row.next?.overdueFrom ? t('vaccinations', 'overdue from {date}', { date: formatDate(row.next.overdueFrom) }) : ''
	case 'not_documented':
		return t('vaccinations', 'No vaccination recorded')
	case 'missing_birth_date':
		return t('vaccinations', 'Date of birth needed for the check')
	default:
		return ''
	}
}
