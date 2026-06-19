import { type FC } from 'react'

import carouselSlide2Accent from '../images/carousel-slide-2-accent.png'
import carouselSlide2Card from '../images/carousel-slide-2-card.png'
import styles from './CarouselSlide2Visual.module.css'

/** Figma: Frame 1762 (accent) + Group 1063 (price card). */
export const CarouselSlide2Visual: FC = () => (
	<div className={styles.stage} aria-hidden>
		<img
			src={carouselSlide2Accent}
			alt=""
			width={507}
			height={290}
			className={styles.accent}
			loading="lazy"
			decoding="async"
		/>
		<img
			src={carouselSlide2Card}
			alt=""
			width={1318}
			height={760}
			className={styles.card}
			loading="lazy"
			decoding="async"
		/>
	</div>
)
