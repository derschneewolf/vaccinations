<template>
	<section class="status">
		<h3>
			{{ t('vaccinations', 'Vaccination status') }}
			<span v-if="schedule" class="status__schedule">– {{ scheduleName }}</span>
		</h3>

		<NcNoteCard v-if="missingBirthDate" type="info">
			{{ t('vaccinations', 'Enter the date of birth to check the vaccinations against the schedule.') }}
		</NcNoteCard>

		<table v-if="rows.length > 0" class="status__table">
			<thead>
				<tr>
					<th>{{ t('vaccinations', 'Vaccination') }}</th>
					<th>{{ t('vaccinations', 'Status') }}</th>
					<th class="status__optional">
						{{ t('vaccinations', 'Documented') }}
					</th>
					<th>{{ t('vaccinations', 'Next') }}</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="row in rows" :key="row.ruleId">
					<td>{{ row.name }}</td>
					<td>
						<span class="status__badge" :class="`status__badge--${row.status}`">
							{{ statusLabel(row.status) }}
						</span>
					</td>
					<td class="status__optional">
						<template v-if="row.dosesCounted > 0">
							{{ n('vaccinations', '%n dose', '%n doses', row.dosesCounted) }},
							{{ t('vaccinations', 'last {date}', { date: formatDate(row.lastDose) }) }}
						</template>
						<div v-if="row.dosesIgnored > 0" class="status__ignored">
							{{ n('vaccinations', '%n earlier dose does not count', '%n earlier doses do not count', row.dosesIgnored) }}
						</div>
					</td>
					<td>{{ nextText(row) }}</td>
				</tr>
			</tbody>
		</table>

		<details v-if="laterRows.length > 0" class="status__later">
			<summary>{{ n('vaccinations', '%n more recommendation in later years', '%n more recommendations in later years', laterRows.length) }}</summary>
			<ul>
				<li v-for="row in laterRows" :key="row.ruleId">
					{{ row.name }}: {{ nextText(row) }}
				</li>
			</ul>
		</details>

		<details v-if="schedule" class="status__source">
			<summary>
				{{ t('vaccinations', 'Based on: {schedule}, as of {version}. Standard vaccinations only – no substitute for medical advice.', {
					schedule: scheduleName,
					version: schedule.version,
				}) }}
			</summary>
			<p>{{ schedule.scope }}</p>
			<ul>
				<li v-for="item in schedule.simplifications" :key="item">
					{{ item }}
				</li>
			</ul>
			<p>
				{{ t('vaccinations', 'Sources:') }}
				<template v-for="(source, index) in schedule.sources" :key="source">
					<a :href="source" target="_blank" rel="noopener noreferrer">[{{ index + 1 }}]</a>{{ ' ' }}
				</template>
			</p>
		</details>

		<template v-if="otherRows.length > 0">
			<h4 class="status__subheading">
				{{ t('vaccinations', 'Further vaccinations') }}
			</h4>
			<p class="status__hint">
				{{ t('vaccinations', 'No current standard recommendation in the selected schedule – booster as advised by a doctor, e.g. before travelling.') }}
			</p>
			<table class="status__table">
				<thead>
					<tr>
						<th>{{ t('vaccinations', 'Vaccination') }}</th>
						<th>{{ t('vaccinations', 'Documented') }}</th>
						<th>{{ t('vaccinations', 'Last vaccination') }}</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="row in otherRows" :key="row.key">
						<td>{{ row.name }}</td>
						<td>{{ n('vaccinations', '%n dose', '%n doses', row.doses) }}</td>
						<td>{{ formatDate(row.lastDose) }} ({{ timeSince(row.lastDose) }})</td>
					</tr>
				</tbody>
			</table>
		</template>

	</section>
</template>

<script setup>
import { getLanguage, n, t } from '@nextcloud/l10n'
import { computed } from 'vue'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { formatDate, fromIsoDate, toIsoDate } from '../dates.js'

const props = defineProps({
	/** { schedule, rules } from the status API */
	status: { type: Object, required: true },
	catalog: { type: Object, required: true },
	/** documented doses, for vaccinations without a standard recommendation */
	records: { type: Array, default: () => [] },
})

// most urgent first
const ORDER = ['overdue', 'due', 'not_documented', 'upcoming', 'missing_birth_date', 'planned', 'complete', 'documented']

const schedule = computed(() => props.status.schedule)
const scheduleName = computed(() => {
	const names = schedule.value?.names ?? {}
	return names[getLanguage().split(/[-_]/)[0]] ?? names.en ?? schedule.value?.id
})
const missingBirthDate = computed(() => props.status.rules.some((rule) => rule.status === 'missing_birth_date'))

const allRows = computed(() => props.status.rules
	// rules without a current recommendation are visible in the history anyway
	.filter((rule) => rule.status !== 'documented')
	.map((rule) => ({ ...rule, name: ruleName(rule) }))
	.sort((a, b) => ORDER.indexOf(a.status) - ORDER.indexOf(b.status)
		|| (a.next?.dueFrom ?? '9999').localeCompare(b.next?.dueFrom ?? '9999')
		|| a.name.localeCompare(b.name)))

// recommendations more than two years ahead (e.g. 60+ for a 45-year-old) are collapsed
const laterThreshold = (() => {
	const date = new Date()
	date.setFullYear(date.getFullYear() + 2)
	return toIsoDate(date)
})()
const isLater = (row) => row.status === 'planned' && row.dosesCounted === 0 && (row.next?.dueFrom ?? '') > laterThreshold
const rows = computed(() => allRows.value.filter((row) => !isLater(row)))
const laterRows = computed(() => allRows.value.filter(isLater))

// doses the status table above does not account for (e.g. typhoid, hepatitis A,
// polio in adults, a MenC-only dose next to the MenACWY row); identical histories share a row
const otherRows = computed(() => {
	const datesByDisease = new Map()
	for (const record of props.records) {
		const vaccineDiseases = props.catalog.vaccine(record.vaccineId)?.diseaseIds ?? []
		// a row accounts for a dose only if the vaccine covers all diseases of the row
		const isAccountedFor = (diseaseId) => rows.value.some((row) => row.diseaseIds.includes(diseaseId)
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
			name: group.diseaseIds.map(props.catalog.diseaseName).join(', '),
			doses: group.dates.length,
			lastDose: group.dates.at(-1),
		}))
		.sort((a, b) => a.name.localeCompare(b.name))
})

function timeSince(isoDate) {
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

function ruleName(rule) {
	const language = getLanguage().split(/[-_]/)[0]
	return rule.names?.[language] ?? rule.names?.en ?? rule.diseaseIds.map(props.catalog.diseaseName).join(', ')
}

function statusLabel(status) {
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

function nextText(row) {
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
</script>

<style scoped lang="scss">
.status {
	margin-bottom: calc(var(--default-grid-baseline) * 8);

	h3 {
		margin-bottom: calc(var(--default-grid-baseline) * 2);
	}

	&__table {
		width: 100%;
		border-collapse: collapse;

		th {
			text-align: start;
			color: var(--color-text-maxcontrast);
			font-weight: normal;
		}

		th, td {
			padding: calc(var(--default-grid-baseline) * 2);
			border-bottom: 1px solid var(--color-border);
			vertical-align: middle;
		}
	}

	&__badge {
		display: inline-block;
		padding: 2px 10px;
		border-radius: var(--border-radius-pill, 12px);
		font-size: var(--font-size-small, 13px);
		white-space: nowrap;
		background-color: var(--color-background-dark);
		color: var(--color-main-text);

		&--overdue {
			background-color: var(--color-error);
			color: #fff;
		}

		&--due {
			background-color: var(--color-warning);
			color: #000;
		}

		&--upcoming {
			background-color: var(--color-primary-element-light);
			color: var(--color-primary-element-light-text);
		}

		// outlined: a light success colour with white text is unreadable in some themes
		&--planned, &--complete {
			background-color: transparent;
			border: 1px solid var(--color-success-text, var(--color-success));
			color: var(--color-success-text, var(--color-success));
		}
	}

	&__schedule {
		font-weight: normal;
		color: var(--color-text-maxcontrast);
	}

	&__ignored {
		color: var(--color-text-maxcontrast);
		font-size: var(--font-size-small, 13px);
	}

	&__subheading {
		margin-top: calc(var(--default-grid-baseline) * 6);
		margin-bottom: 0;
	}

	&__hint {
		color: var(--color-text-maxcontrast);
		font-size: var(--font-size-small, 13px);
		margin-bottom: calc(var(--default-grid-baseline) * 2);
	}

	&__later {
		margin-top: calc(var(--default-grid-baseline) * 3);

		summary {
			cursor: pointer;
		}

		ul {
			list-style: disc;
			padding-inline-start: 20px;
			margin-top: var(--default-grid-baseline);
		}
	}

	&__source {
		margin-top: calc(var(--default-grid-baseline) * 3);
		color: var(--color-text-maxcontrast);
		font-size: var(--font-size-small, 13px);

		summary {
			cursor: pointer;
		}

		ul {
			list-style: disc;
			padding-inline-start: 20px;
		}

		a {
			text-decoration: underline;
		}
	}

	@media (max-width: 700px) {
		&__optional {
			display: none;
		}
	}
}
</style>
