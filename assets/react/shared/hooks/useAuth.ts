import { useState, useCallback, useEffect } from 'react'

import { type AuthUser, apiRefreshToken, apiLogout } from '@api/authApi'

const STORAGE_KEYS = {
	ACCESS_TOKEN:  'auth_access_token',
	REFRESH_TOKEN: 'auth_refresh_token',
	EXPIRES_AT:    'auth_expires_at',
	USER:          'auth_user',
} as const

/** Decode JWT payload without verifying signature (client-side only) */
function decodeJwtPayload(token: string): Record<string, unknown> | null {
	try {
		const parts = token.split('.')
		if (parts.length !== 3) return null
		const payload = parts[1].replace(/-/g, '+').replace(/_/g, '/')
		return JSON.parse(atob(payload))
	} catch {
		return null
	}
}

function isTokenExpired(token: string): boolean {
	const payload = decodeJwtPayload(token)
	if (!payload || typeof payload.exp !== 'number') return true
	return payload.exp * 1000 < Date.now()
}

export interface AuthState {
	accessToken:  string | null
	refreshToken: string | null
	user:         AuthUser | null
	isLoggedIn:   boolean
}

export function saveTokens(
	accessToken: string,
	refreshToken: string,
	expiresIn: number,
	user: AuthUser,
): void {
	localStorage.setItem(STORAGE_KEYS.ACCESS_TOKEN,  accessToken)
	localStorage.setItem(STORAGE_KEYS.REFRESH_TOKEN, refreshToken)
	localStorage.setItem(STORAGE_KEYS.EXPIRES_AT,    String(Date.now() + expiresIn * 1000))
	localStorage.setItem(STORAGE_KEYS.USER,          JSON.stringify(user))
}

export function clearTokens(): void {
	Object.values(STORAGE_KEYS).forEach((key) => localStorage.removeItem(key))
}

function loadFromStorage(): AuthState {
	const accessToken  = localStorage.getItem(STORAGE_KEYS.ACCESS_TOKEN)
	const refreshToken = localStorage.getItem(STORAGE_KEYS.REFRESH_TOKEN)
	const userRaw      = localStorage.getItem(STORAGE_KEYS.USER)

	let user: AuthUser | null = null
	try {
		user = userRaw ? (JSON.parse(userRaw) as AuthUser) : null
	} catch {
		user = null
	}

	return {
		accessToken:  accessToken,
		refreshToken: refreshToken,
		user,
		isLoggedIn: !!accessToken && !isTokenExpired(accessToken),
	}
}

export function useAuth() {
	const [state, setState] = useState<AuthState>(loadFromStorage)

	const logout = useCallback(async () => {
		if (state.refreshToken) {
			await apiLogout(state.refreshToken)
		}
		clearTokens()
		setState({ accessToken: null, refreshToken: null, user: null, isLoggedIn: false })
	}, [state.refreshToken])

	/** Returns a valid access token, refreshing it if expired */
	const getValidAccessToken = useCallback(async (): Promise<string | null> => {
		const current = localStorage.getItem(STORAGE_KEYS.ACCESS_TOKEN)
		const refresh = localStorage.getItem(STORAGE_KEYS.REFRESH_TOKEN)

		if (!refresh) {
			clearTokens()
			setState({ accessToken: null, refreshToken: null, user: null, isLoggedIn: false })
			return null
		}

		if (current && !isTokenExpired(current)) {
			return current
		}

		try {
			const result = await apiRefreshToken(refresh)

			const userRaw = localStorage.getItem(STORAGE_KEYS.USER)
			const user    = userRaw ? (JSON.parse(userRaw) as AuthUser) : null

			localStorage.setItem(STORAGE_KEYS.ACCESS_TOKEN,  result.access_token)
			localStorage.setItem(STORAGE_KEYS.REFRESH_TOKEN, result.refresh_token)
			localStorage.setItem(STORAGE_KEYS.EXPIRES_AT,    String(Date.now() + result.expires_in * 1000))

			setState((prev) => ({
				...prev,
				accessToken:  result.access_token,
				refreshToken: result.refresh_token,
				user,
				isLoggedIn: true,
			}))

			return result.access_token
		} catch {
			clearTokens()
			setState({ accessToken: null, refreshToken: null, user: null, isLoggedIn: false })
			return null
		}
	}, [])

	useEffect(() => {
		const refreshToken = localStorage.getItem(STORAGE_KEYS.REFRESH_TOKEN)
		const accessToken  = localStorage.getItem(STORAGE_KEYS.ACCESS_TOKEN)

		if (!refreshToken) return

		if (!accessToken || isTokenExpired(accessToken)) {
			getValidAccessToken()
		}
	}, [getValidAccessToken])

	return {
		...state,
		logout,
		getValidAccessToken,
	}
}
