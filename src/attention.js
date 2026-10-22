import { getLanguage, n, t } from '@nextcloud/l10n'

/** Statuses the traffic light in the person list looks at (same set as the server). */
const ATTENTION = ['overdue', 'not_documented', 'due', 'upcoming', 'missing_birth_date']

/**
 * Display name of a schedule rule: its own name (e.g. "MenACWY"), else the diseases.
 */
export function ruleName(rule, catalog) {
	const language = getLanguage().split(/[-_]/)[0]
	return rule.names?.[language] ?? rule.names?.en ?? rule.diseaseIds.map(catalog.diseaseName).join(', ')
}

/** Reduces a full status list to the rules relevant for the traffic light. */
export function attentionOf(rules) {
	return rules.filter((rule) => ATTENTION.includes(rule.status))
}

/**
 * Traffic light of a person.
 *
 * - error (red): something overdue or not documented – has to be caught up or entered
 * - warning (yellow): something due or coming up within 60 days
 * - ok (green): everything else
 * - unknown (grey): no date of birth, the schedule cannot be checked
 *
 * @return {{level: string, label: string, title: string}}
 */
export function summarize(attention, catalog) {
	if (attention.some((rule) => rule.status === 'missing_birth_date')) {
		const text = t('vaccinations', 'Date of birth missing')
		return { level: 'unknown', label: text, title: text }
	}

	const groups = {}
	for (const rule of attention) {
		(groups[rule.status] ??= []).push(ruleName(rule, catalog))
	}
	const count = (status) => groups[status]?.length ?? 0
	const line = (status, label) => count(status) > 0 ? `${label}: ${groups[status].join(', ')}` : null
	const title = [
		line('overdue', t('vaccinations', 'Overdue')),
		line('not_documented', t('vaccinations', 'Not documented')),
		line('due', t('vaccinations', 'Due')),
		line('upcoming', t('vaccinations', 'Soon')),
	].filter(Boolean).join('\n')

	const missing = count('overdue') + count('not_documented')
	if (missing > 0) {
		return { level: 'error', label: n('vaccinations', '%n open', '%n open', missing), title }
	}
	if (count('due') > 0) {
		return { level: 'warning', label: n('vaccinations', '%n due', '%n due', count('due')), title }
	}
	if (count('upcoming') > 0) {
		return { level: 'warning', label: n('vaccinations', '%n soon', '%n soon', count('upcoming')), title }
	}
	return { level: 'ok', label: t('vaccinations', 'OK'), title: t('vaccinations', 'All vaccinations up to date') }
}
