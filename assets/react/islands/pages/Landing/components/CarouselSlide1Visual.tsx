import { type FC } from 'react'

import carouselSlide1MobileVisual from '../images/carousel-slide-1-mobile-visual.png'
import carouselSlide1Visual from '../images/carousel-slide-1-visual.png'
import styles from './CarouselSlide1Visual.module.css'

/** Figma desktop 1616:2491 (782×500.5); mobile 1616:2674 (390×245). */
export const CarouselSlide1Visual: FC = () => (
	<div className={styles.stage} aria-hidden>
		<img
			src={carouselSlide1Visual}
			alt=""
			width={3128}
			height={2002}
			className={styles.visualDesktop}
			loading="lazy"
			decoding="async"
		/>
		<img
			src={carouselSlide1MobileVisual}
			alt=""
			width={1560}
			height={980}
			className={styles.visualMobile}
			loading="lazy"
			decoding="async"
		/>
	</div>
)
