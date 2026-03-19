import type { FC, ReactNode } from 'react'

import { cn } from '@utils/cn'

import styles from './InputButton.module.css'

interface InputButtonProps {
	label: ReactNode
	placeholder: string
	onClick: () => void
	className?: string
}

export const InputButton: FC<InputButtonProps> = ({ className, label, placeholder, onClick }) => {
	return (
		<button type="button" className={cn(styles.button, className)} onClick={onClick}>
			<div data-name="label" id="label">
				{label}
			</div>
			<div data-name="placeholder" id="placeholder" title={placeholder}>
				{placeholder}
			</div>
		</button>
	)
}
