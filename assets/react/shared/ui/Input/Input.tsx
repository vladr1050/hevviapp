import { type FC, useState } from 'react'
import { Control, Controller, UseFormRegisterReturn } from 'react-hook-form'

import { Icon } from '@ui/Icon/Icon'
import { cn } from '@utils/cn'

import styles from './Input.module.css'

interface InputProps {
	label?: string
	placeholder?: string
	defaultValue?: string
	className?: string
	disabled?: boolean
	required?: boolean
	type?: 'text' | 'password' | 'email' | 'search'
	error?: string | boolean

	control: Control<any>
	name: string
}

export const Input: FC<InputProps> = ({
	label,
	placeholder,
	defaultValue,
	className,
	disabled,
	type = 'text',
	required,
	error,
	control,
	name,
}) => {
	const [isPasswordVisible, setIsPasswordVisible] = useState(false)

	return (
		<Controller
			control={control}
			name={name}
			defaultValue={defaultValue}
			render={({ field: { value, onChange } }) => {
				const showIcon = type === 'search' && !value.length

				return (
					<label className={cn(styles.wrapper, className)}>
						{label && <span className={styles.label}>{label}</span>}

						<div
							className={cn(styles.inputWrapper, {
								[styles.error]: error,
								[styles.disabled]: disabled,
							})}
						>
							{showIcon && (
								<span className={styles.icon}>
									<Icon size={16} type="search" className="!w-4" />
								</span>
							)}

							<input
								type={type === 'password' ? (isPasswordVisible ? 'text' : 'password') : type}
								className={cn(styles.input, {
									['!pl-12']: showIcon,
									['!pr-12']: type === 'password',
								})}
								placeholder={placeholder}
								disabled={disabled}
								value={value}
								required={required}
								onChange={(e) => {
									onChange(e)
								}}
							/>

							{type === 'password' && (
								<span
									className={styles.passwordIcon}
									onClick={() => setIsPasswordVisible((v) => !v)}
								>
									<Icon
										className="!animate-fade"
										size={20}
										key={isPasswordVisible ? 'eye' : 'eye_close'}
										type={isPasswordVisible ? 'eye' : 'eye_close'}
									/>
								</span>
							)}
						</div>

						{typeof error === 'string' && (
							<span className="ml-[30px] text-red-600 text-xs">{error}</span>
						)}
					</label>
				)
			}}
		/>
	)
}
