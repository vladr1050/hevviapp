import { type FC } from 'react'

import carouselSlide2MobileVisual from '../images/carousel-slide-2-mobile-visual.png'
import carouselSlide2Visual from '../images/carousel-slide-2-visual.png'
import styles from './CarouselSlide2Visual.module.css'

/** Figma desktop 1616:2417 (779×500.5); mobile 1616:2658 (389×245.5). */
export const CarouselSlide2Visual: FC = () => (
	<div className={styles.stage} aria-hidden>
		<img
			src={carouselSlide2Visual}
			alt=""
			width={3116}
			height={2002}
			className={styles.visualDesktop}
			loading="lazy"
			decoding="async"
		/>
		<img
			src={carouselSlide2MobileVisual}
			alt=""
			width={1556}
			height={982}
			className={styles.visualMobile}
			loading="lazy"
			decoding="async"
		/>
	</div>
)
