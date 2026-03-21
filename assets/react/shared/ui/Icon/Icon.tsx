'use client'

import { type FC } from 'react'

import { cn } from '@utils/cn'

import styles from './Icon.module.css'

import { FileIconNames, IconProps, UIIconNames } from './Icon.types'
import * as FileIcons from './data/file.data'
import * as UIIcons from './data/ui.data'

export const Icon: FC<IconProps> = ({ type, size, className, currentColor }) => {
	// @ts-ignore
	const UiComponent = UIIcons[UIIconNames[type?.toUpperCase()]]

	// @ts-ignore
	const FileIconComponent = FileIcons[FileIconNames[type?.toUpperCase()]]

	const IconComponent = UiComponent || FileIconComponent

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
