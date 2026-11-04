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
							{{ ignoredText(row) }}
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
import { n, t } from '@nextcloud/l10n'
import { computed } from 'vue'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { formatDate } from '../dates.js'
import { ignoredText, nextText, otherRows as buildOtherRows, scheduleName as nameOf, statusLabel, statusRows, timeSince } from '../status.js'

const props = defineProps({
	/** { schedule, rules } from the status API */
	status: { type: Object, required: true },
	catalog: { type: Object, required: true },
	/** documented doses, for vaccinations without a standard recommendation */
	records: { type: Array, default: () => [] },
})

const schedule = computed(() => props.status.schedule)
const scheduleName = computed(() => nameOf(schedule.value))
const missingBirthDate = computed(() => props.status.rules.some((rule) => rule.status === 'missing_birth_date'))

const statusView = computed(() => statusRows(props.status, props.catalog))
const rows = computed(() => statusView.value.rows)
const laterRows = computed(() => statusView.value.laterRows)
const otherRows = computed(() => buildOtherRows(props.records, props.catalog, rows.value))
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
			// NC 32+: --color-error is a light background, the strong colour is --color-element-error
		background-color: var(--color-element-error, var(--color-error));
			color: #fff;
		}

		&--due {
			background-color: var(--color-element-warning, var(--color-warning));
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
