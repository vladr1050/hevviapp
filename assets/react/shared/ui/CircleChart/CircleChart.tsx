import type { FC, PropsWithChildren } from 'react'
import { useId } from 'react'

import { cn } from '@utils/cn'

import styles from './CircleChart.module.css'

interface CircleChartProps extends PropsWithChildren {
	size: number
	percent: number
	title?: string
	subtitle?: string
	className?: string
	strokeWidth?: number
	countdown?: boolean
}

export const CircleChart: FC<CircleChartProps> = ({
	size,
	percent,
	title,
	subtitle,
	className,
	strokeWidth = 8,
	countdown,
}) => {
	const safePercent = Math.min(Math.max(percent, 0), 100)

	const center = size / 2
	const radius = (size - strokeWidth) / 2
	const circumference = 2 * Math.PI * radius
	const offset = circumference * (1 - safePercent / 100)

	const maskId = useId()

	const end = Math.ceil(360 * (safePercent / 100))
	const start = Math.round(end / 3)
	const middle = Math.round((end * 2) / 3)

	return (
		<div className={cn(styles.wrapper, className)} style={{ width: size, height: size }}>
			<svg
				width={size}
				height={size}
				viewBox={`0 0 ${size} ${size}`}
				className={styles.svg}
				style={countdown ? { transform: 'scale(-1,1)' } : {}}
			>
				<defs>
					<mask id={maskId} maskUnits="userSpaceOnUse">
						<rect x="0" y="0" width={size} height={size} fill="black" />
						<circle
							cx={center}
							cy={center}
							r={radius}
							stroke="white"
							strokeWidth={strokeWidth}
							fill="none"
							strokeLinecap="round"
							strokeDasharray={circumference}
							strokeDashoffset={offset}
							transform={`rotate(-90 ${center} ${center})`}
						/>
					</mask>
				</defs>

				{/* track */}
				<circle
					cx={center}
					cy={center}
					r={radius}
					stroke="rgba(255,255,255,0.12)"
					strokeWidth={strokeWidth}
					fill="none"
				/>

				{countdown ? (
					<foreignObject x="0" y="0" width={size} height={size} mask={`url(#${maskId})`}>
						<div
							// @ts-ignore
							xmlns="http://www.w3.org/1999/xhtml"
							style={{
								width: '100%',
								height: '100%',
								borderRadius: '50%',
								background: `conic-gradient(
									#C1E501 0deg, 
									#D7FF01 ${start}deg,
									#F9FFDB ${middle}deg,
									#F9FFDB ${end}deg,
									#FFFFFF 360deg
								)`,
							}}
						/>
					</foreignObject>
				) : (
					<circle
						cx={center}
						cy={center}
						r={radius}
						stroke="#D7FF01"
						strokeWidth={strokeWidth}
						fill="none"
						strokeLinecap="round"
						strokeDasharray={circumference}
						strokeDashoffset={offset}
						transform={`rotate(-90 ${center} ${center})`}
					/>
				)}
			</svg>

			{(title || subtitle) && (
				<div className={styles.content}>
					{title && <div className={styles.title}>{title}</div>}
					{subtitle && <div className={styles.subtitle}>{subtitle}</div>}
				</div>
			)}
		</div>
	)
}
