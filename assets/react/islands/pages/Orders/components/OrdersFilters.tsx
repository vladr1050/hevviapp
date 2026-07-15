import type { FC } from 'react'

import { buildOrdersUrl, type OrdersFiltersState } from '../ordersQuery'

import { OrdersFilterSelect } from './OrdersFilterSelect'
import styles from './OrdersFilters.module.css'

interface OrdersFiltersProps {
	baseUrl: string
	filters: OrdersFiltersState
}

const PER_PAGE_OPTIONS = [
	{ value: '10', label: '10 results on page' },
	{ value: '20', label: '20 results on page' },
	{ value: '50', label: '50 results on page' },
]

const STATUS_OPTIONS = [
	{ value: 'all', label: 'Show all orders' },
	{ value: 'in_transit', label: 'In transit' },
	{ value: 'delivered', label: 'Delivered' },
	{ value: 'awaiting', label: 'Awaiting' },
]

const SORT_OPTIONS = [
	{ value: 'newest', label: 'Newest first' },
	{ value: 'oldest', label: 'Oldest first' },
]

export const OrdersFilters: FC<OrdersFiltersProps> = ({ baseUrl, filters }) => {
	const navigate = (next: Partial<OrdersFiltersState>) => {
		window.location.href = buildOrdersUrl(baseUrl, {
			perPage: next.perPage ?? filters.perPage,
			status: next.status ?? filters.status,
			sort: next.sort ?? filters.sort,
		})
	}

	return (
		<div className={styles.filters}>
			<OrdersFilterSelect
				value={String(filters.perPage)}
				options={PER_PAGE_OPTIONS}
				onChange={(value) => navigate({ perPage: Number(value) })}
			/>
			<OrdersFilterSelect
				value={filters.status}
				options={STATUS_OPTIONS}
				onChange={(value) => navigate({ status: value as OrdersFiltersState['status'] })}
			/>
			<OrdersFilterSelect
				value={filters.sort}
				options={SORT_OPTIONS}
				onChange={(value) => navigate({ sort: value as OrdersFiltersState['sort'] })}
			/>
		</div>
	)
}
