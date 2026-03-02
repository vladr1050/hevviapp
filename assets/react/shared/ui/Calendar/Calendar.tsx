import { ComponentProps, FC, useEffect, useRef } from 'react'
import { type DayButton, DayPicker, getDefaultClassNames } from 'react-day-picker'

import { cn } from '@utils/cn'

import { CalendarButton } from './components/Button'

type CalendarProps = ComponentProps<typeof DayPicker> & {
	// buttonVariant?: ComponentProps<typeof Button>["variant"]
	month: Date
	setMonth: (month: Date) => void
}

const Calendar: FC<CalendarProps> = ({
	className,
	classNames,
	showOutsideDays = true,
	captionLayout = 'label',
	formatters,
	components,
	month,
	setMonth,
	...props
}) => {
	const defaultClassNames = getDefaultClassNames()

	return (
		<DayPicker
			month={month}
			onMonthChange={setMonth}
			disableNavigation
			//
			showOutsideDays={showOutsideDays}
			className={cn('rounded-full', className)}
			captionLayout={captionLayout}
			formatters={{
				formatMonthDropdown: (date) => date.toLocaleString('default', { month: 'short' }),
				...formatters,
			}}
			classNames={{
				nav: 'hidden',
				month_caption: 'hidden',
				root: cn('w-fit !border-none', defaultClassNames.root),
				weekdays: cn('flex', defaultClassNames.weekdays),
				weekday: cn(
					'flex-1 select-none text-xs font-normal text-center',
					defaultClassNames.weekday
				),
				//

				week: cn('flex w-full mt-2 gap-2', defaultClassNames.week),
				week_number_header: cn('select-none w-(--cell-size)', defaultClassNames.week_number_header),
				week_number: cn(
					'text-[0.8rem] select-none text-muted-foreground',
					defaultClassNames.week_number
				),
				day: cn(
					'relative w-full h-full p-0 text-center [&:last-child[data-selected=true]_button]:rounded-r-md group/day select-none',
					'w-[44px] h-[40px]',
					props.showWeekNumber
						? '[&:nth-child(2)[data-selected=true]_button]:rounded-l-md'
						: '[&:first-child[data-selected=true]_button]:rounded-l-md',
					defaultClassNames.day
				),
				//
				range_start: cn('!border-primary !bg-transparent !rounded-full !outline-none'),
				range_middle: cn('rounded-none', defaultClassNames.range_middle),
				range_end: cn('!border-primary !bg-transparent !rounded-full !outline-none'),

				day_button: cn(
					'!aspect-square transition-colors rounded-full',
					'border-solid border-[3px] !border-transparent bg-transparent',
					'hover-hover:hover:border-primary hover-none:active:border-primary',
					//
					'data-[range-start="true"]:!border-primary data-[range-start="true"]:!bg-primary data-[range-start="true"]:!rounded-full data-[range-start="true"]:!aspect-square',
					//
					'data-[range-middle="true"]:!border-primary data-[range-middle="true"]:!bg-transparent data-[range-middle="true"]:!rounded-full data-[range-middle="true"]:!aspect-square',
					//
					'data-[range-end="true"]:!border-primary data-[range-end="true"]:!bg-primary data-[range-end="true"]:!rounded-full data-[range-end="true"]:!aspect-square'
				),

				today: cn(
					'!bg-black/5 !h-[40px] !w-[40px] !rounded-full data-[selected=true]:rounded-none !aspect-square',
					defaultClassNames.today
				),
				outside: cn('text-black/40', defaultClassNames.outside),
				disabled: cn('opacity-50', defaultClassNames.disabled),
				hidden: cn('invisible', defaultClassNames.hidden),
				...classNames,
			}}
			components={{
				Root: ({ className, rootRef, ...props }) => {
					return <div data-slot="calendar" ref={rootRef} className={cn(className)} {...props} />
				},

				// @ts-ignore
				DayButton: CalendarDayButton,
				WeekNumber: ({ children, ...props }) => {
					return (
						<td {...props}>
							<div className="flex size-(--cell-size) items-center justify-center text-center">
								{children}
							</div>
						</td>
					)
				},
				...components,
			}}
			{...props}
		/>
	)
}

const CalendarDayButton: FC<ComponentProps<typeof DayButton>> = ({
	className,
	day,
	modifiers,
	...props
}) => {
	const defaultClassNames = getDefaultClassNames()

	const ref = useRef<HTMLButtonElement>(null)

	useEffect(() => {
		if (modifiers.focused) ref.current?.focus()
	}, [modifiers.focused])

	return (
		<CalendarButton
			ref={ref}
			variant="ghost"
			size="icon"
			data-day={day.date.toLocaleDateString()}
			data-selected-single={
				modifiers.selected &&
				!modifiers.range_start &&
				!modifiers.range_end &&
				!modifiers.range_middle
			}
			data-range-start={modifiers.range_start}
			data-range-end={modifiers.range_end}
			data-range-middle={modifiers.range_middle}
			className={cn(
				'data-[selected-single=true]:bg-primary data-[selected-single=true]:text-primary-foreground data-[range-middle=true]:bg-accent data-[range-middle=true]:text-accent-foreground data-[range-start=true]:bg-primary data-[range-start=true]:text-primary-foreground data-[range-end=true]:bg-primary data-[range-end=true]:text-primary-foreground group-data-[focused=true]/day:border-ring group-data-[focused=true]/day:ring-ring/50 dark:hover:text-accent-foreground flex aspect-square size-auto w-full min-w-(--cell-size) flex-col gap-1 leading-none font-normal group-data-[focused=true]/day:relative group-data-[focused=true]/day:z-10 group-data-[focused=true]/day:ring-[3px] data-[range-end=true]:rounded-md data-[range-end=true]:rounded-r-md data-[range-middle=true]:rounded-none data-[range-start=true]:rounded-md data-[range-start=true]:rounded-l-md [&>span]:text-xs [&>span]:opacity-70',
				defaultClassNames.day,
				className
			)}
			{...props}
		/>
	)
}

export { Calendar, CalendarDayButton }
