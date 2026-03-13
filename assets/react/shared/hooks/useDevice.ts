import { useEffect, useState } from 'react'

import Cookies from 'js-cookie'

export type DeviceType = 'desktop' | 'tablet' | 'mobile'

const getDevice = (width: number): DeviceType => {
	if (width < 768) return 'mobile'
	if (width < 1024) return 'tablet'
	return 'desktop'
}

export function useDevice(initialDevice?: DeviceType) {
	const [device, setDevice] = useState<DeviceType | undefined>(initialDevice)

	useEffect(() => {
		const updateDevice = () => {
			const newDevice = getDevice(window.innerWidth)

			setDevice((prev) => {
				if (prev !== newDevice) {
					Cookies.set('device', newDevice)
					return newDevice
				}
				return prev
			})
		}

		updateDevice()

		window.addEventListener('resize', updateDevice)
		return () => window.removeEventListener('resize', updateDevice)
	}, [])

	return {
		isMobile: device === 'mobile',
		isTablet: device === 'tablet',
		isDesktop: device === 'desktop',
	}
}
