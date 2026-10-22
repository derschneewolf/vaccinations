import { getLanguage } from '@nextcloud/l10n'

/**
 * Lookup helpers around the catalog returned by the API.
 */
export function createCatalog({ diseases, vaccines, schedules = [] }) {
	const language = getLanguage()
	const baseLanguage = language.split(/[-_]/)[0]
	const diseasesById = new Map(diseases.map((disease) => [disease.id, disease]))
	const vaccinesById = new Map(vaccines.map((vaccine) => [vaccine.id, vaccine]))

	const diseaseName = (id) => {
		const names = diseasesById.get(id)?.names ?? {}
		return names[language] ?? names[baseLanguage] ?? names.en ?? id
	}

	const localizedName = (names, fallback) => names?.[language] ?? names?.[baseLanguage] ?? names?.en ?? fallback

	return {
		vaccines,
		schedules: schedules.map((schedule) => ({ ...schedule, label: localizedName(schedule.names, schedule.id) })),
		scheduleName: (id) => localizedName(schedules.find((schedule) => schedule.id === id)?.names, id),
		vaccine: (id) => vaccinesById.get(id) ?? null,
		vaccineName: (id) => vaccinesById.get(id)?.name ?? id,
		diseaseName,
		diseaseNamesOf: (vaccineId) => (vaccinesById.get(vaccineId)?.diseaseIds ?? []).map(diseaseName),
		/** Search term matching for the vaccine picker. */
		matches: (vaccine, search) => {
			const needle = search.trim().toLowerCase()
			return [vaccine.name, ...vaccine.aliases, vaccine.manufacturer ?? '', ...vaccine.diseaseIds.map(diseaseName)]
				.some((term) => term.toLowerCase().includes(needle))
		},
	}
}
