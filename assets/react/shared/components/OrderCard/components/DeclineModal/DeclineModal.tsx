import type { FC } from 'react'
import { useForm } from 'react-hook-form'

import { Button } from '@ui/Button/Button'
import { Icon } from '@ui/Icon/Icon'
import { RadioButtons } from '@ui/RadioButtons/RadioButtons'
import { Textarea } from '@ui/Textarea/Textarea'

import styles from './DeclineModal.module.css'

interface DeclineModalProps {
	id: string
	from?: string
	to?: string
	actionUrl: string
}

type FormValues = {
	radio: '1' | '2' | '3'
	text?: string
}

export const DeclineModal: FC<DeclineModalProps> = ({ id, from, to, actionUrl }) => {
	const { control, register, watch } = useForm<FormValues>({
		defaultValues: { radio: '1' },
	})

	return (
		<form className={styles.modal} method="POST" action={actionUrl}>
			<div className={styles.icon}>
				<Icon type="sad_box" size={60} />
			</div>

			<div className={styles.textWrapper}>
				<span>
					{!!from && !!to && (
						<>
							{from} → {to}
						</>
					)}
				</span>
				<span>Cancel order?</span>
				<span>ID {id}</span>
			</div>

			<div className={styles.subtitle}>If you decline, please mention the reason</div>

			<div className={styles.wrapper}>
				<RadioButtons
					control={control}
					name="radio"
					defaultValue="1"
					items={[
						{ label: 'No trucks available', value: '1' },
						{ label: 'Wrong price calculation', value: '2' },
						{ label: 'Other', value: '3' },
					]}
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
