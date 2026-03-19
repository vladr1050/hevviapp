import { type FC, PropsWithChildren } from 'react'

import { Dialog } from '@radix-ui/themes'
import { Icon } from '@ui/Icon/Icon'

import styles from './Modal.module.css'

interface ModalProps extends PropsWithChildren {
	isOpen?: boolean
	onClose: () => void
	disableCloseOutside?: boolean
	disableCloseButton?: boolean
	maxWidth?: string
}

export const Modal: FC<ModalProps> = ({
	isOpen,
	onClose,
	disableCloseOutside,
	disableCloseButton,
	maxWidth = '400px',
	children,
}) => {
	return (
		<Dialog.Root
			open={isOpen}
			onOpenChange={(v) => {
				if (disableCloseOutside) return
				onClose()
			}}
		>
			<Dialog.Content maxWidth={maxWidth} className={styles.content} size="4">
				{!disableCloseButton && (
					<Dialog.Close>
						<button type="button" className={styles.close} onClick={() => onClose()}>
							<Icon type="x_mark" />
						</button>
					</Dialog.Close>
				)}

				<Dialog.Title className="hidden"></Dialog.Title>

				{children}
			</Dialog.Content>
		</Dialog.Root>
	)
}
