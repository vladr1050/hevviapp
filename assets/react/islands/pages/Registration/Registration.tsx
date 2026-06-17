import { type FC } from 'react'

import { WaitingListForm } from '@components/WaitingListForm/WaitingListForm'
import { DeviceType, useDevice } from '@hooks/useDevice'
import { Icon } from '@ui/Icon/Icon'
import { cn } from '@utils/cn'

import styles from './Registration.module.css'

interface RegistrationProps {
	device?: DeviceType
}

export const RegistrationPage: FC<RegistrationProps> = ({ device }) => {
	const { isMobile } = useDevice(device)

	return (
		<div className={cn('tw-container', styles.page, { [styles.mobile]: isMobile })}>
			<div className={styles.content}>
				<div className={cn(styles.left, styles.sender, { [styles.mobileLeft]: isMobile })}>
					<div className={styles.info}>
						<h2>
							Heavy
							<br />
							cargo delivery
						</h2>

						<div className={styles.infoWrapper}>
							<div className={styles.item}>
								<div className={styles.iconWrapper}>
									<Icon type="time" size={34} />
								</div>
								<span>
									Order
									<br />
									in seconds
								</span>
							</div>

							<div className={styles.item}>
								<div className={styles.iconWrapper}>
									<Icon type="vehicle_drive" size={34} />
								</div>
								<span>
									Get in
									<br />
									48 hours
								</span>
							</div>
						</div>
					</div>
				</div>

				<div className={styles.right}>
					<div className={styles.formShell}>
						<h1 className={styles.title}>Join waiting list</h1>
						<WaitingListForm variant="page" />
					</div>
				</div>
			</div>
		</div>
	)
}
