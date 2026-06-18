import { type FC } from 'react'

import { landingAssets } from '../landingAssets'
import styles from '../Landing.module.css'

export const HeroVisual: FC = () => (
	<div className={styles.heroVisualStack} aria-hidden>
		<div className={styles.heroVisualMapFrame} />
		<img
			src={landingAssets.heroMapContent}
			alt=""
			className={styles.heroVisualMap}
			width={429}
			height={265}
			loading="eager"
		/>
		<img
			src={landingAssets.heroMapGradient}
			alt=""
			className={styles.heroVisualGradient}
			width={165}
			height={289}
			loading="eager"
		/>
		<img
			src={landingAssets.heroCargo}
			alt=""
			className={styles.heroVisualCargo}
			width={515}
			height={356}
			loading="eager"
		/>
		<img
			src={landingAssets.heroDeliveryPanel}
			alt=""
			className={styles.heroVisualDelivery}
			width={255}
			height={140}
			loading="eager"
		/>
	</div>
)
