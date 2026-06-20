import { type FC } from 'react'

import carouselSlide3MobileVisual from '../images/carousel-slide-3-mobile-visual.png'
import carouselSlide3Visual from '../images/carousel-slide-3-visual.png'
import styles from './CarouselSlide3Visual.module.css'

/** Figma desktop 1616:2574 (756.5×499); mobile 1616:2690 (382×244 @ 4,114 in 390 frame). */
export const CarouselSlide3Visual: FC = () => (
	<div className={styles.stage} aria-hidden>
		<img
			src={carouselSlide3Visual}
			alt=""
			width={3026}
			height={1996}
			className={styles.visualDesktop}
			loading="lazy"
			decoding="async"
		/>
		<img
			src={carouselSlide3MobileVisual}
			alt=""
			width={1528}
			height={976}
			className={styles.visualMobile}
			loading="lazy"
			decoding="async"
		/>
	</div>
)
