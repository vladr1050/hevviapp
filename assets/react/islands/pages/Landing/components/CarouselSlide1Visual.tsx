import { type FC } from 'react'

import { landingAssets } from '../landingAssets'
import styles from './CarouselSlide1Visual.module.css'

export const CarouselSlide1Visual: FC = () => (
	<div className={styles.root} aria-hidden>
		<div className={styles.stage}>
			<div className={styles.accent} />
			<img
				src={landingAssets.carouselSlide1Visual}
				alt=""
				width={661.5}
				height={380.4}
				className={styles.form}
				loading="lazy"
				decoding="async"
			/>
			<img
				src={landingAssets.carouselSlide1Cursor}
				alt=""
				width={19.5}
				height={31.2}
				className={styles.cursor}
				loading="lazy"
				decoding="async"
			/>
		</div>
	</div>
)
