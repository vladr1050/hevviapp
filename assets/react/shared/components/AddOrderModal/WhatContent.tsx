import { type DragEvent, type FC, useRef, useState } from 'react'
import {
	Control,
	Controller,
	UseFieldArrayAppend,
	UseFieldArrayRemove,
	UseFieldArrayUpdate,
	UseFormRegister,
	UseFormRegisterReturn,
	UseFormWatch,
	useFieldArray,
} from 'react-hook-form'

import {
	EMPTY_STRING,
	MAX_CARGO_HEIGHT,
	MAX_LENGTH,
	MAX_QUANTITY,
	MAX_WIDTH,
	MIN_WEIGHT,
} from '@config/constants'
import { Button } from '@ui/Button/Button'
import { Icon } from '@ui/Icon/Icon'
import { Switch } from '@ui/Switch/Switch'
import { Textarea } from '@ui/Textarea/Textarea'
import { cn } from '@utils/cn'
import { downloadFile, downloadFileByUrl } from '@utils/file/downloadFile'
import { formatFileSize } from '@utils/file/formatFileSize'
import { getFileCategory } from '@utils/file/getFileCategory'

import styles from './ModalContent.module.css'

import { CargoItemType, FormValues } from './types'

type NumericField = number | ''

type EditableCargoItem = Omit<CargoItemType, 'length' | 'width' | 'height' | 'weight'> & {
	length: NumericField
	width: NumericField
	height: NumericField
	weight: NumericField
}

const numericFieldValue = (value: NumericField): string => (value === '' ? '' : String(value))

const parseNumericInput = (raw: string, max?: number): NumericField | null => {
	if (raw === '') {
		return ''
	}

	const value = Number(raw)
	if (!Number.isFinite(value)) {
		return null
	}

	if (max !== undefined && value > max) {
		return max
	}

	return value
}

const toCargoItem = (item: EditableCargoItem): CargoItemType | null => {
	const length = item.length === '' ? Number.NaN : Number(item.length)
	const width = item.width === '' ? Number.NaN : Number(item.width)
	const height = item.height === '' ? Number.NaN : Number(item.height)
	const weight = item.weight === '' ? Number.NaN : Number(item.weight)

	if (
		!Number.isFinite(length) ||
		!Number.isFinite(width) ||
		!Number.isFinite(height) ||
		!Number.isFinite(weight) ||
		length < 1 ||
		width < 1 ||
		height < 1 ||
		weight < MIN_WEIGHT ||
		length > MAX_LENGTH ||
		width > MAX_WIDTH
	) {
		return null
	}

	return {
		...item,
		length,
		width,
		height,
		weight,
	}
}

interface WhatContentProps {
	control: Control<FormValues, any, FormValues>
	register: UseFormRegister<FormValues>
}

export const WhatContent: FC<WhatContentProps> = ({ control, register }) => {
	const { fields, prepend, remove, update } = useFieldArray({ control, name: 'cargo' })

	return (
		<div className={cn(styles.body, styles.whatActive)}>
			<div className={styles.left}>
				<div className={styles.top}>
					<div className={styles.title}>Add cargo</div>

					<div className={styles.items}>
						<AddNewItem append={prepend} />

						{fields.map((item, idx) => (
							<Item key={item.id} idx={idx} item={item} remove={remove} update={update} />
						))}
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
					<Textarea
						register={register('comment')}
						placeholder="Add comments"
						rows={7}
						className={styles.textarea}
					/>
				</div>
			</div>
		</div>
	)
}

const AddNewItem: FC<{ append: UseFieldArrayAppend<FormValues, 'cargo'> }> = ({ append }) => {
	const [show, setShow] = useState(false)

	if (show) return <Item isNew append={append} onClose={() => setShow(false)} />

	return (
		<button type="button" className={styles.addNewItem} onClick={() => setShow(true)}>
			<Icon type="x_mark" size={18} className="rotate-45" />
			Add cargo
		</button>
	)
}

const Item: FC<{
	//
	idx?: number
	item?: CargoItemType
	remove?: UseFieldArrayRemove
	update?: UseFieldArrayUpdate<FormValues, 'cargo'>
	//
	isNew?: boolean
	append?: UseFieldArrayAppend<FormValues, 'cargo'>
	onClose?: () => void
}> = ({ idx, item: _item, remove, isNew, append, update, onClose }) => {
	const [item, setItem] = useState<EditableCargoItem>({
		name: '',
		length: 120,
		width: 80,
		height: 150,
		weight: 500,
		quantity: 1,
		..._item,
	})

	const [expand, setExpand] = useState(isNew ?? false)

	const [error, setError] = useState(false)
	const [heightError, setHeightError] = useState(false)

	const commitItem = () => {
		const normalized = toCargoItem(item)
		if (normalized === null || !normalized.name.length) {
			return setError(true)
		}
		if (normalized.height > MAX_CARGO_HEIGHT) {
			setError(false)
			return setHeightError(true)
		}
		setError(false)
		setHeightError(false)

		if (isNew) {
			append?.(normalized)
			onClose?.()
		} else {
			if (typeof idx !== 'undefined') {
				update?.(idx, normalized)
			}

			setExpand(false)
		}
	}

	return (
		<div className={styles.item}>
			<div className={styles.headerItem}>
				<div className={styles.left}>
					<div className={styles.title} title={item.name}>
						{isNew ? 'Add new cargo' : item.name || EMPTY_STRING}
					</div>

					<div className={styles.subtitle}>
						{isNew ? (
							'(in palletes)'
						) : (
							<>
								<span>
									{item.length} x {item.width} x {item.height} cm
								</span>
								<span>{item.weight} kg</span>
							</>
						)}
					</div>
				</div>

				<div className={styles.right}>
					<div className={styles.countButtons}>
						<button
							type="button"
							onClick={() => {
								setItem((v) => {
									const quantity = v.quantity === 1 ? 1 : v.quantity - 1
									return { ...v, quantity }
								})

								if (!isNew && typeof idx !== 'undefined' && !expand) {
									update?.(idx, { ...item, quantity: item.quantity === 1 ? 1 : item.quantity - 1 })
								}
							}}
						>
							–
						</button>

						<div className={styles.count}>{item.quantity}</div>

						<button
							type="button"
							onClick={() => {
								setItem((v) => {
									const quantity = v.quantity === MAX_QUANTITY ? MAX_QUANTITY : v.quantity + 1
									return { ...v, quantity }
								})

								if (!isNew && typeof idx !== 'undefined' && !expand) {
									update?.(idx, {
										...item,
										quantity: item.quantity === MAX_QUANTITY ? MAX_QUANTITY : item.quantity + 1,
									})
								}
							}}
						>
							+
						</button>
					</div>

					{!isNew && (
						<button
							type="button"
							className={styles.headerButton}
							onClick={() => {
								if (expand) {
									const normalized = toCargoItem(item)
									if (normalized === null || !normalized.name.length) {
										return setError(true)
									}
								}
								setError(false)

								setExpand((v) => !v)
							}}
						>
							<Icon
								type="expand"
								size={12}
								className={cn('!transition-transform', { ['rotate-180']: expand })}
							/>
						</button>
					)}

					<div className="w-[1px] h-7 bg-black/30"></div>

					<button
						type="button"
						className={cn(styles.headerButton, styles.red)}
						onClick={() => {
							if (isNew) return onClose?.()
							else {
								if (typeof idx !== 'undefined') remove?.(idx)
							}
						}}
					>
						<Icon type="plus" size={12} className="rotate-45" />
					</button>
				</div>
			</div>

			{expand && (
				<>
					<div className={styles.hr} />

					<div className={styles.contentItem}>
						<div className={styles.row}>
							<div className={styles.rowTitle}>Item</div>

							<input
								className={cn(styles.input, '!rounded-full', { [styles.error]: error })}
								value={item.name}
								placeholder="Add title"
								onChange={(e) => setItem((v) => ({ ...v, name: e.target.value }))}
								onKeyDown={(e) => {
									if (e.key !== 'Enter') {
										return
									}
									e.preventDefault()
									commitItem()
								}}
							/>
						</div>

						<div className={styles.row}>
							<div className={styles.rowTitle}>Dimensions L x W x H</div>
							<div className={styles.dimensions}>
								<div className={styles.inputWrapper}>
									<input
										className={cn(styles.input, '!rounded-l-full')}
										value={numericFieldValue(item.length)}
										onChange={(e) => {
											const length = parseNumericInput(e.target.value, MAX_LENGTH)
											if (length === null) {
												return
											}
											setItem((prev) => ({ ...prev, length }))
										}}
										type="text"
										inputMode="numeric"
										placeholder="0"
									/>
									<div className={styles.info}>
										<span>cm</span>
										<Icon type="arrow_left_right" size={16} className="-rotate-45" />
									</div>
								</div>

								<div className={styles.divider}></div>

								<div className={cn(styles.inputWrapper)}>
									<input
										className={styles.input}
										value={numericFieldValue(item.width)}
										onChange={(e) => {
											const width = parseNumericInput(e.target.value, MAX_WIDTH)
											if (width === null) {
												return
											}
											setItem((prev) => ({ ...prev, width }))
										}}
										type="text"
										inputMode="numeric"
										placeholder="0"
									/>
									<div className={styles.info}>
										<span>cm</span>
										<Icon type="arrow_left_right" size={16} />
									</div>
								</div>

								<div className={styles.divider}></div>

								<div className={cn(styles.inputWrapper, { [styles.error]: heightError })}>
									<input
										className={cn(styles.input, '!rounded-r-full', { [styles.error]: heightError })}
										value={numericFieldValue(item.height)}
										onChange={(e) => {
											const height = parseNumericInput(e.target.value)
											if (height === null) {
												return
											}
											setItem((prev) => ({ ...prev, height }))
											setHeightError(typeof height === 'number' && height > MAX_CARGO_HEIGHT)
										}}
										type="text"
										inputMode="numeric"
										placeholder="0"
									/>
									<div className={styles.info}>
										<span>cm</span>
										<Icon type="arrow_left_right" size={16} className="rotate-90" />
									</div>
								</div>
							</div>
							{heightError && (
								<div className={styles.fieldError}>
									Maximum height is {MAX_CARGO_HEIGHT} cm
								</div>
							)}
						</div>

						<div className="grid grid-cols-[180px,1fr] gap-9 items-end">
							<div className={styles.row}>
								<div className={styles.rowTitle}>Weight</div>

								<div className={styles.inputKg}>
									<input
										className={cn(styles.input, '!rounded-l-full')}
										value={numericFieldValue(item.weight)}
										onChange={(e) => {
											const weight = parseNumericInput(e.target.value)
											if (weight === null) {
												return
											}
											setItem((prev) => ({ ...prev, weight }))
										}}
										type="text"
										inputMode="numeric"
										placeholder="0"
									/>

									<span className={styles.info}>kg</span>
								</div>
							</div>

							<Button type="button" onClick={commitItem} className={styles.addButton}>
								{isNew ? '+ Add' : 'Save'}
							</Button>
						</div>
					</div>
				</>
			)}
		</div>
	)
}

// ─── Documents Upload ─────────────────────────────────────────────────────────

const ALLOWED_MIME = 'application/pdf'
const MAX_FILE_SIZE_MB = 20

const DocumentsSection: FC<{
	control: Control<FormValues, any, FormValues>
}> = ({ control }) => {
	const inputRef = useRef<HTMLInputElement>(null)
	const [isDragging, setIsDragging] = useState(false)

	return (
		<div className={styles.files}>
			<Controller
				control={control}
				name="attachments"
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

					const handleDrop = (e: DragEvent<HTMLButtonElement>) => {
						e.preventDefault()
						setIsDragging(false)
						addFiles(e.dataTransfer.files)
					}

					return (
						<>
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
						</>
					)
				}}
			/>

			{/* OLD ATTACHMENTS */}
			<Controller
				control={control}
				name="keepAttachments"
				render={({ field: { value = [], onChange } }) => {
					const removeFile = (index: number) => {
						onChange((value ?? []).filter((_, i) => i !== index))
					}

					return (
						<>
							{/* File list */}
							{value?.map(({ filename, path }, index) => (
								<div key={`${filename}-${path}`} className={styles.fileItem}>
									<div className={styles.fileInfo}>
										<span className={styles.fileName} title={filename}>
											{filename}
										</span>

										<div className={styles.fileDescription}>
											<div className={styles.fileType}>
												<Icon type={getFileCategory(filename)} size={16} />
												<span>
													{getFileCategory(filename) === 'document'
														? 'Word Document'
														: getFileCategory(filename) === 'excel'
															? 'Excel Document'
															: getFileCategory(filename) === 'pdf'
																? 'PDF Document'
																: 'File'}
												</span>
											</div>

											{/* <span className={styles.fileSize}>{formatFileSize(file.size)}</span> */}
										</div>
									</div>

									<div className={styles.fileButtons}>
										<button
											type="button"
											className={styles.fileDownload}
											onClick={() => downloadFileByUrl(path, filename)}
											title="Download"
										>
											<Icon type="download_file" size={16} />
										</button>

										<button
											type="button"
											className={styles.fileRemove}
											onClick={() => removeFile(index)}
											title="Remove"
										>
											<Icon type="x_mark" size={12} />
										</button>
									</div>
								</div>
							))}
						</>
					)
				}}
			/>

			{/* ATTACHMENTS */}
			<Controller
				control={control}
				name="attachments"
				render={({ field: { value = [], onChange } }) => {
					const removeFile = (index: number) => {
						onChange((value ?? []).filter((_, i) => i !== index))
					}

					return (
						<>
							{/* File list */}
							{(value ?? []).map((file, index) => (
								<div key={`${file.name}-${file.lastModified}`} className={styles.fileItem}>
									<div className={styles.fileInfo}>
										<span className={styles.fileName} title={file.name}>
											{file.name}
										</span>

										<div className={styles.fileDescription}>
											<div className={styles.fileType}>
												<Icon type={getFileCategory(file)} size={16} />
												<span>
													{getFileCategory(file) === 'document'
														? 'Word Document'
														: getFileCategory(file) === 'excel'
															? 'Excel Document'
															: getFileCategory(file) === 'pdf'
																? 'PDF Document'
																: 'File'}
												</span>
											</div>

											<span className={styles.fileSize}>{formatFileSize(file.size)}</span>
										</div>
									</div>

									<div className={styles.fileButtons}>
										<button
											type="button"
											className={styles.fileDownload}
											onClick={() => downloadFile(file)}
											title="Download"
										>
											<Icon type="download_file" size={16} />
										</button>

										<button
											type="button"
											className={styles.fileRemove}
											onClick={() => removeFile(index)}
											title="Remove"
										>
											<Icon type="x_mark" size={12} />
										</button>
									</div>
								</div>
							))}
						</>
					)
				}}
			/>
		</div>
	)
}
