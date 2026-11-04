<template>
	<!-- outside the Nextcloud layout: its scroll containers would cut the printout -->
	<Teleport to="body">
		<div class="vaccinations-print" :lang="language">
			<header class="vaccinations-print__header">
				<h1>{{ t('vaccinations', 'Vaccination status of {name}', { name: person.displayName }) }}</h1>
				<p>
					<template v-if="person.birthDate">
						{{ t('vaccinations', 'Born {date} ({age})', { date: formatDate(person.birthDate), age: ageText }) }} ·
					</template>
					{{ t('vaccinations', 'Schedule: {schedule}, as of {version}', { schedule: scheduleName, version: status.schedule?.version ?? '' }) }} ·
					{{ t('vaccinations', 'Printed on {date}', { date: formatDate(today) }) }}
				</p>
			</header>

			<section>
				<h2>{{ t('vaccinations', 'Open') }}</h2>
				<table v-if="openRows.length > 0">
					<thead>
						<tr>
							<th>{{ t('vaccinations', 'Vaccination') }}</th>
							<th>{{ t('vaccinations', 'Status') }}</th>
							<th>{{ t('vaccinations', 'Documented') }}</th>
							<th>{{ t('vaccinations', 'Next') }}</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="row in openRows" :key="row.ruleId">
							<td class="vaccinations-print__name">
								{{ row.name }}
							</td>
							<td>
								<span class="vaccinations-print__status" :class="`vaccinations-print__status--${row.status}`">
									{{ statusLabel(row.status) }}
								</span>
							</td>
							<td>
								<div>{{ documentedText(row) || '–' }}</div>
								<div v-if="row.dosesIgnored > 0" class="vaccinations-print__secondary">
									{{ ignoredText(row) }}
								</div>
							</td>
							<td>
								<div>{{ nextText(row) }}</div>
								<div class="vaccinations-print__secondary">
									{{ reasonText(row) }}
								</div>
							</td>
						</tr>
					</tbody>
				</table>
				<p v-else>
					{{ t('vaccinations', 'Nothing open – all recommended vaccinations are up to date.') }}
				</p>
			</section>

			<section v-if="doneRows.length > 0">
				<h2>{{ t('vaccinations', 'Up to date') }}</h2>
				<table>
					<thead>
						<tr>
							<th>{{ t('vaccinations', 'Vaccination') }}</th>
							<th>{{ t('vaccinations', 'Status') }}</th>
							<th>{{ t('vaccinations', 'Documented') }}</th>
							<th>{{ t('vaccinations', 'Next') }}</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="row in doneRows" :key="row.ruleId">
							<td class="vaccinations-print__name">
								{{ row.name }}
							</td>
							<td>{{ statusLabel(row.status) }}</td>
							<td>
								<div>{{ documentedText(row) || '–' }}</div>
								<div v-if="row.dosesIgnored > 0" class="vaccinations-print__secondary">
									{{ ignoredText(row) }}
								</div>
							</td>
							<td>{{ nextText(row) }}</td>
						</tr>
					</tbody>
				</table>
				<p v-if="view.laterRows.length > 0" class="vaccinations-print__secondary">
					{{ t('vaccinations', 'Later:') }}
					{{ view.laterRows.map((row) => `${row.name} (${nextText(row)})`).join(' · ') }}
				</p>
			</section>

			<section v-if="others.length > 0">
				<h2>{{ t('vaccinations', 'Further vaccinations') }}</h2>
				<p class="vaccinations-print__secondary">
					{{ t('vaccinations', 'No current standard recommendation in the selected schedule – booster as advised by a doctor, e.g. before travelling.') }}
				</p>
				<table>
					<thead>
						<tr>
							<th>{{ t('vaccinations', 'Vaccination') }}</th>
							<th>{{ t('vaccinations', 'Documented') }}</th>
							<th>{{ t('vaccinations', 'Last vaccination') }}</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="row in others" :key="row.key">
							<td class="vaccinations-print__name">
								{{ row.name }}
							</td>
							<td>{{ n('vaccinations', '%n dose', '%n doses', row.doses) }}</td>
							<td>{{ formatDate(row.lastDose) }} ({{ timeSince(row.lastDose) }})</td>
						</tr>
					</tbody>
				</table>
			</section>

			<section v-if="sortedRecords.length > 0" class="vaccinations-print__history">
				<h2>{{ t('vaccinations', 'Vaccination history') }}</h2>
				<table>
					<thead>
						<tr>
							<th>{{ t('vaccinations', 'Date') }}</th>
							<th>{{ t('vaccinations', 'Vaccine') }}</th>
							<th>{{ t('vaccinations', 'Practice') }}</th>
							<th>{{ t('vaccinations', 'Batch') }}</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="record in sortedRecords" :key="record.id">
							<td class="vaccinations-print__date">
								{{ formatDate(record.administeredOn) }}
							</td>
							<td>
								<div>{{ catalog.vaccineName(record.vaccineId) }}</div>
								<div class="vaccinations-print__secondary">
									{{ catalog.diseaseNamesOf(record.vaccineId).join(', ') }}
								</div>
								<div v-if="record.note" class="vaccinations-print__secondary">
									<em>{{ record.note }}</em>
								</div>
							</td>
							<td>{{ record.practice }}</td>
							<td>{{ record.batchNumber }}</td>
						</tr>
					</tbody>
				</table>
			</section>

			<section class="vaccinations-print__notes">
				<h2>{{ t('vaccinations', 'Notes') }}</h2>
				<div v-for="line in 4" :key="line" class="vaccinations-print__line" />
			</section>

			<footer class="vaccinations-print__footer">
				{{ t('vaccinations', 'Based on: {schedule}, as of {version}. Standard vaccinations only – no substitute for medical advice.', {
					schedule: scheduleName,
					version: status.schedule?.version ?? '',
				}) }}
				{{ t('vaccinations', 'Created with the Nextcloud app Vaccinations.') }}
			</footer>
		</div>
	</Teleport>
</template>

<script setup>
import { getLanguage, n, t } from '@nextcloud/l10n'
import { computed } from 'vue'
import { formatDate, fromIsoDate, toIsoDate } from '../dates.js'
import {
	documentedText,
	ignoredText,
	nextText,
	OPEN_STATUSES,
	otherRows,
	reasonText,
	scheduleName as nameOf,
	statusLabel,
	statusRows,
	timeSince,
} from '../status.js'

const props = defineProps({
	person: { type: Object, required: true },
	/** { schedule, rules } from the status API */
	status: { type: Object, required: true },
	catalog: { type: Object, required: true },
	records: { type: Array, default: () => [] },
})

const language = getLanguage()
const today = toIsoDate(new Date())

const scheduleName = computed(() => nameOf(props.status.schedule))
const view = computed(() => statusRows(props.status, props.catalog))
const openRows = computed(() => view.value.rows.filter((row) => OPEN_STATUSES.includes(row.status)))
const doneRows = computed(() => view.value.rows.filter((row) => !OPEN_STATUSES.includes(row.status)))
const others = computed(() => otherRows(props.records, props.catalog, view.value.rows))
const sortedRecords = computed(() => [...props.records].sort((a, b) =>
	b.administeredOn.localeCompare(a.administeredOn) || b.id - a.id))

const ageText = computed(() => {
	const birth = fromIsoDate(props.person.birthDate)
	const now = new Date()
	const months = (now.getFullYear() - birth.getFullYear()) * 12 + now.getMonth() - birth.getMonth() - (now.getDate() < birth.getDate() ? 1 : 0)
	return months < 24
		? n('vaccinations', '%n month', '%n months', months)
		: n('vaccinations', '%n year', '%n years', Math.floor(months / 12))
})
</script>

<style lang="scss">
// not scoped: the sheet lives directly in <body> and replaces the whole page when printing
.vaccinations-print {
	display: none;
}

@media print {
	@page {
		size: A4;
		margin: 14mm 12mm;
	}

	// Nextcloud positions html/body absolute/fixed at viewport size: the printout
	// would be cut at the right and stop after the first page
	html, body {
		position: static !important;
		inset: auto !important;
		width: auto !important;
		min-width: 0 !important;
		height: auto !important;
		min-height: 0 !important;
		overflow: visible !important;
		background: #fff !important;
	}

	body > :not(.vaccinations-print) {
		display: none !important;
	}

	.vaccinations-print {
		display: block;
		color: #000;
		font: 10pt/1.35 system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;

		h1 {
			font-size: 16pt;
			margin: 0 0 2pt;
		}

		h2 {
			font-size: 12pt;
			margin: 12pt 0 4pt;
			break-after: avoid;
		}

		p {
			margin: 0 0 4pt;
		}

		table {
			width: 100%;
			border-collapse: collapse;
		}

		thead {
			display: table-header-group;
		}

		tr {
			break-inside: avoid;
		}

		th {
			text-align: start;
			font-weight: normal;
			color: #555;
			border-bottom: 1pt solid #000;
		}

		th, td {
			padding: 3pt 6pt 3pt 0;
			vertical-align: top;
		}

		td {
			border-bottom: 0.5pt solid #bbb;
		}

		&__header {
			border-bottom: 1.5pt solid #000;
			padding-bottom: 4pt;
		}

		&__name {
			font-weight: 600;
		}

		&__date {
			white-space: nowrap;
		}

		&__secondary {
			color: #444;
			font-size: 8.5pt;
		}

		// readable in black and white too: the strong states are bold and framed
		&__status {
			display: inline-block;
			white-space: nowrap;
			padding: 0 4pt;
			border: 0.75pt solid #666;
			border-radius: 3pt;
			print-color-adjust: exact;
			-webkit-print-color-adjust: exact;

			&--overdue {
				font-weight: bold;
				border: 1.5pt solid #b00000;
				color: #b00000;
				background: #fde8e8;
			}

			&--not_documented {
				font-weight: bold;
				border-color: #b00000;
				color: #b00000;
			}

			&--due, &--upcoming {
				font-weight: bold;
				border-color: #9a6100;
				color: #7a4d00;
				background: #fff3d6;
			}
		}

		&__notes {
			break-inside: avoid;
		}

		&__line {
			height: 18pt;
			border-bottom: 0.5pt solid #999;
		}

		&__footer {
			margin-top: 12pt;
			font-size: 7.5pt;
			color: #555;
			word-break: break-word;
		}
	}
}
</style>
