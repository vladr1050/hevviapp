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

export async function apiLogin(email: string, password: string): Promise<LoginResponse> {
	const res = await fetch(`${API_BASE}/login`, {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({ login: email, password }),
	})

	const data = await res.json()

	if (!res.ok) {
		throw new Error((data as ApiError).error ?? 'Login failed')
	}

	return data as LoginResponse
}

export async function apiResetPassword(email: string): Promise<any> {
	// FIXME PAVEL
	const res = await fetch(`${API_BASE}/reset-password`, {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({ login: email }),
	})

	const data = await res.json()

	if (!res.ok) {
		throw new Error((data as ApiError).error ?? 'Password reset failed')
	}

	return data as LoginResponse
}

export async function apiRefreshToken(refreshToken: string): Promise<RefreshResponse> {
	const res = await fetch(`${API_BASE}/refresh`, {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({ refresh_token: refreshToken }),
	})

	const data = await res.json()

	if (!res.ok) {
		throw new Error((data as ApiError).error ?? 'Token refresh failed')
	}

	return data as RefreshResponse
}

export async function apiLogout(refreshToken: string): Promise<void> {
	await fetch(`${API_BASE}/logout`, {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({ refresh_token: refreshToken }),
	}).catch(() => {})
}

export async function apiGetMe(accessToken: string): Promise<AuthUser> {
	const res = await fetch(`${API_BASE}/me`, {
		headers: {
			Authorization: `Bearer ${accessToken}`,
		},
	})

	const data = await res.json()

	if (!res.ok) {
		throw new Error((data as ApiError).error ?? 'Unauthorized')
	}

	return (data as { user: AuthUser }).user
}
