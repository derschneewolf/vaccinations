<template>
	<NcDialog :name="record ? t('vaccinations', 'Edit vaccination') : t('vaccinations', 'Add vaccination')"
		size="normal"
		is-form
		:buttons="buttons"
		@submit="submit"
		@closing="emit('close')">
		<div class="record-form">
			<div class="record-form__field">
				<NcSelect v-model="vaccine"
					input-id="vaccinations-vaccine"
					:input-label="t('vaccinations', 'Vaccine')"
					:placeholder="t('vaccinations', 'Search by name, manufacturer or disease')"
					:options="catalog.vaccines"
					:filter-by="(option, label, search) => catalog.matches(option, search)"
					label="name"
					:clearable="false">
					<template #option="option">
						<div class="record-form__option">
							<span>{{ option.name }}</span>
							<span class="record-form__secondary">
								{{ option.diseaseIds.map(catalog.diseaseName).join(', ') }}
							</span>
						</div>
					</template>
				</NcSelect>
				<p v-if="vaccine" class="record-form__secondary">
					{{ t('vaccinations', 'Protects against: {diseases}', { diseases: vaccine.diseaseIds.map(catalog.diseaseName).join(', ') }) }}
				</p>
				<p v-if="errors.vaccineId" class="record-form__error">
					{{ errors.vaccineId }}
				</p>
				<p class="record-form__secondary">
					{{ t('vaccinations', 'Vaccine missing? It can be added to the list via a pull request on GitHub.') }}
				</p>
			</div>

			<div class="record-form__field">
				<NcDateTimePickerNative id="vaccinations-date"
					v-model="administeredOn"
					type="date"
					:max="new Date()"
					:label="t('vaccinations', 'Date of vaccination')" />
				<p v-if="errors.administeredOn" class="record-form__error">
					{{ errors.administeredOn }}
				</p>
			</div>

			<NcTextField v-model="practice"
				:label="t('vaccinations', 'Practice (optional)')"
				:error="Boolean(errors.practice)"
				:helper-text="errors.practice ?? ''"
				:maxlength="255" />

			<NcTextField v-model="batchNumber"
				:label="t('vaccinations', 'Batch number (optional)')"
				:error="Boolean(errors.batchNumber)"
				:helper-text="errors.batchNumber ?? ''"
				:maxlength="64" />

			<NcTextArea v-model="note"
				:label="t('vaccinations', 'Note (optional)')"
				:error="Boolean(errors.note)"
				:helper-text="errors.note ?? ''"
				:maxlength="2000"
				resize="vertical" />

			<NcNoteCard v-if="generalError" type="error">
				{{ generalError }}
			</NcNoteCard>
		</div>
	</NcDialog>
</template>

<script setup>
import { t } from '@nextcloud/l10n'
import { computed, reactive, ref } from 'vue'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { fromIsoDate, toIsoDate } from '../dates.js'

const props = defineProps({
	catalog: { type: Object, required: true },
	/** Record to edit; null creates a new one. */
	record: { type: Object, default: null },
	/** async (input) => void – throws ApiError on failure */
	save: { type: Function, required: true },
})
const emit = defineEmits(['close'])

const vaccine = ref(props.record ? props.catalog.vaccine(props.record.vaccineId) : null)
const administeredOn = ref(fromIsoDate(props.record?.administeredOn) ?? new Date())
const practice = ref(props.record?.practice ?? '')
const batchNumber = ref(props.record?.batchNumber ?? '')
const note = ref(props.record?.note ?? '')

const saving = ref(false)
const errors = reactive({})
const generalError = ref(null)

// server messages are English; show translated text for the fields we know
const fieldMessages = {
	vaccineId: () => t('vaccinations', 'Please choose a vaccine from the list.'),
	administeredOn: () => t('vaccinations', 'Please enter a valid date that is not in the future.'),
}

const buttons = computed(() => [
	{ label: t('vaccinations', 'Cancel'), callback: () => emit('close') },
	// returning false keeps the dialog open; the parent closes it once saving succeeded
	{ label: t('vaccinations', 'Save'), variant: 'primary', disabled: saving.value, callback: () => { submit(); return false } },
])

async function submit() {
	if (saving.value) {
		return
	}
	Object.keys(errors).forEach((key) => delete errors[key])
	generalError.value = null
	saving.value = true
	try {
		await props.save({
			vaccineId: vaccine.value?.id ?? '',
			administeredOn: toIsoDate(administeredOn.value) ?? '',
			practice: practice.value,
			batchNumber: batchNumber.value,
			note: note.value,
		})
	} catch (error) {
		if (error.field) {
			errors[error.field] = fieldMessages[error.field]?.() ?? error.message
		} else {
			generalError.value = t('vaccinations', 'Could not save: {message}', { message: error.message })
		}
	} finally {
		saving.value = false
	}
}
</script>

<style scoped lang="scss">
.record-form {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 4);
	padding-bottom: calc(var(--default-grid-baseline) * 2);

	&__field :deep(.v-select) {
		width: 100%;
	}

	&__option {
		display: flex;
		flex-direction: column;
		line-height: 1.3;
	}

	&__secondary {
		color: var(--color-text-maxcontrast);
		font-size: var(--font-size-small, 13px);
		margin-top: var(--default-grid-baseline);
	}

	&__error {
		color: var(--color-error-text, var(--color-error));
		margin-top: var(--default-grid-baseline);
	}
}
</style>
