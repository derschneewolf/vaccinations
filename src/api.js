import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

const base = (path) => generateOcsUrl('/apps/vaccinations/api/v1' + path)

/**
 * Error raised for failed API calls.
 * `field` is set for validation errors (HTTP 400) and names the offending input.
 */
export class ApiError extends Error {
	constructor(status, message, field = null) {
		super(message)
		this.status = status
		this.field = field
	}
}

async function request(method, path, data = undefined) {
	try {
		const response = await axios({ method, url: base(path), data })
		return response.data.ocs.data
	} catch (error) {
		const payload = error.response?.data?.ocs?.data ?? {}
		throw new ApiError(error.response?.status ?? 0, payload.message ?? error.message, payload.field ?? null)
	}
}

const encode = encodeURIComponent

export const api = {
	catalog: () => request('get', '/catalog'),
	persons: () => request('get', '/persons'),
	/** changes: { birthDate?, scheduleId? } – only the given fields are changed */
	updatePerson: (userId, changes) => request('put', `/persons/${encode(userId)}`, changes),
	records: (userId) => request('get', `/persons/${encode(userId)}/records`),
	status: (userId) => request('get', `/persons/${encode(userId)}/status`),
	createRecord: (userId, record) => request('post', `/persons/${encode(userId)}/records`, record),
	updateRecord: (id, record) => request('put', `/records/${id}`, record),
	deleteRecord: (id) => request('delete', `/records/${id}`),
}
