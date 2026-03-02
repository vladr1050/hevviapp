'use client'

import { type FC } from 'react'

import { cn } from '@utils/cn'

import styles from './Icon.module.css'

import { IconProps, UIIconNames } from './Icon.types'
import * as UIIcons from './data/ui.data'

export const Icon: FC<IconProps> = ({ type, size, className, currentColor }) => {
	// @ts-ignore
	const IconComponent = UIIcons[UIIconNames[type?.toUpperCase()]]

	if (!IconComponent) return null

	return (
		<div className={cn(styles.wrapper, className)}>
			<div
				className={cn(styles.icon, { [styles.currentColor]: !!currentColor })}
				style={!size ? undefined : { height: size }}
			>
				<IconComponent />
			</div>
		</div>
	)
}
