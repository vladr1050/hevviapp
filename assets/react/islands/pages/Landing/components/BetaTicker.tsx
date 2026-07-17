import { type FC, useState } from 'react'

import { Icon } from '@ui/Icon/Icon'

import styles from './BetaTicker.module.css'

const TICKER_TEXT = 'Beta version - orders accepted in Latvia'
const DISMISS_KEY = 'hevvi-landing-beta-ticker-dismissed'
const REPEAT_COUNT = 8

const LatviaFlag: FC = () => (
	<svg
		className={styles.flag}
		width="16"
		height="12"
		viewBox="0 0 16 12"
		aria-hidden="true"
		focusable="false"
	>
		<rect width="16" height="12" fill="#9E3039" rx="1" />
		<rect y="4.5" width="16" height="3" fill="#FFFFFF" />
	</svg>
)

const TickerItem: FC = () => (
	<span className={styles.item}>
		<Icon type="vehicle_right" size={16} currentColor className={styles.truck} />
		<span>
			{TICKER_TEXT} <LatviaFlag /> only
		</span>
	</span>
)

export const BetaTicker: FC = () => {
	const [visible, setVisible] = useState(() => {
		if (typeof window === 'undefined') return true
		return sessionStorage.getItem(DISMISS_KEY) !== '1'
	})

	if (!visible) return null

	const handleDismiss = () => {
		sessionStorage.setItem(DISMISS_KEY, '1')
		setVisible(false)
	}

	return (
		<div className={styles.ticker} role="status" aria-label={TICKER_TEXT + ' only'}>
			<div className={styles.viewport}>
				<div className={styles.track}>
					<div className={styles.group}>
						{Array.from({ length: REPEAT_COUNT }, (_, i) => (
							<TickerItem key={`a-${i}`} />
						))}
					</div>
					<div className={styles.group} aria-hidden="true">
						{Array.from({ length: REPEAT_COUNT }, (_, i) => (
							<TickerItem key={`b-${i}`} />
						))}
					</div>
				</div>
			</div>

			<button
				type="button"
				className={styles.close}
				onClick={handleDismiss}
				aria-label="Dismiss announcement"
			>
				<Icon type="x_mark" size={12} currentColor />
			</button>
		</div>
	)
}
