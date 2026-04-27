'use client'

import { type FC, PropsWithChildren, useEffect, useId, useState } from 'react'

import { cn } from '@utils/cn'

import styles from './Checkbox.module.css'

interface CheckboxProps extends PropsWithChildren {
	defaultChecked?: boolean
	value?: boolean
	onChange?: (value: any) => void
	required?: boolean
	disabled?: boolean
	disabledWithoutCss?: boolean
	className?: string
	alignTop?: boolean
	color?: 'default' | 'green' | 'gray'
	/** When true, only the box toggles the checkbox; children are outside the &lt;label&gt; (e.g. separate terms link). */
	labelCoversInputOnly?: boolean
}

export const Checkbox: FC<CheckboxProps> = ({
	defaultChecked = false,
	value,
	onChange,
	required,
	disabled,
	disabledWithoutCss,
	className,
	alignTop,
	color = 'default',
	labelCoversInputOnly = false,
	children,
}) => {
	const [isChecked, setIsChecked] = useState(defaultChecked)
	const inputId = useId()
	const textId = useId()

	useEffect(() => {
		if (typeof value !== 'undefined') setIsChecked(value)
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [value])

	const input = (
		<input
			id={inputId}
			type="checkbox"
			checked={isChecked}
			required={required}
			disabled={disabled || disabledWithoutCss}
			aria-labelledby={labelCoversInputOnly && children ? textId : undefined}
			onChange={(e) => {
				setIsChecked(e.target.checked)
				onChange?.(e.target.checked)
			}}
		/>
	)

	return (
		<div
			className={cn(
				styles.checkbox,
				{
					[styles.disabled]: disabled,
					[styles.disabledWithoutCss]: disabledWithoutCss,
					[styles.green]: color === 'green',
					[styles.gray]: color === 'gray',
					[styles.default]: color === 'default',
				},
				className
			)}
		>
			{labelCoversInputOnly ? (
				<div className={cn(styles.row, { [styles.alignTop]: alignTop })}>
					<label className={styles.inputOnlyLabel} htmlFor={inputId}>
						{input}
					</label>
					{children != null && children !== false ? (
						<div id={textId} className={styles.sideContent}>
							{children}
						</div>
					) : null}
				</div>
			) : (
				<label className={cn({ [styles.alignTop]: alignTop })}>
					{input}
					<span>{children}</span>
				</label>
			)}
		</div>
	)
}
