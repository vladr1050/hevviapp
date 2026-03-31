import { type FC, useState } from 'react'
import { SubmitHandler, useForm } from 'react-hook-form'

import { apiUpdateOrder, apiUploadOrderAttachments } from '@api/orderApi'
import { AddOrderModal } from '@components/AddOrderModal/AddOrderModal'
import { CalculateModalType, FormValues } from '@components/AddOrderModal/types'
import { dimensionsCm, formatDate } from '@components/AddOrderModal/utils'
import { OrderCard } from '@components/OrderCard/OrderCard'
import { OrderStatusEnum, OrderType, Routes } from '@config/constants'
import { useAuth } from '@hooks/useAuth'
import { DeviceType, useDevice } from '@hooks/useDevice'
import { Icon } from '@ui/Icon/Icon'
import { Modal } from '@ui/Modal/Modal'
import { cn } from '@utils/cn'
import { parse } from 'date-fns'

import styles from './Order.module.css'

import { MobilePage } from '../MobilePage/MobilePage'

interface OrderPageProps {
	title: string
	order: OrderType
	csrf_token: string
	cancel_order_csrf_token: string
	update_status_csrf_token?: string
	isCarrier?: boolean
	device?: DeviceType
}

const getDefaultDate = (date?: string, time?: { from?: string; to?: string }) => {
	const curDate = date ? parse(date, 'dd.MM.yyyy', new Date()) : undefined

	const curTime = (time?.from && time?.to ? `${time.from}-${time.to}` : 'anytime') as any

	return {
		pickupType: (curDate ? 'pickup_later' : 'pickup_ready') as any,
		pickupDate: curDate,
		pickupMonth: (curDate ? curDate.getMonth() : undefined) as any,
		pickupYear: (curDate ? curDate.getFullYear().toString() : undefined) as any,
		pickupTime: curTime,
	}
}

export const OrderPage: FC<OrderPageProps> = (props) => {
	const { title, order, csrf_token, cancel_order_csrf_token, update_status_csrf_token, isCarrier, device } = props
	console.log(props)

	const { isMobile } = useDevice(device)

	const { getValidAccessToken } = useAuth()

	const [activeTab, setActiveTab] = useState<CalculateModalType>()
	const [submitError, setSubmitError] = useState<string>()

	const { control, register, handleSubmit, watch, setValue } = useForm<FormValues>({
		defaultValues: {
			...order,
			// WHAT
			attachments: [],
			old_attachments: order.attachments,
			manipulatorNeeded: order.manipulator_needed,
			// WHERE
			from: order.address.from,
			to: order.address.to,
			pickupLatitude:
				typeof order?.pickup_latitude === 'undefined' ? undefined : Number(order.pickup_latitude),
			pickupLongitude:
				typeof order?.pickup_longitude === 'undefined' ? undefined : Number(order.pickup_longitude),
			dropoutLatitude:
				typeof order?.dropout_latitude === 'undefined' ? undefined : Number(order.dropout_latitude),
			dropoutLongitude:
				typeof order?.dropout_longitude === 'undefined'
					? undefined
					: Number(order.dropout_longitude),
			// WHEN
			...getDefaultDate(order?.pickup_request_date, {
				from: order?.pickup_time_from,
				to: order?.pickup_time_to,
			}),
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

			const payload = {
				pickupAddress: values.from,
				dropoutAddress: values.to,
				pickupLatitude: values.pickupLatitude ?? null,
				pickupLongitude: values.pickupLongitude ?? null,
				dropoutLatitude: values.dropoutLatitude ?? null,
				dropoutLongitude: values.dropoutLongitude ?? null,
				notes: values.comment || null,
				pickupTimeFrom: values.pickupTime === 'anytime' ? null : values.pickupTime.split('-')[0],
				pickupTimeTo: values.pickupTime === 'anytime' ? null : values.pickupTime.split('-')[1],
				pickupDate,
				stackable: values.stackable,
				manipulatorNeeded: values.manipulatorNeeded,
				cargo: values.cargo.map((item) => ({
					name: item.name,
					quantity: item.quantity,
					weightKg: item.weight,
					dimensionsCm: dimensionsCm(item.width, item.length, item.height),
				})),
				attachments: values.old_attachments,
			}

			console.log(payload)

			const result = await apiUpdateOrder(token, payload, order.id)

			if (!!values?.attachments?.length) {
				await apiUploadOrderAttachments(token, result.id, values.attachments)
			}

			setTimeout(() => {
				window.location.href = `/user/orders/${result.id}`
			}, 2000)
		} catch (err) {
			setSubmitError(err instanceof Error ? err.message : 'Something went wrong')
			setActiveTab('what')
			setValue('_step', 1)
		}
	}

	const isDraft = order.status === OrderStatusEnum.DRAFT

	if (isMobile) return <MobilePage />

	return (
		<>
			<div className={cn('tw-container', styles.page)}>
				<div className={styles.content}>
					{isDraft && !isCarrier ? (
						<button type="button" className={styles.back} onClick={() => setActiveTab('what')}>
							<Icon type="edit" size={18} />
						</button>
					) : (
						<a
							className={styles.back}
							href={isCarrier ? Routes.CARRIER_ORDERS : Routes.USER_ORDERS}
						>
							<Icon type="arrow_right" className="rotate-180" size={18} />
						</a>
					)}

				<OrderCard
					title={title}
					order={order}
					isCarrier={isCarrier}
					csrfToken={csrf_token}
					cancelCsrfToken={cancel_order_csrf_token}
					updateStatusCsrfToken={update_status_csrf_token}
				/>
				</div>
			</div>

			<Modal
				isOpen={!!activeTab}
				onClose={() => setActiveTab(undefined)}
				disableCloseButton
				maxWidth="1200px"
			>
				<AddOrderModal
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
