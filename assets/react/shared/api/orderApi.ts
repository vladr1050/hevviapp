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
	pickupTime: string | null
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
