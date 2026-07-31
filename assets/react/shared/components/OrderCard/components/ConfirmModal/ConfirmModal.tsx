import type { FC } from 'react'

import { Button } from '@ui/Button/Button'
import { Icon } from '@ui/Icon/Icon'

import styles from './ConfirmModal.module.css'

interface ConfirmModalProps {
	id: string | number | undefined
	from?: string
	to?: string
	onClose: () => void
	email: string
}

export const ConfirmModal: FC<ConfirmModalProps> = ({ id, from, to, onClose, email }) => {
	return (
		<div className={styles.modal}>
			<div className={styles.icon}>
				<Icon type="vehicle_check" size={60} />
			</div>

			<div className={styles.textWrapper}>
				<span>
					{!!from && !!to && (
						<>
							{from} → {to}
						</>
					)}
				</span>
				<span>Order Confirmed</span>
				<span>ID {id}</span>
			</div>

			<div className={styles.title}>We will send you invoice to {email}</div>

			<div className={styles.subtitle}>The order will be shipped once the invoice is paid</div>

			<Button type="button" onClick={onClose} className="!w-full">
				Got It
			</Button>
		</div>
	)
}
