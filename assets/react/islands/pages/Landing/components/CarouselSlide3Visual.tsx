import { type FC } from 'react'

import carouselSlide3Visual from '../images/carousel-slide-3-visual.png'
import styles from './CarouselSlide3Visual.module.css'

/** Figma 1616:2573 → 1616:2574 «03- desktop» (756.5×499). */
export const CarouselSlide3Visual: FC = () => (
	<div className={styles.stage} aria-hidden>
		<img
			src={carouselSlide3Visual}
			alt=""
			width={3026}
			height={1996}
			className={styles.visual}
			loading="lazy"
			decoding="async"
		/>
	</div>
)
