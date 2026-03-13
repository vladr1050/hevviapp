import type { FC } from 'react'

import { cn } from '@utils/cn'

import styles from './MobilePage.module.css'

interface MobilePageProps {}

export const MobilePage: FC<MobilePageProps> = () => {
	return (
		<div className={cn('tw-container', styles.page)}>
			<h1>Beta version available only on desktop devices</h1>
			<span>Please login with PC or your laptop</span>
		</div>
	)
}
