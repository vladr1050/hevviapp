import { type FC, useCallback, useEffect, useRef, useState } from 'react'

import { cn } from '@utils/cn'

import { CarouselVisual } from './CarouselVisual'
import { landingAssets, landingSlides } from '../landingAssets'
import styles from './ProductCarousel.module.css'

const AUTOPLAY_MS = 5000

export const ProductCarousel: FC = () => {
	const [active, setActive] = useState(0)
	const sectionRef = useRef<HTMLElement>(null)
	const [isVisible, setIsVisible] = useState(false)
	const manualUntilRef = useRef(0)

	const goNext = useCallback(() => {
		setActive((prev) => (prev + 1) % landingSlides.length)
	}, [])

	useEffect(() => {
		const node = sectionRef.current
		if (!node) {
			return
		}

		const observer = new IntersectionObserver(
			([entry]) => setIsVisible(entry.isIntersecting),
			{ threshold: 0.35 }
		)
		observer.observe(node)

		return () => observer.disconnect()
	}, [])

	useEffect(() => {
		if (!isVisible) {
			return
		}

		const timer = window.setInterval(() => {
			if (Date.now() < manualUntilRef.current) {
				return
			}
			goNext()
		}, AUTOPLAY_MS)

		return () => window.clearInterval(timer)
	}, [isVisible, goNext])

	const slide = landingSlides[active]

	return (
		<section ref={sectionRef} className={styles.section} aria-label="How does it work">
			<h2 className={styles.title}>How does it work?</h2>

			<div className={styles.track}>
				<div className={styles.slide}>
					<div className={styles.visual}>
						<CarouselVisual slide={slide} />
					</div>

					<div className={styles.copy}>
						<div className={styles.textBlock}>
							<span className={styles.step}>{slide.step}</span>
							<h3>{slide.title}</h3>
							<p>{slide.description}</p>
						</div>

						<div className={styles.slideNav}>
							<div className={styles.indicators} aria-hidden="true">
								{landingSlides.map((item, index) => (
									<span
										key={item.step}
										className={cn(styles.indicator, {
											[styles.indicatorActive]: index === active,
										})}
									/>
								))}
							</div>
							<button
								type="button"
								className={styles.nextButton}
								aria-label="Next slide"
								onClick={() => {
									manualUntilRef.current = Date.now() + AUTOPLAY_MS
									goNext()
								}}
							>
								<img src={landingAssets.carouselArrow} alt="" className={styles.nextIcon} />
							</button>
						</div>
					</div>
				</div>
			</div>
		</section>
	)
}
