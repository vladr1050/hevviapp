import { fetchJson } from './http'

const API_BASE = '/api/auth'

export interface AuthUser {
	id: string
	email: string
	firstName: string | null
	lastName: string | null
	phone: string | null
	locale: string | null
	roles: string[]
}

export interface LoginResponse {
	access_token: string
	refresh_token: string
	expires_in: number
	token_type: string
	account_type: 'user' | 'carrier'
	user: AuthUser
}

export interface RefreshResponse {
	access_token: string
	refresh_token: string
	expires_in: number
	token_type: string
}

export interface ApiError {
	error: string
}

export interface PortalLoginConsentPayload {
	portal_audience: 'sender' | 'carrier'
	terms_accepted: boolean
}

export async function apiLogin(
	email: string,
	password: string,
	consent: PortalLoginConsentPayload
): Promise<LoginResponse> {
	const { data, response } = await fetchJson<LoginResponse | ApiError>(`${API_BASE}/login`, {
		method: 'POST',
		body: JSON.stringify({
			login: email,
			password,
			portal_audience: consent.portal_audience,
			terms_accepted: consent.terms_accepted,
		}),
	})

	if (!response.ok) {
		throw new Error((data as ApiError).error ?? 'Login failed')
	}

	return data as LoginResponse
}

export async function apiResetPassword(email: string): Promise<any> {
	const { data, response } = await fetchJson<LoginResponse | ApiError>(`${API_BASE}/reset-password`, {
		method: 'POST',
		body: JSON.stringify({ login: email }),
	})

	if (!response.ok) {
		throw new Error((data as ApiError).error ?? 'Password reset failed')
	}

	return data as LoginResponse
}

export async function apiRefreshToken(refreshToken: string): Promise<RefreshResponse> {
	const { data, response } = await fetchJson<RefreshResponse | ApiError>(`${API_BASE}/refresh`, {
		method: 'POST',
		body: JSON.stringify({ refresh_token: refreshToken }),
	})

	if (!response.ok) {
		throw new Error((data as ApiError).error ?? 'Token refresh failed')
	}

	return data as RefreshResponse
}

export async function apiLogout(refreshToken: string): Promise<void> {
	await fetchJson(`${API_BASE}/logout`, {
		method: 'POST',
		body: JSON.stringify({ refresh_token: refreshToken }),
	}).catch(() => {})
}

export async function apiGetMe(accessToken: string): Promise<AuthUser> {
	const { data, response } = await fetchJson<{ user: AuthUser } | ApiError>(`${API_BASE}/me`, {
		headers: {
			Authorization: `Bearer ${accessToken}`,
		},
	})

	if (!response.ok) {
		throw new Error((data as ApiError).error ?? 'Unauthorized')
	}

	return (data as { user: AuthUser }).user
}
