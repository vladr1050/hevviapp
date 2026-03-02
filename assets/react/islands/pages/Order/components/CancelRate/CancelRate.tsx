import { type FC } from 'react'
import { Controller, useForm } from 'react-hook-form'

import { FormActions } from '@config/constants'
import { RadioGroup } from '@radix-ui/themes'
import { Icon } from '@ui/Icon/Icon'
import { Textarea } from '@ui/Textarea/Textarea'
import { cn } from '@utils/cn'

import styles from './CancelRate.module.css'

interface CancelRateProps {
	id: string | number | undefined
	from: string
	to: string
}

type FormValues = {
	radio: '1' | '2' | '3'
	text?: string
}

export const CancelRate: FC<CancelRateProps> = ({ id, from, to }) => {
	const { control, register, watch } = useForm<FormValues>({
		defaultValues: { radio: '1' },
	})

	return (
		<form className={styles.modal} method="POST" action={FormActions.CANCEL_ORDER}>
			<div className={styles.icon}>
				<Icon type="vehicle_check" size={60} />
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
				<Controller
					control={control}
					name="radio"
					render={({ field: { value, onChange } }) => (
						<RadioGroup.Root defaultValue="1" size="3" value={value} onValueChange={onChange}>
							<RadioGroup.Item value="1" className={styles.radio}>
								Didn’t like the price
							</RadioGroup.Item>
							<RadioGroup.Item value="2" className={styles.radio}>
								Didn’t like the delivery time
							</RadioGroup.Item>
							<RadioGroup.Item value="3" className={styles.radio}>
								Other
							</RadioGroup.Item>
						</RadioGroup.Root>
					)}
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

			<button className={styles.button} type="submit" value="CANCEL_ORDER">
				Cancel order
			</button>
		</form>
	)
}
