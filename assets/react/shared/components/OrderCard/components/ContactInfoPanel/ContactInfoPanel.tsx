import { type FC, useEffect, useState } from 'react'

import { OrderType } from '@config/constants'
import { Checkbox } from '@ui/Checkbox/Checkbox'
import { cn } from '@utils/cn'

import styles from './ContactInfoPanel.module.css'

const isFilled = (value: string): boolean => value.trim().length > 0

export type OrderContactFormState = {
	shipperCompanyName: string
	setShipperCompanyName: (v: string) => void
	shipperPhone: string
	setShipperPhone: (v: string) => void
	shipperContactName: string
	setShipperContactName: (v: string) => void
	consigneeSameAsShipper: boolean
	setConsigneeSameAsShipper: (v: boolean) => void
	consigneeCompanyName: string
	setConsigneeCompanyName: (v: string) => void
	consigneePhone: string
	setConsigneePhone: (v: string) => void
	consigneeContactName: string
	setConsigneeContactName: (v: string) => void
	isValid: boolean
}

export function useOrderContactForm(
	order: OrderType,
	onValidityChange?: (valid: boolean) => void,
): OrderContactFormState {
	const [shipperCompanyName, setShipperCompanyName] = useState(order.shipper_company_name ?? '')
	const [shipperPhone, setShipperPhone] = useState(order.shipper_phone ?? '')
	const [shipperContactName, setShipperContactName] = useState(order.shipper_contact_name ?? '')
	const [consigneeSameAsShipper, setConsigneeSameAsShipper] = useState(false)
	const [consigneeCompanyName, setConsigneeCompanyName] = useState(order.consignee_company_name ?? '')
	const [consigneePhone, setConsigneePhone] = useState(order.consignee_phone ?? '')
	const [consigneeContactName, setConsigneeContactName] = useState(order.consignee_contact_name ?? '')

	const isValid =
		isFilled(shipperCompanyName) &&
		isFilled(shipperPhone) &&
		isFilled(shipperContactName) &&
		(consigneeSameAsShipper ||
			(isFilled(consigneeCompanyName) &&
				isFilled(consigneePhone) &&
				isFilled(consigneeContactName)))

	useEffect(() => {
		onValidityChange?.(isValid)
	}, [isValid, onValidityChange])

	return {
		shipperCompanyName,
		setShipperCompanyName,
		shipperPhone,
		setShipperPhone,
		shipperContactName,
		setShipperContactName,
		consigneeSameAsShipper,
		setConsigneeSameAsShipper,
		consigneeCompanyName,
		setConsigneeCompanyName,
		consigneePhone,
		setConsigneePhone,
		consigneeContactName,
		setConsigneeContactName,
		isValid,
	}
}

interface ShipperContactFieldsProps {
	expanded: boolean
	form: OrderContactFormState
}

export const ShipperContactFields: FC<ShipperContactFieldsProps> = ({ expanded, form }) => (
	<div className={cn(styles.expandWrap, { [styles.expandOpen]: expanded })}>
		<div className={styles.expandInner}>
			<div className={styles.fields}>
				<ContactField
					label="Shippers name"
					name="shipper_company_name"
					placeholder="My company name"
					value={form.shipperCompanyName}
					onChange={form.setShipperCompanyName}
					required
					highlightEmpty={expanded}
				/>
				<ContactField
					label="Phone number"
					name="shipper_phone"
					placeholder="+371 --- --- ---"
					type="tel"
					value={form.shipperPhone}
					onChange={form.setShipperPhone}
					required
					highlightEmpty={expanded}
				/>
				<ContactField
					label="Name"
					name="shipper_contact_name"
					placeholder="Your name"
					value={form.shipperContactName}
					onChange={form.setShipperContactName}
					required
					highlightEmpty={expanded}
				/>
			</div>
		</div>
	</div>
)

interface ConsigneeContactFieldsProps {
	expanded: boolean
	form: OrderContactFormState
}

export const ConsigneeContactFields: FC<ConsigneeContactFieldsProps> = ({ expanded, form }) => (
	<div className={cn(styles.expandWrap, { [styles.expandOpen]: expanded })}>
		<div className={styles.expandInner}>
			<Checkbox
				className={styles.sameCheckbox}
				value={form.consigneeSameAsShipper}
				onChange={form.setConsigneeSameAsShipper}
			>
				Same contact information for unloading
			</Checkbox>

			{form.consigneeSameAsShipper && (
				<input type="hidden" name="consignee_same_as_shipper" value="1" />
			)}

			<div
				className={cn(styles.expandWrap, {
					[styles.expandOpen]: expanded && !form.consigneeSameAsShipper,
				})}
			>
				<div className={styles.expandInner}>
					<div className={styles.fields}>
						<ContactField
							label="Consignee name"
							name="consignee_company_name"
							placeholder="My company name"
							value={form.consigneeCompanyName}
							onChange={form.setConsigneeCompanyName}
							required
							highlightEmpty={expanded && !form.consigneeSameAsShipper}
						/>
						<ContactField
							label="Phone number"
							name="consignee_phone"
							placeholder="+371 --- --- ---"
							type="tel"
							value={form.consigneePhone}
							onChange={form.setConsigneePhone}
							required
							highlightEmpty={expanded && !form.consigneeSameAsShipper}
						/>
						<ContactField
							label="Name"
							name="consignee_contact_name"
							placeholder="Your name"
							value={form.consigneeContactName}
							onChange={form.setConsigneeContactName}
							required
							highlightEmpty={expanded && !form.consigneeSameAsShipper}
						/>
					</div>
				</div>
			</div>
		</div>
	</div>
)

interface ContactFieldProps {
	label: string
	name: string
	placeholder: string
	value: string
	onChange: (value: string) => void
	type?: 'text' | 'tel'
	required?: boolean
	highlightEmpty?: boolean
}

const ContactField: FC<ContactFieldProps> = ({
	label,
	name,
	placeholder,
	value,
	onChange,
	type = 'text',
	required,
	highlightEmpty,
}) => {
	const showError = !!highlightEmpty && !isFilled(value)

	return (
		<label className={styles.field}>
			<span className={cn(styles.fieldLabel, { [styles.fieldLabelError]: showError })}>
				{label}
				{required && <span className={styles.requiredMark}> *</span>}
			</span>
			<input
				className={cn(styles.fieldInput, { [styles.fieldInputError]: showError })}
				name={name}
				type={type}
				placeholder={placeholder}
				value={value}
				required={required && highlightEmpty}
				onChange={(e) => onChange(e.target.value)}
			/>
		</label>
	)
}

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
			{(companyName || phone) && (
				<div className={styles.contactTopRow}>
					<div className={styles.contactRow}>
						{companyName && (
							<>
								<div className={styles.contactLabel}>Company name</div>
								<div className={styles.contactValue}>{companyName}</div>
							</>
						)}
					</div>
					<div className={styles.contactRow}>
						{phone && (
							<>
								<div className={styles.contactLabel}>Phone number</div>
								<div className={styles.contactValue}>{phone}</div>
							</>
						)}
					</div>
					{/* third column reserved — matches Loading window / Delivery window track */}
					<div />
				</div>
			)}
			{contactName && (
				<div className={styles.contactTopRow}>
					<div className={styles.contactRow}>
						<div className={styles.contactLabel}>Name</div>
						<div className={styles.contactValue}>{contactName}</div>
					</div>
				</div>
			)}
		</div>
	)
}
