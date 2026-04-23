import { TERMS_CURRENT_URL } from '@config/constants'

export interface TermsCurrentResponse {
	audience: string
	version: number
	title: string
	subtitle: string | null
	publishedAt: string | null
	html: string
}

export async function apiFetchCurrentTerms(accessToken: string): Promise<TermsCurrentResponse> {
	const res = await fetch(TERMS_CURRENT_URL, {
		method: 'GET',
		headers: {
			Authorization: `Bearer ${accessToken}`,
		},
	})

	const data: unknown = await res.json().catch(() => ({}))

	if (!res.ok) {
		const err = typeof data === 'object' && data !== null && 'error' in data ? String((data as { error: unknown }).error) : null
		throw new Error(err || `Terms request failed (${res.status})`)
	}

	return data as TermsCurrentResponse
}
