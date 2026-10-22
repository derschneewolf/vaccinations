import { getCanonicalLocale } from '@nextcloud/l10n'

/** Date object (local time) → 'YYYY-MM-DD', or null. */
export function toIsoDate(date) {
	if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
		return null
	}
	const pad = (value) => String(value).padStart(2, '0')
	return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

/** 'YYYY-MM-DD' → Date object at local midnight, or null. */
export function fromIsoDate(value) {
	if (!value) {
		return null
	}
	const [year, month, day] = value.split('-').map(Number)
	return new Date(year, month - 1, day)
}

/** 'YYYY-MM-DD' → date in the user's locale, e.g. 12.05.2025 */
export function formatDate(value) {
	const date = fromIsoDate(value)
	return date ? date.toLocaleDateString(getCanonicalLocale(), { day: '2-digit', month: '2-digit', year: 'numeric' }) : ''
}
