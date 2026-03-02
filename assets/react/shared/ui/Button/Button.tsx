import type { FC, PropsWithChildren } from 'react'

import { cn } from '@utils/cn'

import styles from './Button.module.css'

interface ButtonProps extends PropsWithChildren {
	onClick?: () => void
	type?: 'button' | 'submit'
	variant?: 'solid' | 'outline'
	className?: string
	disabled?: boolean
	name?: string
	value?: string
}

export const Button: FC<ButtonProps> = ({
	type = 'button',
	onClick,
	variant,
	className,
	children,
	disabled,
	...props
}) => {
	return (
		<button
			type={type}
			onClick={onClick}
			//
			disabled={disabled}
			className={cn(styles.button, { [styles.outline]: variant === 'outline' }, className)}
			{...props}
		>
			{children}
		</button>
	)
}
