'use client'

import { type FC, PropsWithChildren, useEffect, useState } from 'react'

import { cn } from '@utils/cn'

import styles from './Checkbox.module.css'

interface CheckboxProps extends PropsWithChildren {
	defaultChecked?: boolean
	value?: boolean
	onChange?: (value: any) => void
	required?: boolean
	disabled?: boolean
	className?: string
	alignTop?: boolean
	color?: 'default' | 'green' | 'gray'
}

export const Checkbox: FC<CheckboxProps> = ({
	defaultChecked = false,
	value,
	onChange,
	required,
	disabled,
	className,
	alignTop,
	color = 'default',
	children,
}) => {
	const [isChecked, setIsChecked] = useState(defaultChecked)

	useEffect(() => {
		if (typeof value !== 'undefined') setIsChecked(value)
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [value])

	return (
		<div
			className={cn(
				styles.checkbox,
				{
					[styles.disabled]: disabled,
					[styles.green]: color === 'green',
					[styles.gray]: color === 'gray',
					[styles.default]: color === 'default',
				},
				className
			)}
		>
			<label className={cn({ [styles.alignTop]: alignTop })}>
				<input
					type="checkbox"
					checked={isChecked}
					required={required}
					disabled={disabled}
					onChange={(e) => {
						setIsChecked(e.target.checked)
						onChange?.(e.target.checked)
					}}
				/>
				<span>{children}</span>
			</label>
		</div>
	)
}
