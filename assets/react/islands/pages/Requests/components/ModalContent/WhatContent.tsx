import { type DragEvent, type FC, useRef, useState } from 'react'
import {
	Control,
	Controller,
	UseFieldArrayAppend,
	UseFieldArrayRemove,
	UseFormRegister,
	useFieldArray,
} from 'react-hook-form'

import {
	MAX_HEIGHT,
	MAX_LENGTH,
	MAX_QUANTITY,
	MAX_WEIGHT,
	MAX_WIDTH,
	MIN_WEIGHT,
} from '@config/constants'
import { Button } from '@ui/Button/Button'
import { Icon } from '@ui/Icon/Icon'
import { Switch } from '@ui/Switch/Switch'
import { Tabs } from '@ui/Tabs/Tabs'
import { Textarea } from '@ui/Textarea/Textarea'
import { cn } from '@utils/cn'

import styles from './ModalContent.module.css'

import { CargoItemType, FormValues } from '../RequestsUser/types'

interface WhatContentProps {
	control: Control<FormValues, any, FormValues>
	register: UseFormRegister<FormValues>
}

export const WhatContent: FC<WhatContentProps> = ({ control, register }) => {
	const { fields, prepend, remove } = useFieldArray({ control, name: 'cargo' })

	return (
		<div className={cn(styles.body, styles.whatActive)}>
			<div className={styles.left}>
				<div className={styles.top}>
					<div className={styles.title}>Add cargo</div>

					<div className={styles.items}>
						{!fields?.length ? (
							<AddItem append={prepend} />
						) : (
							<>
								<AddNewItem append={prepend} />

								{fields.map((item, idx) => (
									<Item key={item.id} idx={idx} item={item} control={control} remove={remove} />
								))}
							</>
						)}
					</div>
				</div>

				<div className={styles.switches}>
					<Controller
						control={control}
						name="stackable"
						render={({ field: { value, onChange } }) => (
							<Switch checked={value} onChange={onChange} label="Stackability possible" />
						)}
					/>
					<Controller
						control={control}
						name="manipulatorNeeded"
						render={({ field: { value, onChange } }) => (
							<Switch checked={value} onChange={onChange} label="Truck with Lift" />
						)}
					/>
				</div>
			</div>

			<div className={styles.right}>
				<div className={styles.rightBlock}>
					<div className={styles.title}>Add documents</div>

					<DocumentsSection control={control} />
				</div>

				<div className={styles.rightBlock}>
					<div className={styles.title}>Comments</div>
					<Textarea register={register('comments')} placeholder="Add comments" rows={7} />
				</div>
			</div>
		</div>
	)
}

const AddNewItem: FC<{ append: UseFieldArrayAppend<FormValues, 'cargo'> }> = ({ append }) => {
	const [show, setShow] = useState(false)

	if (show)
		return <AddItem onClose={() => setShow(false)} className={styles.fade} append={append} />

	return (
		<button type="button" className={styles.addNewItem} onClick={() => setShow(true)}>
			<Icon type="x_mark" size={18} className="rotate-45" />
			Add one more cargo
		</button>
	)
}

const Item: FC<{
	idx: number
	item: CargoItemType
	remove: UseFieldArrayRemove
	control: Control<FormValues, any, FormValues>
}> = ({ idx, item, remove, control }) => {
	return (
		<div className={cn(styles.item, styles.short)} onClick={() => console.log(item)}>
			<div className={styles.left}>
				<div className={styles.title}>
					{item.type === 'palette' ? 'Palletes' : 'Irregular cargo'}
				</div>
				<div className={styles.subtitle}>
					<span>
						{item.width} x {item.length} x {item.height}
					</span>
					<span>{item.weight} kg</span>
				</div>
			</div>
			<div className={styles.right}>
				<Controller
					control={control}
					name={`cargo.${idx}.quantity`}
					render={({ field: { value, onChange } }) => (
						<div className={styles.countButtons}>
							<button
								type="button"
								onClick={() =>
									onChange(() => {
										if (value === 1) return 1
										return value - 1
									})
								}
							>
								–
							</button>

							<div className={styles.count}>{value}</div>

							<button
								type="button"
								onClick={() =>
									onChange(() => {
										if (value === MAX_QUANTITY) return MAX_QUANTITY
										return value + 1
									})
								}
							>
								+
							</button>
						</div>
					)}
				/>

				<button type="button" className={styles.close} onClick={() => remove(idx)}>
					<Icon type="x_mark" size={12} />
				</button>
			</div>
		</div>
	)
}

// ─── Documents Upload ─────────────────────────────────────────────────────────

const ALLOWED_MIME = 'application/pdf'
const MAX_FILE_SIZE_MB = 20

const DocumentsSection: FC<{ control: Control<FormValues, any, FormValues> }> = ({ control }) => {
	const inputRef = useRef<HTMLInputElement>(null)
	const [isDragging, setIsDragging] = useState(false)

	return (
		<Controller
			control={control}
			name="documents"
			render={({ field: { value = [], onChange } }) => {
				const addFiles = (incoming: FileList | File[]) => {
					const existing = value ?? []
					const existingNames = new Set(existing.map((f) => f.name))

					const valid = Array.from(incoming).filter((f) => {
						if (f.type !== ALLOWED_MIME) return false
						if (f.size > MAX_FILE_SIZE_MB * 1024 * 1024) return false
						if (existingNames.has(f.name)) return false
						return true
					})

					if (valid.length) onChange([...existing, ...valid])
				}

				const removeFile = (index: number) => {
					onChange((value ?? []).filter((_, i) => i !== index))
				}

				const handleDrop = (e: DragEvent<HTMLButtonElement>) => {
					e.preventDefault()
					setIsDragging(false)
					addFiles(e.dataTransfer.files)
				}

				return (
					<div className={styles.files}>
						{/* Hidden native input */}
						<input
							ref={inputRef}
							type="file"
							accept="application/pdf"
							multiple
							style={{ display: 'none' }}
							onChange={(e) => {
								if (e.target.files) addFiles(e.target.files)
								e.target.value = ''
							}}
						/>

						{/* Drop zone */}
						<button
							type="button"
							className={cn(styles.empty, { [styles.dragging]: isDragging })}
							onClick={() => inputRef.current?.click()}
							onDragOver={(e) => {
								e.preventDefault()
								setIsDragging(true)
							}}
							onDragLeave={() => setIsDragging(false)}
							onDrop={handleDrop}
						>
							<Icon type="x_mark" size={18} className="rotate-45" />
							{isDragging ? 'Drop PDF files here' : 'Add or drag & drop files'}
						</button>

						{/* File list */}
						{(value ?? []).map((file, index) => (
							<div key={`${file.name}-${index}`} className={styles.fileItem}>
								<Icon type="download_file" size={16} className={styles.fileIcon} />
								<span className={styles.fileName} title={file.name}>
									{file.name}
								</span>
								<span className={styles.fileSize}>
									{(file.size / 1024).toFixed(0)} KB
								</span>
								<button
									type="button"
									className={styles.fileRemove}
									onClick={() => removeFile(index)}
									title="Remove"
								>
									<Icon type="x_mark" size={12} />
								</button>
							</div>
						))}
					</div>
				)
			}}
		/>
	)
}

// ─── AddItem ──────────────────────────────────────────────────────────────────

const AddItem: FC<{
	append: UseFieldArrayAppend<FormValues, 'cargo'>
	onClose?: () => void
	className?: string
}> = ({ onClose, className, append }) => {
	const [item, setItem] = useState<CargoItemType>({
		type: 'palette',
		width: 120,
		length: 80,
		height: 150,
		weight: 500,
		quantity: 1,
	})

	return (
		<div className={cn(styles.item, styles.full, className)}>
			{!!onClose && (
				<button type="button" className={styles.close} onClick={onClose}>
					<Icon type="x_mark" size={12} />
				</button>
			)}

			<div className={styles.row}>
				<div className={styles.rowTitle}>Item</div>

				<Tabs
					defaultValue={'palette'}
					classNames={{ tab: '!w-full', root: cn({ ['mr-14']: !!onClose }) }}
					onChange={(v) => setItem({ ...item, type: v as any })}
					items={[
						{ label: 'Palette', value: 'palette' },
						{ label: 'Irregular cargo', value: 'irregular_cargo' },
					]}
				/>
			</div>

			<div className={styles.row}>
				<div className={styles.rowTitle}>Dimensions W x L x H</div>
				<div className={cn(styles.rowContent, styles.dimensions)}>
					<div className={styles.inputWrapper}>
						<input
							className={cn(styles.input, '!rounded-l-full')}
							value={item.width}
							onChange={(e) => {
								const v = Number(e.target.value)
								const width = v > MAX_WIDTH ? MAX_WIDTH : v < 1 ? 1 : v
								setItem((v) => ({ ...v, width }))
							}}
							type="number"
							placeholder="0"
							min={1}
							max={MAX_WIDTH}
						/>

						<div className={styles.info}>
							<span>cm</span>
							<Icon type="arrow_left_right" size={16} />
						</div>
					</div>

					<div className={styles.divider}></div>

					<div className={cn(styles.inputWrapper)}>
						<input
							className={styles.input}
							value={item.length}
							onChange={(e) => {
								const v = Number(e.target.value)
								const length = v > MAX_LENGTH ? MAX_LENGTH : v < 1 ? 1 : v
								setItem((v) => ({ ...v, length }))
							}}
							type="number"
							placeholder="0"
							min={1}
							max={MAX_LENGTH}
						/>
						<div className={styles.info}>
							<span>cm</span>
							<Icon type="arrow_left_right" size={16} className="-rotate-45" />
						</div>
					</div>

					<div className={styles.divider}></div>

					<div className={cn(styles.inputWrapper)}>
						<input
							className={cn(styles.input, '!rounded-r-full')}
							value={item.height}
							onChange={(e) => {
								const v = Number(e.target.value)
								const height = v > MAX_HEIGHT ? MAX_HEIGHT : v < 1 ? 1 : v
								setItem((v) => ({ ...v, height }))
							}}
							type="number"
							placeholder="0"
							min={1}
							max={MAX_HEIGHT}
						/>
						<div className={styles.info}>
							<span>cm</span>
							<Icon type="arrow_left_right" size={16} className="rotate-90" />
						</div>
					</div>
				</div>
			</div>

			<div className={styles.row}>
				<div className={styles.rowTitle}>Weight ({MAX_WEIGHT} max)</div>
				<div className={styles.rowContent}>
					<div className={styles.inputKg}>
						<input
							className={cn(styles.input, '!rounded-l-full')}
							value={item.weight}
							onChange={(e) => {
								const v = Number(e.target.value)
								const weight = v > MAX_WEIGHT ? MAX_WEIGHT : v < 1 ? 1 : v
								setItem((v) => ({ ...v, weight }))
							}}
							type="number"
							placeholder="0"
							min={MIN_WEIGHT}
							max={MAX_WEIGHT}
						/>

						<span className={styles.info}>kg</span>
					</div>

					<div className={styles.countButtons}>
						<button
							type="button"
							onClick={() =>
								setItem((v) => {
									const quantity = v.quantity === 1 ? 1 : v.quantity - 1
									return { ...v, quantity }
								})
							}
						>
							–
						</button>

						<div className={styles.count}>{item.quantity}</div>

						<button
							type="button"
							onClick={() =>
								setItem((v) => {
									const quantity = v.quantity === MAX_QUANTITY ? MAX_QUANTITY : v.quantity + 1
									return { ...v, quantity }
								})
							}
						>
							+
						</button>
					</div>

					<Button
						type="button"
						onClick={() => {
							console.log(3, item)
							append(item)
							onClose?.()
						}}
						className={styles.addButton}
					>
						+ Add
					</Button>
				</div>
			</div>
		</div>
	)
}
