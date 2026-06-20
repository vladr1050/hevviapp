import { type FC } from 'react'

import carouselSlide1Visual from '../images/carousel-slide-1-visual.png'
import styles from './CarouselSlide1Visual.module.css'

/** Figma 1616:2490 → 1616:2491 «01- desktop» (782×500.5). */
export const CarouselSlide1Visual: FC = () => (
	<div className={styles.stage} aria-hidden>
		<img
			src={carouselSlide1Visual}
			alt=""
			width={3128}
			height={2002}
			className={styles.visual}
			loading="lazy"
			decoding="async"
		/>
	</div>
)
