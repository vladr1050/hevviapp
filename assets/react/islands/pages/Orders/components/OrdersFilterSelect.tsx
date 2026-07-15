import type { FC, ReactNode } from 'react'

import { Select as RadixSelect } from '@radix-ui/themes'

import styles from './OrdersFilterSelect.module.css'

interface OrdersFilterSelectProps {
	value: string
	options: { value: string; label: ReactNode }[]
	onChange: (value: string) => void
}

export const OrdersFilterSelect: FC<OrdersFilterSelectProps> = ({ value, options, onChange }) => (
	<RadixSelect.Root value={value} onValueChange={onChange}>
		<RadixSelect.Trigger radius="full" className={styles.trigger} />
		<RadixSelect.Content className={styles.content} position="popper" sideOffset={8}>
			<RadixSelect.Group>
				{options.map((option) => (
					<RadixSelect.Item key={option.value} value={option.value} className={styles.item}>
						{option.label}
					</RadixSelect.Item>
				))}
			</RadixSelect.Group>
		</RadixSelect.Content>
	</RadixSelect.Root>
)
