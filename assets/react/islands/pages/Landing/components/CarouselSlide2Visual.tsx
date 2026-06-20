import { type FC } from 'react'

import carouselSlide2Visual from '../images/carousel-slide-2-visual.png'
import styles from './CarouselSlide2Visual.module.css'

/** Figma 1616:2416 → 1616:2417 «02- desktop» (779×500.5). */
export const CarouselSlide2Visual: FC = () => (
	<div className={styles.stage} aria-hidden>
		<img
			src={carouselSlide2Visual}
			alt=""
			width={3116}
			height={2002}
			className={styles.visual}
			loading="lazy"
			decoding="async"
		/>
	</div>
)
