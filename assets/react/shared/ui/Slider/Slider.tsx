import { type FC, useEffect, useId, useState } from 'react'

import styles from './Slider.module.css'

interface SliderProps {
	min?: number
	max?: number
	step?: number
	defaultValue?: number
	label?: string
	value: number
	setValue?: (value: number) => void
}

export const Slider: FC<SliderProps> = ({
	min = 0,
	max = 100,
	step = 10,
	defaultValue = 0,
	label,
	value,
	setValue,
}) => {
	const id = useId()

	const [curValue, setCurValue] = useState(defaultValue)

	useEffect(() => {
		setCurValue(value)
	}, [value])

	return (
		<div className={styles.wrapper}>
			{label && (
				<label htmlFor={id} className={styles.label}>
					{label}
				</label>
			)}

			<input
				type="range"
				id={id}
				min={min}
				max={max}
				step={step}
				value={curValue}
				onChange={(e) => {
					setCurValue(Number(e.target.value))
					setValue?.(Number(e.target.value))
				}}
			/>
		</div>
	)
}
