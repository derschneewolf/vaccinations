<template>
	<NcContent app-name="vaccinations">
		<NcAppNavigation :aria-label="t('vaccinations', 'People')">
			<template #list>
				<NcAppNavigationCaption :name="t('vaccinations', 'People')" />
				<NcAppNavigationItem v-for="person in persons"
					:key="person.userId"
					:name="person.displayName"
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
					<template v-if="person.isSelf" #counter>
						<span class="vaccinations__self">{{ t('vaccinations', 'You') }}</span>
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
				@person-updated="onPersonUpdated" />
		</NcAppContent>
	</NcContent>
</template>

<script setup>
import { mdiAlertCircleOutline } from '@mdi/js'
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
import { createCatalog } from './catalog.js'

const persons = ref([])
const catalog = ref(null)
const selectedUserId = ref(null)
const loading = ref(true)
const loadError = ref(null)

const selectedPerson = computed(() => persons.value.find((person) => person.userId === selectedUserId.value) ?? null)
const pageTitle = computed(() => selectedPerson.value
	? `${selectedPerson.value.displayName} - ${t('vaccinations', 'Vaccinations')}`
	: t('vaccinations', 'Vaccinations'))

onMounted(async () => {
	try {
		const [catalogData, personList] = await Promise.all([api.catalog(), api.persons()])
		catalog.value = createCatalog(catalogData)
		persons.value = personList
		selectedUserId.value = personList[0]?.userId ?? null
	} catch (error) {
		loadError.value = error.message
	} finally {
		loading.value = false
	}
})

function onPersonUpdated(updated) {
	persons.value = persons.value.map((person) => person.userId === updated.userId ? updated : person)
}
</script>

<style scoped lang="scss">
.vaccinations__center {
	display: flex;
	justify-content: center;
	padding-top: 20vh;
}

.vaccinations__self {
	color: var(--color-text-maxcontrast);
	font-size: var(--font-size-small, 13px);
}
</style>
