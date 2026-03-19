import { type FC, useState } from 'react'
import { SubmitHandler, useForm } from 'react-hook-form'

import { apiCreateOrder } from '@api/orderApi'
import { CargoTypeEnum, EMPTY_STRING, ShortOrderType, YearsType } from '@config/constants'
import { useAuth } from '@hooks/useAuth'
import { Button } from '@ui/Button/Button'
import { Icon } from '@ui/Icon/Icon'
import { Modal } from '@ui/Modal/Modal'
import { cn } from '@utils/cn'
import { addDays } from 'date-fns'

import styles from '../../Requests.module.css'

import { InputButton } from '../InputButton/InputButton'
import { ModalContent } from '../ModalContent/ModalContent'
import { dimensionsCm, formatDate, whatLabel, whenLabel, whereLabel } from '../ModalContent/utils'

import { CalculateModalType, FormValues } from './types'

interface RequestsUserProps {
	orders: ShortOrderType[]
}

export const RequestsUser: FC<RequestsUserProps> = ({ orders }) => {
	const currentDate = new Date()
	const currentMonthZeroBased = currentDate.getMonth()
	const currentYear = currentDate.getFullYear()

	const { getValidAccessToken } = useAuth()

	const [activeTab, setActiveTab] = useState<CalculateModalType>()

	const [submitError, setSubmitError] = useState<string>()

	const { control, register, handleSubmit, watch, setValue, resetField } = useForm<FormValues>({
		defaultValues: {
			// WHAT
			cargo: [],
			stackable: true,
			manipulatorNeeded: true,
			// WHEN
			pickupType: 'pickup_ready',
			pickupMonth: currentMonthZeroBased,
			pickupYear: currentYear.toString() as YearsType,
			pickupDate: addDays(new Date(), 1),
			pickupTime: 'anytime',
		},
	})

	const onSubmit: SubmitHandler<FormValues> = async (values) => {
		setSubmitError(undefined)
		setActiveTab('calculate')

		try {
			const token = await getValidAccessToken()
			if (!token) {
				setSubmitError('Session expired. Please log in again.')
				setActiveTab('what')
				return
			}

			const pickupDate = values.pickupType === 'pickup_later' ? formatDate(values.pickupDate) : null

			const result = await apiCreateOrder(token, {
				pickupAddress: values.from,
				dropoutAddress: values.to,
				pickupLatitude: values.pickupLatitude ?? null,
				pickupLongitude: values.pickupLongitude ?? null,
				dropoutLatitude: values.dropoutLatitude ?? null,
				dropoutLongitude: values.dropoutLongitude ?? null,
				notes: values.comments || null,
				pickupTime: values.pickupTime || null,
				pickupDate,
				cargo: values.cargo.map((item) => ({
					type: CargoTypeEnum[item.type] ?? 1,
					quantity: item.quantity,
					weightKg: item.weight,
					dimensionsCm: dimensionsCm(item.width, item.length, item.height),
				})),
				stackable: values.stackable,
				manipulatorNeeded: values.manipulatorNeeded,
			})

			setTimeout(() => {
				window.location.href = `/user/orders/${result.id}`
			}, 2000)
		} catch (err) {
			setSubmitError(err instanceof Error ? err.message : 'Something went wrong')
			setActiveTab('what')
		}
	}

	return (
		<>
			<div className={cn('tw-container', styles.page, styles.sender)}>
				<div className={styles.mainWrapper}>
					<div className={styles.main}>
						<h1 className={styles.title}>
							<span className={styles.light}>Heavy cargo delivery.</span>
							<span>Order in seconds.</span>
							<span>Get in 48 hours.</span>
						</h1>
						<div className={styles.inputButtons}>
							<InputButton
								label="What"
								placeholder={whatLabel(watch('cargo')) || 'Fill cargo parameters'}
								className={cn(styles.inputButton)}
								onClick={() => setActiveTab('what')}
							/>
							<InputButton
								label="Where"
								placeholder={
									whereLabel(
										{ label: watch('from'), lat: watch('pickupLatitude') },
										{ label: watch('to'), lat: watch('dropoutLatitude') }
									) || 'Enter destination'
								}
								className={cn(styles.inputButton)}
								onClick={() => setActiveTab('what')}
							/>
							<InputButton
								label="When"
								placeholder={
									whenLabel(watch('pickupType'), watch('pickupTime'), watch('pickupDate')) ||
									'Add date'
								}
								className={cn(styles.inputButton)}
								onClick={() => setActiveTab('what')}
							/>

							<Button type="button" onClick={() => setActiveTab('what')}>
								Calculate
							</Button>
						</div>
					</div>

					<div className={styles.description}>
						<div className={styles.item}>
							<Icon type="vehicle" size={20} className={styles.icon} />
							500+ vehicle fleet
						</div>
						<div className={styles.item}>
							<Icon type="trust" size={20} className={styles.icon} />
							50+ trusted carriers
						</div>
						<div className={styles.item}>
							<Icon type="box" size={20} className={styles.icon} />
							10,000 m³ capacity available
						</div>
					</div>
				</div>
				{!orders?.length ? (
					<div className={styles.emptyOrders}>
						<h2 className={styles.title}>How does it work?</h2>

						<div className={styles.description}>
							<div className={styles.item}>
								<div className={styles.icon}>
									<Icon type="calculate_price" size={32} />
								</div>
								<div className={styles.text}>
									Calculate price
									<span>in seconds</span>
								</div>
							</div>

							<Icon type="arrow_right" size={23} />

							<div className={styles.item}>
								<div className={styles.icon}>
									<Icon type="confirm_order" size={32} />
								</div>
								<div className={styles.text}>
									Confirm the order
									<span>and make a payment</span>
								</div>
							</div>

							<Icon type="arrow_right" size={23} />

							<div className={styles.item}>
								<div className={styles.icon}>
									<Icon type="vehicle_drive" size={32} />
								</div>
								<div className={styles.text}>
									Get the delivery
									<span>in 48 hours</span>
								</div>
							</div>
						</div>
					</div>
				) : (
					<div className={styles.ordersWrapper}>
						<div className={styles.header}>
							<div className={styles.item}>
								<div className={styles.icon}>
									<Icon type="previous_orders" size={20} />
								</div>
								Your previous orders
							</div>
						</div>

						<div className={styles.orders}>
							{orders?.map((order, index) => (
								<div className={styles.order} key={index}>
									<div className={styles.name}>{order.comment || EMPTY_STRING}</div>
									<div className="">{`${order.item} ${order.type}`}</div>
									<div className="!text-[10px] !leading-[10px]">
										{!!order.address.from && !!order.address.to && (
											<>
												{order.address.from}
												<br />
												→
												<br />
												{order.address.to}
											</>
										)}
									</div>

									<button
										type="button"
										className={styles.button}
										// TODO
										onClick={() => console.log(order.id)}
									>
										Pielietot
									</button>
								</div>
							))}
						</div>
					</div>
				)}
			</div>

			<Modal
				isOpen={!!activeTab}
				onClose={() => setActiveTab(undefined)}
				disableCloseButton
				maxWidth="1200px"
			>
				<ModalContent
					activeTab={activeTab}
					setActiveTab={setActiveTab}
					control={control}
					register={register}
					setValue={setValue}
					watch={watch}
					submitError={submitError}
					onSubmit={handleSubmit(onSubmit)}
				/>
			</Modal>
		</>
	)
}
