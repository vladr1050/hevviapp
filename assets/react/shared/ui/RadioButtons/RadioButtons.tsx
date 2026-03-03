import type { FC } from 'react'
import { Control, Controller } from 'react-hook-form'

import { RadioGroup } from '@radix-ui/themes'

import styles from './RadioButtons.module.css'

interface RadioButtonsProps {
	control: Control<any>
	name: string
	items: { label: string; value: string }[]
	defaultValue?: string
}

export const RadioButtons: FC<RadioButtonsProps> = ({ control, name, items, defaultValue }) => {
	return (
		<Controller
			control={control}
			name={name}
			render={({ field: { value, onChange } }) => (
				<RadioGroup.Root
					defaultValue={defaultValue}
					size="3"
					value={value}
					onValueChange={onChange}
				>
					{items?.map((item) => (
						<RadioGroup.Item value={item.value} className={styles.radio} key={item.value}>
							{item.label}
						</RadioGroup.Item>
					))}
				</RadioGroup.Root>
			)}
		/>
	)
}
