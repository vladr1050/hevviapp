import { type FC, type RefObject, useEffect, useRef, useState } from 'react'

import { OrderType } from '@config/constants'
import { Checkbox } from '@ui/Checkbox/Checkbox'
import { cn } from '@utils/cn'

import styles from './ContactInfoPanel.module.css'

const isFilled = (value: string): boolean => value.trim().length > 0

const SHIPPER_NAME_INPUT_ID = 'order-contact-shipper-name'
const SHIPPER_SECTION_ID = 'order-contact-shipper-fields'
const CONSIGNEE_NAME_INPUT_ID = 'order-contact-consignee-name'
export const CONSIGNEE_SECTION_ID = 'order-contact-consignee'

const focusInputById = (id: string): void => {
	const el = document.getElementById(id) as HTMLInputElement | null
	el?.focus()
	el?.select()
}

const isFocusInside = (sectionId: string): boolean => {
	const section = document.getElementById(sectionId)
	const active = document.activeElement
	return !!(section && active instanceof Node && section.contains(active))
}

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
	/** Red empty-state only after a failed Confirm attempt — not on first expand. */
	showValidation: boolean
	form: OrderContactFormState
}

export const ShipperContactFields: FC<ShipperContactFieldsProps> = ({
	expanded,
	showValidation,
	form,
}) => {
	const shipperComplete =
		isFilled(form.shipperCompanyName) &&
		isFilled(form.shipperPhone) &&
		isFilled(form.shipperContactName)
	const jumpedToConsigneeRef = useRef(false)
	const wasExpandedRef = useRef(false)

	const jumpToConsigneeIfReady = () => {
		if (!expanded || !shipperComplete || form.consigneeSameAsShipper) return
		if (jumpedToConsigneeRef.current) return
		if (isFocusInside(SHIPPER_SECTION_ID)) return

		jumpedToConsigneeRef.current = true
		document.getElementById(CONSIGNEE_SECTION_ID)?.scrollIntoView({
			behavior: 'smooth',
			block: 'nearest',
		})
		window.setTimeout(() => focusInputById(CONSIGNEE_NAME_INPUT_ID), 200)
	}

	// After Continue: focus Shipper name so typing can start immediately.
	useEffect(() => {
		const justOpened = expanded && !wasExpandedRef.current
		wasExpandedRef.current = expanded

		if (!expanded) {
			jumpedToConsigneeRef.current = shipperComplete
			return
		}
		if (!justOpened) return

		jumpedToConsigneeRef.current = false
		const timer = window.setTimeout(() => {
			focusInputById(SHIPPER_NAME_INPUT_ID)
		}, 120)
		return () => window.clearTimeout(timer)
	}, [expanded, shipperComplete])

	useEffect(() => {
		if (!shipperComplete) {
			jumpedToConsigneeRef.current = false
		}
	}, [shipperComplete])

	return (
		<div
			id={SHIPPER_SECTION_ID}
			className={cn(styles.expandWrap, { [styles.expandOpen]: expanded })}
			onBlur={(e) => {
				const next = e.relatedTarget as Node | null
				if (next && e.currentTarget.contains(next)) return
				// Left Shipper fields — jump only if everything is filled.
				window.setTimeout(jumpToConsigneeIfReady, 0)
			}}
		>
			<div className={styles.expandInner}>
				<div className={styles.fields}>
					<ContactField
						id={SHIPPER_NAME_INPUT_ID}
						label="Shippers name"
						name="shipper_company_name"
						placeholder="Shippers name"
						value={form.shipperCompanyName}
						onChange={form.setShipperCompanyName}
						required
						showError={showValidation && !isFilled(form.shipperCompanyName)}
					/>
					<ContactField
						label="Phone number"
						name="shipper_phone"
						placeholder="+371 --- --- ---"
						type="tel"
						value={form.shipperPhone}
						onChange={form.setShipperPhone}
						required
						showError={showValidation && !isFilled(form.shipperPhone)}
					/>
					<ContactField
						label="Name"
						name="shipper_contact_name"
						placeholder="Your name"
						value={form.shipperContactName}
						onChange={form.setShipperContactName}
						showError={showValidation && !isFilled(form.shipperContactName)}
					/>
				</div>
			</div>
		</div>
	)
}

interface ConsigneeContactFieldsProps {
	expanded: boolean
	showValidation: boolean
	form: OrderContactFormState
}

export const ConsigneeContactFields: FC<ConsigneeContactFieldsProps> = ({
	expanded,
	showValidation,
	form,
}) => (
	<div id={CONSIGNEE_SECTION_ID} className={cn(styles.expandWrap, { [styles.expandOpen]: expanded })}>
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
							id={CONSIGNEE_NAME_INPUT_ID}
							label="Consignee name"
							name="consignee_company_name"
							placeholder="Consignee name"
							value={form.consigneeCompanyName}
							onChange={form.setConsigneeCompanyName}
							required
							showError={
								showValidation &&
								!form.consigneeSameAsShipper &&
								!isFilled(form.consigneeCompanyName)
							}
						/>
						<ContactField
							label="Phone number"
							name="consignee_phone"
							placeholder="+371 --- --- ---"
							type="tel"
							value={form.consigneePhone}
							onChange={form.setConsigneePhone}
							required
							showError={
								showValidation &&
								!form.consigneeSameAsShipper &&
								!isFilled(form.consigneePhone)
							}
						/>
						<ContactField
							label="Name"
							name="consignee_contact_name"
							placeholder="Your name"
							value={form.consigneeContactName}
							onChange={form.setConsigneeContactName}
							showError={
								showValidation &&
								!form.consigneeSameAsShipper &&
								!isFilled(form.consigneeContactName)
							}
						/>
					</div>
				</div>
			</div>
		</div>
	</div>
)

interface ContactFieldProps {
	id?: string
	label: string
	name: string
	placeholder: string
	value: string
	onChange: (value: string) => void
	type?: 'text' | 'tel'
	required?: boolean
	showError?: boolean
	inputRef?: RefObject<HTMLInputElement | null>
}

const ContactField: FC<ContactFieldProps> = ({
	id,
	label,
	name,
	placeholder,
	value,
	onChange,
	type = 'text',
	required,
	showError,
	inputRef,
}) => (
	<label className={styles.field}>
		<span className={cn(styles.fieldLabel, { [styles.fieldLabelError]: showError })}>
			{label}
			{required && <span className={styles.requiredMark}> *</span>}
		</span>
		<input
			id={id}
			ref={inputRef}
			className={cn(styles.fieldInput, { [styles.fieldInputError]: showError })}
			name={name}
			type={type}
			placeholder={placeholder}
			value={value}
			onChange={(e) => onChange(e.target.value)}
		/>
		{showError && (
			<span className={styles.fieldError} role="alert">
				<span className={styles.fieldErrorIcon} aria-hidden>
					!
				</span>
				Please enter {label.toLowerCase()}
			</span>
		)}
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
