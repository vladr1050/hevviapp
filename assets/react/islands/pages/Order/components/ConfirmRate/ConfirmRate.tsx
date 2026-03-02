import type { FC } from 'react'

import { FormActions } from '@config/constants'
import { Button } from '@ui/Button/Button'
import { Icon } from '@ui/Icon/Icon'

import styles from './ConfirmRate.module.css'

interface ConfirmRateProps {
	id: string | number | undefined
	from: string
	to: string
	onClose: () => void
	email: string
}

type FormValues = {
	text: string
	rate: number
	id: string
}

export const ConfirmRate: FC<ConfirmRateProps> = ({ id, from, to, onClose, email }) => {
	return (
		<form className={styles.modal} method="POST" action={FormActions.RATE_ORDER}>
			<div className={styles.icon}>
				<Icon type="vehicle_check" size={60} />
			</div>

			<div className={styles.textWrapper}>
				<span>
					{from} → {to}
				</span>
				<span>Order Confirmed</span>
				<span>ID {id}</span>
			</div>

			<div className={styles.title}>We will send you invoice to {email}</div>

			<div className={styles.subtitle}>The order will be shipped once the invoice is paid</div>

			<Button type="button" onClick={onClose} className="!w-full">
				Got It
			</Button>
		</form>
	)
}
