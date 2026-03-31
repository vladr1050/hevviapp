import { type Dispatch, type FC, type SetStateAction } from 'react'
import { Control, UseFormRegister, UseFormSetValue, UseFormWatch } from 'react-hook-form'

import { Button } from '@ui/Button/Button'
import { Icon } from '@ui/Icon/Icon'
import { cn } from '@utils/cn'

import styles from './ModalContent.module.css'

import { WhatContent } from './WhatContent'
import { WhenContent } from './WhenContent'
import { WhereContent } from './WhereContent'
// @ts-ignore
import calculatingGif from './images/calculating.gif'
import { CalculateModalType, FormValues } from './types'
import { whatLabel, whenLabel, whereLabel } from './utils'

interface AddOrderModalProps {
	activeTab: CalculateModalType
	setActiveTab: Dispatch<SetStateAction<CalculateModalType>>
	watch: UseFormWatch<FormValues>
	control: Control<FormValues, any, FormValues>
	register: UseFormRegister<FormValues>
	setValue: UseFormSetValue<FormValues>
	onSubmit: () => Promise<void>
	submitError?: string
}

export const AddOrderModal: FC<AddOrderModalProps> = ({
	activeTab,
	setActiveTab,
	watch,
	control,
	register,
	setValue,
	onSubmit,
	submitError,
}) => {
	if (activeTab === 'calculate')
		return (
			<div className={styles.isCalculating}>
				<div className={styles.icon}>
					<img alt="" src={calculatingGif} style={{ width: '170px', height: '170px' }} />
				</div>

				<div className={styles.wrapper}>
					<div className={styles.title}>Calculating...</div>
					<div className={styles.subtitle}>
						{watch('cargo').length} item{watch('cargo').length > 1 && 's'} · {/*  */}
						{watch('from')} → {watch('to')}
					</div>
				</div>
			</div>
		)

	return (
		<form className={styles.modal} onSubmit={(e) => e.preventDefault()}>
			<div className={styles.header}>
				<div className={styles.title}>Request form</div>

				<div
					className={cn(styles.tabs, {
						[styles.whatActive]: activeTab === 'what',
						[styles.whereActive]: activeTab === 'where',
						[styles.whenActive]: activeTab === 'when',
					})}
				>
					<div
						className={cn(styles.tab, { [styles.active]: activeTab === 'what' })}
						onClick={() => watch('_step') >= 1 && setActiveTab('what')}
					>
						<div className={styles.icon}>
							<Icon type={activeTab === 'what' ? 'box' : 'check_circle_1'} size={20} />
						</div>

						<div className={styles.text}>
							<div className={styles.title}>What</div>
							<div
								className={cn(styles.subtitle, { [styles.empty]: !watch('cargo')?.length })}
								title={whatLabel(watch('cargo'))}
							>
								{whatLabel(watch('cargo')) || 'Add cargo'}
							</div>
						</div>
					</div>

					<div className={cn(styles.divider, { ['!bg-transparent']: activeTab !== 'when' })} />

					<div
						className={cn(styles.tab, {
							[styles.disable]: watch('_step') === 1,
							[styles.active]: activeTab === 'where',
						})}
						onClick={() => watch('_step') >= 2 && setActiveTab('where')}
					>
						{watch('_step') >= 2 && (
							<div className={styles.icon}>
								<Icon
									type={
										activeTab === 'where' || watch('_step') === 2 ? 'mark_map' : 'check_circle_1'
									}
									size={20}
								/>
							</div>
						)}

						<div className={styles.text}>
							<div className={styles.title}>Where</div>
							<div
								title={whereLabel(
									{ label: watch('from'), lat: watch('pickupLatitude') },
									{ label: watch('to'), lat: watch('dropoutLatitude') }
								)}
								className={cn(styles.subtitle, {
									[styles.empty]: !whereLabel(
										{ label: watch('from'), lat: watch('pickupLatitude') },
										{ label: watch('to'), lat: watch('dropoutLatitude') }
									),
								})}
							>
								{whereLabel(
									{ label: watch('from'), lat: watch('pickupLatitude') },
									{ label: watch('to'), lat: watch('dropoutLatitude') }
								) || 'Enter destination'}
							</div>
						</div>
					</div>

					<div className={cn(styles.divider, { ['!bg-transparent']: activeTab !== 'what' })} />

					<div
						className={cn(styles.tab, styles.withButton, {
							[styles.disable]: watch('_step') < 3,
							[styles.active]: activeTab === 'when',
						})}
						onClick={() => watch('_step') === 3 && setActiveTab('when')}
					>
						<div className={styles.contentButton}>
							{watch('_step') === 3 && (
								<div className={styles.icon}>
									{watch('_step') === 3 && <Icon type="mark_map" size={20} />}
								</div>
							)}

							<div className={styles.text}>
								<div className={styles.title}>When</div>
								<div
									className={cn(styles.subtitle, {
										[styles.empty]:
											activeTab !== 'when' ||
											(watch('pickupType') === 'pickup_later' && !watch('pickupDate')),
									})}
									title={whenLabel(watch('pickupType'), watch('pickupTime'), watch('pickupDate'))}
								>
									{whenLabel(watch('pickupType'), watch('pickupTime'), watch('pickupDate')) ||
										'Add date'}
									{/* {activeTab !== 'when' ? 'Add date' : PickupTypeEnum[watch('pickupType')]} */}
								</div>
							</div>
						</div>

						<NextButton
							onClick={() => {
								if (activeTab === 'when') return onSubmit?.()

								if (watch('_step') !== 3) {
									setValue('_step', activeTab === 'what' ? 2 : 3)
								}
								setActiveTab((v) => (v === 'what' ? 'where' : v === 'where' ? 'when' : 'calculate'))
							}}
							showNext={activeTab !== 'when'}
							disabled={
								(activeTab === 'what' && !watch('cargo').length) ||
								(activeTab === 'where' &&
									(!watch('pickupLatitude')?.toString().length ||
										!watch('dropoutLatitude')?.toString().length)) ||
								(activeTab === 'when' &&
									watch('pickupType') === 'pickup_later' &&
									!watch('pickupDate'))
							}
						/>
					</div>
				</div>

				{submitError && <div className={styles.submitError}>{submitError}</div>}
			</div>

			{activeTab === 'what' && <WhatContent control={control} register={register} />}

			{activeTab === 'where' && (
				<WhereContent
					control={control}
					watch={watch}
					setValue={setValue}
					register={register}
					defaultValues={{
						from: {
							lat: Number(watch('pickupLatitude')),
							lng: Number(watch('pickupLongitude')),
						},
						to: {
							lat: Number(watch('dropoutLatitude')),
							lng: Number(watch('dropoutLongitude')),
						},
					}}
				/>
			)}

			{activeTab === 'when' && <WhenContent control={control} watch={watch} />}
		</form>
	)
}

const NextButton: FC<{ showNext?: boolean; disabled?: boolean; onClick?: () => void }> = ({
	showNext,
	disabled,
	onClick,
}) => {
	return (
		<Button
			type="button"
			// type="submit"
			disabled={disabled}
			className={cn(styles.nextButton, { [styles.disabled]: !!disabled })}
			onClick={onClick}
		>
			{showNext ? 'Next →' : 'Calculate'}
		</Button>
	)
}
