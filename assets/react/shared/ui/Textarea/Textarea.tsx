import type { FC } from 'react'
import { UseFormRegisterReturn } from 'react-hook-form'

import { cn } from '@utils/cn'

import styles from './Textarea.module.css'

interface TextareaProps {
	label?: string
	placeholder?: string
	disabled?: boolean
	rows?: number
	isResizable?: boolean
	error?: string | boolean
	required?: boolean
	className?: string

	register: UseFormRegisterReturn<any>
}

export const Textarea: FC<TextareaProps> = ({
	label,
	placeholder,
	disabled,
	rows = 4,
	isResizable,
	register,
	required,
	error,
	className,
}) => {
	return (
		<label
			className={cn(
				styles.wrapper,
				{ [styles.error]: error, [styles.disabled]: disabled },
				className
			)}
		>
			{label && <span className={styles.label}>{label}</span>}

			<textarea
				className={cn(styles.input, { [styles.disableResize]: !isResizable })}
				placeholder={placeholder}
				disabled={disabled}
				rows={rows}
				{...register}
				required={required}
			/>

			{typeof error === 'string' && <span className="ml-[30px] text-red-600 text-xs">{error}</span>}
		</label>
	)
}
