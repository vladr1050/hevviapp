import { type FC } from 'react'
import { useForm } from 'react-hook-form'

import { FormActions } from '@config/constants'
import { Button } from '@ui/Button/Button'
import { Icon } from '@ui/Icon/Icon'
import { RadioButtons } from '@ui/RadioButtons/RadioButtons'
import { Textarea } from '@ui/Textarea/Textarea'

import styles from './CancelModal.module.css'

interface CancelModalProps {
	id: string
	from: string
	to: string
	accountType: 'sender' | 'carrier'
}

type FormValues = {
	radio: '1' | '2' | '3'
	text?: string
}

export const CancelModal: FC<CancelModalProps> = ({ id, from, to, accountType }) => {
	const { control, register, watch } = useForm<FormValues>({
		defaultValues: { radio: '1' },
	})

	return (
		<form className={styles.modal} method="POST" action={FormActions.CANCEL_ORDER}>
			<div className={styles.icon}>
				<Icon type="sad_box" size={60} />
			</div>

			<div className={styles.textWrapper}>
				<span>
					{from} → {to}
				</span>
				<span>Cancel order?</span>
				<span>ID {id}</span>
			</div>

			<div className={styles.subtitle}>If you decide to cancel, please mention the reason</div>

			<div className={styles.wrapper}>
				<RadioButtons
					control={control}
					name="radio"
					defaultValue="1"
					items={
						accountType === 'sender'
							? [
									{ label: 'Didn’t like the price', value: '1' },
									{ label: 'Didn’t like the delivery time', value: '2' },
									{ label: 'Other', value: '3' },
								]
							: [
									{ label: 'Reason 1', value: '1' },
									{ label: 'Reason 2', value: '2' },
									{ label: 'Other', value: '3' },
								]
					}
				/>
			</div>

			{watch('radio') === '3' && (
				<Textarea
					register={register('text')}
					rows={5}
					placeholder="Add comments"
					className="!w-full"
				/>
			)}

			<Button type="submit" value="CANCEL_ORDER" className="!w-full" variant="transparent">
				Cancel order
			</Button>
		</form>
	)
}
