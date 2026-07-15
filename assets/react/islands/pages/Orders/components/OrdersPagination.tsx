import type { FC } from 'react'

import styles from './OrdersPagination.module.css'

interface OrdersPaginationProps {
	baseUrl: string
	page: number
	totalPages: number
}

const getVisiblePages = (current: number, total: number): number[] => {
	if (total <= 5) {
		return Array.from({ length: total }, (_, index) => index + 1)
	}

	let start = Math.max(1, current - 2)
	const end = Math.min(total, start + 4)
	start = Math.max(1, end - 4)

	return Array.from({ length: end - start + 1 }, (_, index) => start + index)
}

const pageHref = (baseUrl: string, page: number): string =>
	page <= 1 ? baseUrl : `${baseUrl}?page=${page}`

export const OrdersPagination: FC<OrdersPaginationProps> = ({ baseUrl, page, totalPages }) => {
	if (totalPages <= 1) {
		return null
	}

	const visiblePages = getVisiblePages(page, totalPages)

	return (
		<nav className={styles.pagination} aria-label="Orders pagination">
			<a
				className={styles.arrow}
				href={pageHref(baseUrl, page - 1)}
				aria-label="Previous page"
				aria-disabled={page <= 1}
				tabIndex={page <= 1 ? -1 : undefined}
				onClick={(event) => page <= 1 && event.preventDefault()}
			>
				‹
			</a>

			{visiblePages.map((pageNumber) => (
				<a
					key={pageNumber}
					href={pageHref(baseUrl, pageNumber)}
					className={pageNumber === page ? styles.pageActive : styles.page}
					aria-current={pageNumber === page ? 'page' : undefined}
				>
					{pageNumber}
				</a>
			))}

			<a
				className={styles.arrow}
				href={pageHref(baseUrl, page + 1)}
				aria-label="Next page"
				aria-disabled={page >= totalPages}
				tabIndex={page >= totalPages ? -1 : undefined}
				onClick={(event) => page >= totalPages && event.preventDefault()}
			>
				›
			</a>
		</nav>
	)
}
