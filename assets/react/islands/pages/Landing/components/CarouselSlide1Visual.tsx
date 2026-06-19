import { type FC } from 'react'

import carouselSlide1Accent from '../images/carousel-slide-1-accent.png'
import carouselSlide1Form from '../images/carousel-slide-1-form.png'
import styles from './CarouselSlide1Visual.module.css'

/** Figma: Frame 1762 (accent) + Frame 1859 (form). */
export const CarouselSlide1Visual: FC = () => (
	<div className={styles.stage} aria-hidden>
		<img
			src={carouselSlide1Accent}
			alt=""
			width={507}
			height={290}
			className={styles.accent}
			loading="lazy"
			decoding="async"
		/>
		<img
			src={carouselSlide1Form}
			alt=""
			width={661.5}
			height={380.4}
			className={styles.form}
			loading="lazy"
			decoding="async"
		/>
	</div>
)
