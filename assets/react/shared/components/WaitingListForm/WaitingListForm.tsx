import { type FC, useState } from 'react'
import { SubmitHandler, useForm } from 'react-hook-form'

import { apiJoinWaitingList } from '@api/waitingListApi'
import { Button } from '@ui/Button/Button'
import { Icon } from '@ui/Icon/Icon'
import { Input } from '@ui/Input/Input'
import { cn } from '@utils/cn'

import styles from './WaitingListForm.module.css'

type FormValues = {
	email: string
	phone: string
	company_website: string
}

export type WaitingListFormProps = {
	variant?: 'landing' | 'page'
	submitLabel?: string
	onSuccess?: (email: string) => void
	className?: string
}

export const WaitingListForm: FC<WaitingListFormProps> = ({
	variant = 'page',
	submitLabel,
	onSuccess,
	className,
}) => {
	const [isLoading, setIsLoading] = useState(false)
	const [error, setError] = useState<string | null>(null)
	const [isSuccess, setIsSuccess] = useState(false)
	const [submittedEmail, setSubmittedEmail] = useState('')

	const { handleSubmit, control, register, setValue } = useForm<FormValues>({
		defaultValues: {
			email: '',
			phone: '',
			company_website: '',
		},
	})

	const onSubmit: SubmitHandler<FormValues> = async (values) => {
		setError(null)
		setIsLoading(true)

		try {
			await apiJoinWaitingList(values.email, values.phone, values.company_website, 'sender')
			const email = values.email.trim()
			setSubmittedEmail(email)
			setIsSuccess(true)
			onSuccess?.(email)
		} catch (err: unknown) {
			const message = err instanceof Error ? err.message : 'Registration failed'
			setError(message)
		} finally {
			setIsLoading(false)
		}
	}

	if (isSuccess && variant === 'page') {
		return (
			<div className={cn(styles.success, className)}>
				<h2 className={styles.successTitle}>Awesome! We&apos;ve registered</h2>
				<div className={styles.successIcon}>
					<Icon type="profile" size={60} />
				</div>
				<p className={styles.successEmail}>{submittedEmail}</p>
				<p className={styles.successHint}>We&apos;ll get in touch with you!</p>
				<Button type="button" className="w-full" onClick={() => (window.location.href = '/login')}>
					Back to login
				</Button>
			</div>
		)
	}

	if (isSuccess && variant === 'landing') {
		return (
			<div className={cn(styles.success, styles.landingSuccess, className)}>
				<h2 className={styles.successTitle}>Thank you!</h2>
				<p className={styles.successHint}>We&apos;ll get in touch with you at {submittedEmail}.</p>
				<Button
					type="button"
					variant="outline"
					onClick={() => {
						setIsSuccess(false)
						setSubmittedEmail('')
						setValue('email', '')
						setValue('phone', '')
					}}
				>
					Submit another
				</Button>
			</div>
		)
	}

	const buttonLabel =
		submitLabel ?? (variant === 'landing' ? 'Get early access' : 'Join waiting list')

	return (
		<form
			className={cn(styles.form, styles[variant], className)}
			onSubmit={handleSubmit(onSubmit)}
		>
			{variant === 'landing' && (
				<h3 className={styles.landingTitle}>For cargo senders</h3>
			)}

			<div className={styles.inputs}>
				<Input
					control={control}
					name="email"
					placeholder={variant === 'landing' ? 'you@company.com' : 'janis.doe@gmail.com'}
					label="E-mail"
					type="email"
					required
					className={variant === 'landing' ? styles.landingInput : undefined}
				/>
				<Input
					control={control}
					name="phone"
					placeholder={variant === 'landing' ? '+371 XX XXX XXX' : '+371 20000000'}
					label="Phone"
					type="tel"
					required
					className={variant === 'landing' ? styles.landingInput : undefined}
				/>

				<input
					type="text"
					tabIndex={-1}
					autoComplete="off"
					aria-hidden="true"
					className={styles.honeypot}
					{...register('company_website')}
				/>
			</div>

			{error && <p className={styles.error}>{error}</p>}

			<Button type="submit" className={styles.submit} disabled={isLoading}>
				{isLoading ? 'Submitting…' : buttonLabel}
			</Button>
		</form>
	)
}
