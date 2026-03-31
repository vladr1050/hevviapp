import { type FC, useId, useState } from 'react'

import { cn } from '@utils/cn'

import styles from './Switch.module.css'

interface SwitchProps {
	disabled?: boolean
	checked?: boolean
	label?: string
	onChange?: (value: boolean) => void
}

export const Switch: FC<SwitchProps> = ({ disabled, checked, label, onChange }) => {
	const [isChecked, setIsChecked] = useState(checked)
	const id = useId()

	return (
		<div className="flex items-center gap-3">
			<div className={cn(styles.container, { [styles.disabled]: disabled })}>
				<input
					id={id}
					type="checkbox"
					checked={isChecked}
					disabled={disabled}
					className={styles.checkbox}
					onChange={(e) => {
						setIsChecked(e.target.checked)
						onChange?.(e.target.checked)
					}}
				/>
				<label className={styles.switch} htmlFor={id}>
					<span className={styles.slider}></span>
				</label>
			</div>

			{label && (
				<span
					className={cn(styles.label, { [styles.disabled]: disabled })}
					onClick={() => {
						if (disabled) return
						onChange?.(!isChecked)
						setIsChecked(!isChecked)
					}}
				>
					{label}
				</span>
			)}
		</div>
	)
}
