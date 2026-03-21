const API_BASE = '/api/orders'

export interface CreateOrderCargoPayload {
	type: 1 | 2
	quantity: number
	weightKg: number
	dimensionsCm: string | null
}

// FIXME PAVEL
export interface CreateOrderPayload {
	pickupAddress: string
	dropoutAddress: string
	pickupLatitude: number | null
	pickupLongitude: number | null
	dropoutLatitude: number | null
	dropoutLongitude: number | null
	notes: string | null
	timeFrom: string | null
	timeTo: string | null
	pickupDate?: string | null
	cargo?: CreateOrderCargoPayload[]
	stackable: boolean
	manipulatorNeeded: boolean
}

export interface CreateOrderResponse {
	id: string
}

export async function apiCreateOrder(
	accessToken: string,
	payload: CreateOrderPayload
): Promise<CreateOrderResponse> {
	const res = await fetch(API_BASE, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			Authorization: `Bearer ${accessToken}`,
		},
		body: JSON.stringify(payload),
	})

	const data = await res.json()

	if (!res.ok) {
		throw new Error((data as { error?: string }).error ?? 'Failed to create order')
	}

	return data as CreateOrderResponse
}

/**
 * Загружает PDF-файлы к существующему заказу.
 * Использует multipart/form-data — браузер сам выставляет boundary.
 * Content-Type НЕ ставим вручную намеренно.
 */
export async function apiUploadOrderAttachments(
	accessToken: string,
	orderId: string,
	files: File[]
): Promise<void> {
	if (!files.length) return

	const formData = new FormData()
	files.forEach((file) => formData.append('files[]', file))

	const res = await fetch(`${API_BASE}/${orderId}/attachments`, {
		method: 'POST',
		headers: {
			Authorization: `Bearer ${accessToken}`,
		},
		body: formData,
	})

	if (!res.ok) {
		const data = await res.json().catch(() => ({}))
		const message = (data as { error?: string }).error
		throw new Error(message ?? `Failed to upload files (HTTP ${res.status})`)
	}
}
