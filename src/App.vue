<template>
	<NcContent app-name="vaccinations">
		<NcAppNavigation :aria-label="t('vaccinations', 'People')">
			<template #list>
				<NcAppNavigationCaption :name="t('vaccinations', 'People')" />
				<NcAppNavigationItem v-for="person in personsWithLight"
					:key="person.userId"
					:name="person.isSelf ? t('vaccinations', '{name} (you)', { name: person.displayName }) : person.displayName"
					:active="person.userId === selectedUserId"
					@click="selectedUserId = person.userId">
					<template #icon>
						<NcAvatar :user="person.userId"
							:display-name="person.displayName"
							:size="20"
							hide-status
							disable-menu
							disable-tooltip />
					</template>
					<template #counter>
						<span class="vaccinations__light"
							:class="`vaccinations__light--${person.light.level}`"
							:title="person.light.title">
							<!-- plain SVG: NcIconSvgWrapper reserves a 44px click area and inflates the badge -->
							<svg v-if="person.light.level === 'ok'"
								class="vaccinations__light-icon"
								viewBox="0 0 24 24"
								aria-hidden="true">
								<path :d="mdiCheck" />
							</svg>
							{{ person.light.label }}
						</span>
					</template>
				</NcAppNavigationItem>
			</template>
		</NcAppNavigation>

		<NcAppContent :page-heading="t('vaccinations', 'Vaccinations')" :page-title="pageTitle">
			<div v-if="loading" class="vaccinations__center">
				<NcLoadingIcon :size="44" />
			</div>
			<NcEmptyContent v-else-if="loadError"
				:name="t('vaccinations', 'Could not load the app')"
				:description="loadError">
				<template #icon>
					<NcIconSvgWrapper :path="mdiAlertCircleOutline" />
				</template>
			</NcEmptyContent>
			<PersonView v-else-if="selectedPerson"
				:key="selectedPerson.userId"
				:person="selectedPerson"
				:catalog="catalog"
				@person-updated="onPersonUpdated"
				@status-loaded="onStatusLoaded" />
		</NcAppContent>
	</NcContent>
</template>

<script setup>
import { mdiAlertCircleOutline, mdiCheck } from '@mdi/js'
import { t } from '@nextcloud/l10n'
import { computed, onMounted, ref } from 'vue'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationCaption from '@nextcloud/vue/components/NcAppNavigationCaption'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import PersonView from './components/PersonView.vue'
import { api } from './api.js'
import { attentionOf, summarize } from './attention.js'
import { createCatalog } from './catalog.js'

const persons = ref([])
const catalog = ref(null)
const selectedUserId = ref(null)
const loading = ref(true)
const loadError = ref(null)

const personsWithLight = computed(() => persons.value.map((person) => ({
	...person,
	light: summarize(person.attention ?? [], catalog.value),
})))

const selectedPerson = computed(() => persons.value.find((person) => person.userId === selectedUserId.value) ?? null)
const pageTitle = computed(() => selectedPerson.value
	? `${selectedPerson.value.displayName} - ${t('vaccinations', 'Vaccinations')}`
	: t('vaccinations', 'Vaccinations'))

onMounted(async () => {
	try {
		const [catalogData, personList] = await Promise.all([api.catalog(), api.persons()])
		catalog.value = createCatalog(catalogData)
		persons.value = personList
		// notifications link to ?person=<uid>
		const requested = new URLSearchParams(window.location.search).get('person')
		selectedUserId.value = personList.find((person) => person.userId === requested)?.userId ?? personList[0]?.userId ?? null
	} catch (error) {
		loadError.value = error.message
	} finally {
		loading.value = false
	}
})

function onPersonUpdated(updated) {
	// keep the traffic light until the status has been reloaded
	persons.value = persons.value.map((person) => person.userId === updated.userId ? { ...person, ...updated } : person)
}

function onStatusLoaded(userId, rules) {
	persons.value = persons.value.map((person) => person.userId === userId ? { ...person, attention: attentionOf(rules) } : person)
}
</script>

<style scoped lang="scss">
.vaccinations__center {
	display: flex;
	justify-content: center;
	padding-top: 20vh;
}

.vaccinations__light {
	display: inline-flex;
	align-items: center;
	gap: 2px;
	padding: 1px 8px;
	border-radius: var(--border-radius-pill, 12px);
	border: 1px solid transparent;
	font-size: var(--font-size-small, 13px);
	white-space: nowrap;

	&-icon {
		width: 14px;
		height: 14px;
		fill: currentColor;
	}

	&--error {
		// NC 32+: --color-error is a light background, the strong colour is --color-element-error
		background-color: var(--color-element-error, var(--color-error));
		color: #fff;
		font-weight: bold;
	}

	&--warning {
		background-color: var(--color-element-warning, var(--color-warning));
		color: #000;
		font-weight: bold;
	}

	// outlined like "Complete" in the status table
	&--ok {
		border-color: var(--color-success-text, var(--color-success));
		color: var(--color-success-text, var(--color-success));
	}

	&--unknown {
		border: 1px dashed var(--color-text-maxcontrast);
		color: var(--color-text-maxcontrast);
	}
}
</style>
