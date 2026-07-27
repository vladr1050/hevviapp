import { type FC, useState } from 'react'

import { EMPTY_STRING, OrderType } from '@config/constants'
import { Checkbox } from '@ui/Checkbox/Checkbox'
import { cn } from '@utils/cn'

import styles from './ContactInfoPanel.module.css'

interface ContactInfoPanelProps {
	order: OrderType
}

const formatPickupWindow = (order: OrderType): string => {
	if (!order.pickup_time_from && !order.pickup_time_to) {
		return 'Anytime'
	}

	return `${order.pickup_time_from ?? ''} – ${order.pickup_time_to ?? ''}`.trim()
}

const formatDeliveryWindow = (order: OrderType): string => {
	if (!order.delivery_time_from && !order.delivery_time_to) {
		return EMPTY_STRING
	}

	return `${order.delivery_time_from} - ${order.delivery_time_to}`
}

export const ContactInfoPanel: FC<ContactInfoPanelProps> = ({ order }) => {
	const [shipperCompanyName, setShipperCompanyName] = useState(order.shipper_company_name ?? '')
	const [shipperPhone, setShipperPhone] = useState(order.shipper_phone ?? '')
	const [shipperContactName, setShipperContactName] = useState(order.shipper_contact_name ?? '')
	const [consigneeSameAsShipper, setConsigneeSameAsShipper] = useState(false)
	const [consigneeCompanyName, setConsigneeCompanyName] = useState(order.consignee_company_name ?? '')
	const [consigneePhone, setConsigneePhone] = useState(order.consignee_phone ?? '')
	const [consigneeContactName, setConsigneeContactName] = useState(order.consignee_contact_name ?? '')

	return (
		<div className={styles.panel}>
			<div className={styles.title}>Add contact info</div>

			<div className={styles.routeItem}>
				<div className={styles.routeWrapper}>
					<div className={styles.route} />
				</div>

				<div className={styles.section}>
					<div className={styles.sectionHeader}>
						<span className={styles.sectionLabel}>Loading:</span>
						<span className={styles.sectionAddress}>{order.address.from || EMPTY_STRING}</span>
					</div>

					<div className={styles.metaRow}>
						<div className={styles.metaItem}>
							<div className={styles.metaLabel}>Loading ready</div>
							<div className={styles.metaValue}>{order.pickup_request_date || EMPTY_STRING}</div>
						</div>
						<div className={styles.metaItem}>
							<div className={styles.metaLabel}>Loading window</div>
							<div className={styles.metaValue}>{formatPickupWindow(order)}</div>
						</div>
					</div>

					<div className={styles.fields}>
						<ContactField
							label="Shippers name"
							name="shipper_company_name"
							placeholder="My company name"
							value={shipperCompanyName}
							onChange={setShipperCompanyName}
						/>
						<ContactField
							label="Phone number"
							name="shipper_phone"
							placeholder="+371 --- --- ---"
							type="tel"
							value={shipperPhone}
							onChange={setShipperPhone}
						/>
						<ContactField
							label="Name"
							name="shipper_contact_name"
							placeholder="Your name"
							value={shipperContactName}
							onChange={setShipperContactName}
						/>
					</div>
				</div>
			</div>

			<div className={styles.routeItem}>
				<div className={styles.routeWrapper}>
					<div className={styles.routeEnd} />
				</div>

				<div className={styles.section}>
					<div className={styles.sectionHeader}>
						<span className={styles.sectionLabel}>Unloading</span>
						<span className={styles.sectionAddress}>{order.address.to || EMPTY_STRING}</span>
					</div>

					<Checkbox
						className={styles.sameCheckbox}
						value={consigneeSameAsShipper}
						onChange={setConsigneeSameAsShipper}
					>
						Same contact information for unloading
					</Checkbox>

					{consigneeSameAsShipper && (
						<input type="hidden" name="consignee_same_as_shipper" value="1" />
					)}

					<div className={styles.metaRow}>
						<div className={styles.metaItem}>
							<div className={styles.metaLabel}>Delivery date</div>
							<div className={styles.metaValue}>{order.delivery_date || EMPTY_STRING}</div>
						</div>
						<div className={styles.metaItem}>
							<div className={styles.metaLabel}>Delivery window</div>
							<div className={styles.metaValue}>{formatDeliveryWindow(order)}</div>
						</div>
					</div>

					{!consigneeSameAsShipper && (
						<div className={styles.fields}>
							<ContactField
								label="Consignee name"
								name="consignee_company_name"
								placeholder="My company name"
								value={consigneeCompanyName}
								onChange={setConsigneeCompanyName}
							/>
							<ContactField
								label="Phone number"
								name="consignee_phone"
								placeholder="+371 --- --- ---"
								type="tel"
								value={consigneePhone}
								onChange={setConsigneePhone}
							/>
							<ContactField
								label="Name"
								name="consignee_contact_name"
								placeholder="Your name"
								value={consigneeContactName}
								onChange={setConsigneeContactName}
							/>
						</div>
					)}
				</div>
			</div>
		</div>
	)
}

interface ContactFieldProps {
	label: string
	name: string
	placeholder: string
	value: string
	onChange: (value: string) => void
	type?: 'text' | 'tel'
}

const ContactField: FC<ContactFieldProps> = ({
	label,
	name,
	placeholder,
	value,
	onChange,
	type = 'text',
}) => (
	<label className={styles.field}>
		<span className={styles.fieldLabel}>{label}</span>
		<input
			className={styles.fieldInput}
			name={name}
			type={type}
			placeholder={placeholder}
			value={value}
			onChange={(e) => onChange(e.target.value)}
		/>
	</label>
)

interface PartyContactDisplayProps {
	companyName?: string
	phone?: string
	contactName?: string
	className?: string
}

export const PartyContactDisplay: FC<PartyContactDisplayProps> = ({
	companyName,
	phone,
	contactName,
	className,
}) => {
	if (!companyName && !phone && !contactName) {
		return null
	}

	return (
		<div className={cn(styles.contactDisplay, className)}>
			{companyName && (
				<div className={styles.contactRow}>
					<div className={styles.contactLabel}>Company name</div>
					<div className={styles.contactValue}>{companyName}</div>
				</div>
			)}
			{phone && (
				<div className={styles.contactRow}>
					<div className={styles.contactLabel}>Phone number</div>
					<div className={styles.contactValue}>{phone}</div>
				</div>
			)}
			{contactName && (
				<div className={styles.contactRow}>
					<div className={styles.contactLabel}>Name</div>
					<div className={styles.contactValue}>{contactName}</div>
				</div>
			)}
		</div>
	)
}
