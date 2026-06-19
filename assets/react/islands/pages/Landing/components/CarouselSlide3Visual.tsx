import { type FC } from 'react'

import carouselSlide3Accent from '../images/carousel-slide-3-accent.png'
import carouselSlide3Frame from '../images/carousel-slide-3-frame.png'
import carouselSlide3Map from '../images/carousel-slide-3-map.png'
import carouselSlide3Phone from '../images/carousel-slide-3-phone.png'
import styles from './CarouselSlide3Visual.module.css'

/** Figma: Frame 1762 + Frame 1933 + map + Frame 552 (status phone). */
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
			src={carouselSlide3Frame}
			alt=""
			width={1064}
			height={732}
			className={styles.frame}
			loading="lazy"
			decoding="async"
		/>
		<img
			src={carouselSlide3Map}
			alt=""
			width={1024}
			height={692}
			className={styles.map}
			loading="lazy"
			decoding="async"
		/>
		<img
			src={carouselSlide3Phone}
			alt=""
			width={378}
			height={780}
			className={styles.phone}
			loading="lazy"
			decoding="async"
		/>
	</div>
)
