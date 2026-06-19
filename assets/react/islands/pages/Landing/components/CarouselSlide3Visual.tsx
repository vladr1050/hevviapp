import { type FC } from 'react'

import carouselSlide3Accent from '../images/carousel-slide-3-accent.png'
import carouselSlide3Content from '../images/carousel-slide-3-content.png'
import styles from './CarouselSlide3Visual.module.css'

/** Figma: Frame 1762 (accent) + map / status phone (Group 1166 + Frame 552). */
export const CarouselSlide3Visual: FC = () => (
	<div className={styles.stage} aria-hidden>
		<img
			src={carouselSlide3Accent}
			alt=""
			width={399}
			height={228}
			className={styles.accent}
			loading="lazy"
			decoding="async"
		/>
		<img
			src={carouselSlide3Content}
			alt=""
			width={1378}
			height={780}
			className={styles.content}
			loading="lazy"
			decoding="async"
		/>
	</div>
)
