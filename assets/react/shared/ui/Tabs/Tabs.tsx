import { type FC, ReactNode, useState } from 'react'

import { SegmentedControl } from '@radix-ui/themes'
import { cn } from '@utils/cn'

import styles from './Tabs.module.css'

interface TabsProps {
	items?: { label: ReactNode; value: string }[]
	defaultValue?: string
	onChange?: (value: string) => void
	className?: string
	classNames?: {
		root?: string
		tab?: string
	}
}

export const Tabs: FC<TabsProps> = ({ items, defaultValue, onChange, className, classNames }) => {
	const [value, setValue] = useState(defaultValue || items?.[0]?.value)

	return (
		<SegmentedControl.Root
			defaultValue={defaultValue}
			className={cn(styles.wrapper, className, classNames?.root)}
			size="3"
			radius="full"
			value={value}
		>
			{items?.map((item) => (
				<SegmentedControl.Item
					key={item.value}
					value={item.value}
					className={cn(styles.tab, classNames?.tab)}
					onClick={() => {
						setValue(item.value)
						onChange?.(item.value)
					}}
				>
					{item.label}
				</SegmentedControl.Item>
			))}
		</SegmentedControl.Root>
	)
}
