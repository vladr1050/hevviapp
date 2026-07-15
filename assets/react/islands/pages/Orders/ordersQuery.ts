export type OrdersSort = 'newest' | 'oldest'
export type OrdersStatusFilter = 'all' | 'in_transit' | 'delivered' | 'awaiting'

export interface OrdersFiltersState {
	perPage: number
	status: OrdersStatusFilter
	sort: OrdersSort
	page?: number
}

export const buildOrdersUrl = (baseUrl: string, filters: OrdersFiltersState): string => {
	const params = new URLSearchParams()

	if (filters.page && filters.page > 1) {
		params.set('page', String(filters.page))
	}

	if (filters.perPage !== 10) {
		params.set('perPage', String(filters.perPage))
	}

	if (filters.status !== 'all') {
		params.set('status', filters.status)
	}

	if (filters.sort !== 'newest') {
		params.set('sort', filters.sort)
	}

	const query = params.toString()

	return query ? `${baseUrl}?${query}` : baseUrl
}
