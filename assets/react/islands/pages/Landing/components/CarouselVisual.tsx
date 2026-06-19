import { type CSSProperties, type FC } from 'react'

import { cn } from '@utils/cn'

import { type LandingSlide } from '../landingAssets'
import styles from './ProductCarousel.module.css'

type CarouselVisualProps = {
	slide: LandingSlide
}

export const CarouselVisual: FC<CarouselVisualProps> = ({ slide }) => (
	<div
		className={cn(styles.visualStage, styles[slide.stageClass])}
		style={
			{
				'--stage-w': slide.stageWidth,
				'--stage-h': slide.stageHeight,
			} as CSSProperties
		}
	>
		{slide.layers.map((layer) => (
			<img
				key={layer.className}
				src={layer.src}
				alt=""
				width={layer.width}
				height={layer.height}
				className={cn(styles.visualLayer, styles[layer.className])}
				loading="lazy"
				decoding="async"
			/>
		))}
	</div>
)
