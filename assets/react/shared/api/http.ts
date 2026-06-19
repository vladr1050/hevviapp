const JSON_CONTENT_TYPE = 'application/json'

export class UnexpectedApiResponseError extends Error {
	constructor(
		message: string,
		readonly status: number,
		readonly isHtml: boolean
	) {
		super(message)
		this.name = 'UnexpectedApiResponseError'
	}
}

function isHtmlResponse(text: string, contentType: string): boolean {
	const trimmed = text.trimStart()

	if (trimmed.startsWith('<!DOCTYPE') || trimmed.startsWith('<html') || trimmed.startsWith('<')) {
		return true
	}

	return contentType.length > 0 && !contentType.includes(JSON_CONTENT_TYPE) && trimmed.length > 0
}

function unexpectedResponseMessage(status: number): string {
	if (status >= 500) {
		return 'Server error. Please try again in a moment.'
	}

	if (status === 404) {
		return 'Service is temporarily unavailable. Refresh the page and try again.'
	}

	if (status >= 400) {
		return `Request failed (${status}). Please try again.`
	}

	return 'Unexpected server response. Refresh the page and try again.'
}

function sleep(ms: number): Promise<void> {
	return new Promise((resolve) => window.setTimeout(resolve, ms))
}

function isRetryable(status: number, isHtml: boolean): boolean {
	if (isHtml) {
		return true
	}

	return status === 404 || status === 408 || status === 425 || status === 429 || status >= 502
}

export async function readJsonResponse<T>(res: Response): Promise<T> {
	const contentType = res.headers.get('content-type') ?? ''
	const text = await res.text()

	if (!text) {
		return {} as T
	}

	if (isHtmlResponse(text, contentType)) {
		throw new UnexpectedApiResponseError(unexpectedResponseMessage(res.status), res.status, true)
	}

	try {
		return JSON.parse(text) as T
	} catch {
		throw new UnexpectedApiResponseError(unexpectedResponseMessage(res.status), res.status, false)
	}
}

export const jsonRequestHeaders: HeadersInit = {
	'Content-Type': JSON_CONTENT_TYPE,
	Accept: JSON_CONTENT_TYPE,
	'X-Requested-With': 'XMLHttpRequest',
}

function buildRequestUrl(url: string, retry: boolean): string {
	if (!retry) {
		return url
	}

	const separator = url.includes('?') ? '&' : '?'

	return `${url}${separator}_retry=${Date.now()}`
}

function buildRequestInit(init: RequestInit | undefined, retry: boolean): RequestInit {
	return {
		...init,
		cache: 'no-store',
		credentials: 'same-origin',
		headers: {
			...jsonRequestHeaders,
			...(init?.headers ?? {}),
			...(retry
				? {
						'Cache-Control': 'no-cache',
						Pragma: 'no-cache',
					}
				: {}),
		},
	}
}

export interface FetchJsonResult<T> {
	data: T
	response: Response
}

/** Fetch JSON with one automatic retry when the server returns HTML or a transient error. */
export async function fetchJson<T>(url: string, init?: RequestInit): Promise<FetchJsonResult<T>> {
	const attempts = 2
	let lastError: Error | null = null

	for (let attempt = 0; attempt < attempts; attempt++) {
		const retry = attempt > 0
		const response = await fetch(buildRequestUrl(url, retry), buildRequestInit(init, retry))

		try {
			const data = await readJsonResponse<T>(response)

			return { data, response }
		} catch (error) {
			const apiError =
				error instanceof UnexpectedApiResponseError
					? error
					: new UnexpectedApiResponseError(unexpectedResponseMessage(response.status), response.status, false)

			lastError = apiError

			if (retry || !isRetryable(apiError.status, apiError.isHtml)) {
				throw apiError
			}

			await sleep(300)
		}
	}

	throw lastError ?? new Error('Request failed')
}
