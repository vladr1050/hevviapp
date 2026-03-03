import type { FC } from 'react'
import { SubmitHandler, useForm } from 'react-hook-form'

import { FormActions } from '@config/constants'
import { Button } from '@ui/Button/Button'
import { Icon } from '@ui/Icon/Icon'
import { Textarea } from '@ui/Textarea/Textarea'
import { cn } from '@utils/cn'

import styles from './RateModal.module.css'

interface RateModalProps {
	id?: string
}

type FormValues = {
	text: string
	rate: number
	id: string
}

export const RateModal: FC<RateModalProps> = ({ id }) => {
	const { register, handleSubmit, watch, setValue } = useForm<FormValues>({
		defaultValues: { id, rate: 0 },
	})

	const onSubmit: SubmitHandler<FormValues> = async (values) => {
		console.log(values)
	}

	const rate = watch('rate')

	return (
		<form className={styles.modal} method="POST" action={FormActions.RATE_ORDER}>
			<div className={styles.icon}>
				<Icon type="question_circle" size={60} />
			</div>
			<div className={styles.title}>How did it go?</div>

			<div className={styles.stars}>
				<div
					className={cn(styles.star, { [styles.active]: rate > 0 })}
					onClick={() => setValue('rate', 1)}
				>
					<Icon type="star" size={28} />
				</div>
				<div
					className={cn(styles.star, { [styles.active]: rate > 1 })}
					onClick={() => setValue('rate', 2)}
				>
					<Icon type="star" size={28} />
				</div>
				<div
					className={cn(styles.star, { [styles.active]: rate > 2 })}
					onClick={() => setValue('rate', 3)}
				>
					<Icon type="star" size={28} />
				</div>
				<div
					className={cn(styles.star, { [styles.active]: rate > 3 })}
					onClick={() => setValue('rate', 4)}
				>
					<Icon type="star" size={28} />
				</div>
				<div
					className={cn(styles.star, { [styles.active]: rate > 4 })}
					onClick={() => setValue('rate', 5)}
				>
					<Icon type="star" size={28} />
				</div>
			</div>

			<Textarea
				register={register('text')}
				rows={5}
				placeholder="Add comments"
				className="!w-full"
			/>

			<Button type="submit" className="!w-[210px]">
				Rate
			</Button>
		</form>
	)
}
