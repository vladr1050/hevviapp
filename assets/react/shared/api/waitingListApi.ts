const API_BASE = '/api/public/waiting-list'

export interface WaitingListApiError {
	error: string
	code?: string
}

export async function apiJoinWaitingList(
	email: string,
	phone: string,
	companyWebsite = '',
	type: 'sender' | 'carrier' = 'sender'
): Promise<void> {
	const res = await fetch(API_BASE, {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({
			email,
			phone,
			type,
			company_website: companyWebsite,
		}),
	})

	const data = await res.json()

	if (!res.ok) {
		throw new Error((data as WaitingListApiError).error ?? 'Registration failed')
	}
}
