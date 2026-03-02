import { type FC, ReactNode, useEffect, useState } from 'react'

import { Select as RadixSelect } from '@radix-ui/themes'
import { cn } from '@utils/cn'

import styles from './Select.module.css'

interface SelectProps {
	label?: string
	placeholder?: string
	color?: 'black' | 'green' | 'gray'
	values: { value: string; label: ReactNode; disabled?: boolean }[]
	defaultValue?: string
	disabled?: boolean
	value: string
	onChange: (value: string) => void
}

export const Select: FC<SelectProps> = ({
	placeholder,
	color,
	values,
	defaultValue,
	disabled,
	value,
	onChange,
}) => {
	const [curValue, setCurValue] = useState(defaultValue || '')

	useEffect(() => {
		setCurValue(value)
	}, [value])

	return (
		<RadixSelect.Root
			defaultValue={defaultValue}
			disabled={disabled}
			value={curValue}
			onValueChange={(v) => {
				setCurValue(v)
				onChange?.(v)
			}}
		>
			<RadixSelect.Trigger radius="full" placeholder={placeholder} className={styles.trigger} />

			<RadixSelect.Content className={styles.content}>
				<RadixSelect.Group>
					{values.map(({ value, label, disabled }) => (
						<RadixSelect.Item
							key={value}
							value={value}
							disabled={disabled}
							className={cn(styles.item, {
								[styles.black]: color === 'black',
								[styles.gray]: color === 'gray',
								[styles.green]: color === 'green',
							})}
						>
							{label}
						</RadixSelect.Item>
					))}
				</RadixSelect.Group>
			</RadixSelect.Content>
		</RadixSelect.Root>
	)
}
