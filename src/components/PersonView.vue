<template>
	<div class="person">
		<header class="person__header">
			<h2 class="person__name">
				{{ person.displayName }}
			</h2>
			<div class="person__birth-date">
				<NcDateTimePickerNative id="vaccinations-birth-date"
					:model-value="fromIsoDate(person.birthDate)"
					type="date"
					:max="new Date()"
					:label="t('vaccinations', 'Date of birth')"
					@update:model-value="saveBirthDate" />
				<p v-if="!person.birthDate" class="person__hint">
					{{ t('vaccinations', 'Needed to check against the vaccination schedule.') }}
				</p>
			</div>
			<NcButton variant="primary" @click="openCreate">
				<template #icon>
					<NcIconSvgWrapper :path="mdiPlus" />
				</template>
				{{ t('vaccinations', 'Add vaccination') }}
			</NcButton>
		</header>

		<StatusTable v-if="status && catalog" :status="status" :catalog="catalog" />

		<div v-if="loading" class="person__center">
			<NcLoadingIcon :size="32" />
		</div>

		<NcEmptyContent v-else-if="records.length === 0"
			:name="t('vaccinations', 'No vaccinations recorded yet')"
			:description="t('vaccinations', 'Add the entries from the vaccination card, starting with the most recent ones.')">
			<template #icon>
				<NcIconSvgWrapper :path="mdiNeedle" />
			</template>
		</NcEmptyContent>

		<template v-else>
			<section class="person__section">
				<h3>{{ t('vaccinations', 'Vaccination history') }}</h3>
				<table class="person__table">
					<thead>
						<tr>
							<th>{{ t('vaccinations', 'Date') }}</th>
							<th>{{ t('vaccinations', 'Vaccine') }}</th>
							<th class="person__optional">
								{{ t('vaccinations', 'Practice') }}
							</th>
							<th class="person__optional">
								{{ t('vaccinations', 'Batch') }}
							</th>
							<th><span class="hidden-visually">{{ t('vaccinations', 'Actions') }}</span></th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="record in sortedRecords" :key="record.id">
							<td class="person__date">
								{{ formatDate(record.administeredOn) }}
							</td>
							<td>
								<div>{{ catalog.vaccineName(record.vaccineId) }}</div>
								<div class="person__secondary">
									{{ catalog.diseaseNamesOf(record.vaccineId).join(', ') }}
								</div>
								<div v-if="record.note" class="person__note">
									{{ record.note }}
								</div>
							</td>
							<td class="person__optional">
								{{ record.practice }}
							</td>
							<td class="person__optional">
								{{ record.batchNumber }}
							</td>
							<td class="person__actions">
								<NcActions>
									<NcActionButton close-after-click @click="openEdit(record)">
										<template #icon>
											<NcIconSvgWrapper :path="mdiPencil" />
										</template>
										{{ t('vaccinations', 'Edit') }}
									</NcActionButton>
									<NcActionButton close-after-click @click="recordToDelete = record">
										<template #icon>
											<NcIconSvgWrapper :path="mdiDelete" />
										</template>
										{{ t('vaccinations', 'Delete') }}
									</NcActionButton>
								</NcActions>
							</td>
						</tr>
					</tbody>
				</table>
			</section>
		</template>

		<RecordDialog v-if="dialogOpen"
			:catalog="catalog"
			:record="recordToEdit"
			:save="saveRecord"
			@close="dialogOpen = false" />

		<NcDialog v-if="recordToDelete"
			:name="t('vaccinations', 'Delete vaccination?')"
			:message="t('vaccinations', '{vaccine} from {date} will be removed.', {
				vaccine: catalog.vaccineName(recordToDelete.vaccineId),
				date: formatDate(recordToDelete.administeredOn),
			})"
			:buttons="deleteButtons"
			@closing="recordToDelete = null" />
	</div>
</template>

<script setup>
import { mdiDelete, mdiNeedle, mdiPencil, mdiPlus } from '@mdi/js'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import RecordDialog from './RecordDialog.vue'
import StatusTable from './StatusTable.vue'
import { api } from '../api.js'
import { formatDate, fromIsoDate, isPlausiblePastDate, toIsoDate } from '../dates.js'

const props = defineProps({
	person: { type: Object, required: true },
	catalog: { type: Object, required: true },
})
const emit = defineEmits(['person-updated'])

const records = ref([])
const status = ref(null)
const loading = ref(true)
const dialogOpen = ref(false)
const recordToEdit = ref(null)
const recordToDelete = ref(null)

onMounted(async () => {
	loadStatus()
	try {
		records.value = await api.records(props.person.userId)
	} catch (error) {
		showError(t('vaccinations', 'Could not load vaccinations: {message}', { message: error.message }))
	} finally {
		loading.value = false
	}
})

/** Recomputed by the server after every change of records or the date of birth. */
async function loadStatus() {
	try {
		status.value = await api.status(props.person.userId)
	} catch (error) {
		showError(t('vaccinations', 'Could not load the vaccination status: {message}', { message: error.message }))
	}
}

watch(() => props.person.birthDate, loadStatus)

// newest first; same day: most recently entered first
const sortedRecords = computed(() => [...records.value].sort((a, b) =>
	b.administeredOn.localeCompare(a.administeredOn) || b.id - a.id))

function openCreate() {
	recordToEdit.value = null
	dialogOpen.value = true
}

function openEdit(record) {
	recordToEdit.value = record
	dialogOpen.value = true
}

/** Called by the dialog; errors propagate so the dialog can show them per field. */
async function saveRecord(input) {
	if (recordToEdit.value) {
		const updated = await api.updateRecord(recordToEdit.value.id, input)
		records.value = records.value.map((record) => record.id === updated.id ? updated : record)
	} else {
		records.value = [...records.value, await api.createRecord(props.person.userId, input)]
	}
	dialogOpen.value = false
	showSuccess(t('vaccinations', 'Vaccination saved'))
	loadStatus()
}

const deleteButtons = [
	{ label: t('vaccinations', 'Cancel') },
	{ label: t('vaccinations', 'Delete'), variant: 'error', callback: deleteRecord },
]

async function deleteRecord() {
	const record = recordToDelete.value
	try {
		await api.deleteRecord(record.id)
		records.value = records.value.filter((item) => item.id !== record.id)
		loadStatus()
	} catch (error) {
		showError(t('vaccinations', 'Could not delete: {message}', { message: error.message }))
	}
}

// saved shortly after typing stops, and only for complete dates (or when cleared)
let birthDateTimer = null
onBeforeUnmount(() => clearTimeout(birthDateTimer))

function saveBirthDate(date) {
	clearTimeout(birthDateTimer)
	if (date !== null && !isPlausiblePastDate(date)) {
		return
	}
	birthDateTimer = setTimeout(() => storeBirthDate(date === null ? null : toIsoDate(date)), 700)
}

async function storeBirthDate(birthDate) {
	if (birthDate === props.person.birthDate) {
		return
	}
	try {
		emit('person-updated', await api.updatePerson(props.person.userId, birthDate))
	} catch (error) {
		showError(t('vaccinations', 'Could not save the date of birth: {message}', { message: error.message }))
	}
}
</script>

<style scoped lang="scss">
.person {
	padding: calc(var(--default-grid-baseline) * 4);
	padding-inline-start: calc(var(--default-clickable-area) + var(--default-grid-baseline) * 4);
	max-width: 1000px;

	&__header {
		display: flex;
		flex-wrap: wrap;
		align-items: flex-end;
		gap: calc(var(--default-grid-baseline) * 4);
		margin-bottom: calc(var(--default-grid-baseline) * 6);
	}

	&__name {
		flex-basis: 100%;
		margin: 0;
	}

	&__birth-date {
		flex: 1 1 220px;
		max-width: 260px;
	}

	&__hint {
		color: var(--color-text-maxcontrast);
		font-size: var(--font-size-small, 13px);
		margin-top: var(--default-grid-baseline);
	}

	&__center {
		display: flex;
		justify-content: center;
		padding-top: 10vh;
	}

	&__section {
		margin-bottom: calc(var(--default-grid-baseline) * 8);

		h3 {
			margin-bottom: calc(var(--default-grid-baseline) * 2);
		}
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
			vertical-align: top;
		}
	}

	&__number {
		text-align: end !important;
		width: 80px;
	}

	&__date {
		white-space: nowrap;
	}

	&__secondary {
		color: var(--color-text-maxcontrast);
		font-size: var(--font-size-small, 13px);
	}

	&__note {
		margin-top: var(--default-grid-baseline);
		font-style: italic;
	}

	&__actions {
		width: var(--default-clickable-area);
		padding-block: 0 !important;
	}

	@media (max-width: 700px) {
		&__optional {
			display: none;
		}
	}
}
</style>
