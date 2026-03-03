import { type FC, useState } from 'react'
import { SubmitHandler, useForm } from 'react-hook-form'

import { apiLogin } from '@api/authApi'
import { saveTokens } from '@hooks/useAuth'
import { Button } from '@ui/Button/Button'
import { Icon } from '@ui/Icon/Icon'
import { Input } from '@ui/Input/Input'
import { cn } from '@utils/cn'

import styles from './Login.module.css'

interface LoginProps {}

type FormValues = {
	login: string
	password: string
}

export const LoginPage: FC<LoginProps> = () => {
	const [isReset, setIsReset] = useState(false)
	const [isLoading, setIsLoading] = useState(false)
	const [error, setError] = useState<string | null>(null)

	const { control, handleSubmit, watch } = useForm<FormValues>()

	const onSubmit: SubmitHandler<FormValues> = async (values, event) => {
		const submitter = (event?.nativeEvent as SubmitEvent)?.submitter as HTMLButtonElement | null

		if (submitter?.name === 'reset') {
			setIsReset(true)
			return
		}

		setError(null)
		setIsLoading(true)

		try {
			const result = await apiLogin(values.login, values.password)

			saveTokens(result.access_token, result.refresh_token, result.expires_in, result.user)

			window.location.href = '/user/dashboard'
		} catch (err: unknown) {
			const message = err instanceof Error ? err.message : 'Login failed'
			setError(message)
		} finally {
			setIsLoading(false)
		}
	}

	return (
		<div className={cn('tw-container', styles.page)}>
			<div className={styles.content}>
				<div className={styles.left}></div>

				<form className={styles.right} onSubmit={handleSubmit(onSubmit)}>
					<h1 className={styles.title}>{isReset ? 'Recover password' : 'Login'}</h1>

					{isReset ? (
						<div className="flex flex-col items-center gap-9 animate-fade">
							<div className="bg-primary rounded-full w-[120px] h-[120px] flex items-center justify-center text-black">
								<Icon type="block_mail" size={60} />
							</div>

							<div className="text-center font-medium text-base">
								Recovery email was sent to:
								<br />
								<span className="text-center font-medium text-base">{watch('login')}</span>
							</div>
						</div>
					) : (
						<div className={styles.inputs}>
							<Input
								control={control}
								name="login"
								placeholder="E-mail"
								label="E-mail"
								type="email"
								required
							/>

							<div className="">
								<Input
									control={control}
									name="password"
									placeholder="Password"
									label="Password"
									type="password"
								/>

								<button type="submit" name="reset" className={styles.resetPassword}>
									Reset password
								</button>
							</div>

							{error && (
								<p className="text-red-500 text-sm text-center">{error}</p>
							)}
						</div>
					)}

					<Button
						type={isReset ? 'button' : 'submit'}
						name="login"
						className="w-full"
						disabled={isLoading}
						onClick={!isReset ? undefined : () => setIsReset(false)}
					>
						{isLoading ? 'Loading...' : isReset ? 'Back to login' : 'Login'}
					</Button>
				</form>
			</div>
		</div>
	)
}
