export enum UIIconNames {
	ARROW_RIGHT = 'ArrowRight',
	GREEN_CHECK = 'GreenCheck',
	CALCULATE_PRICE = 'CalculatePrice',
	PROFILE = 'Profile',
	LOGOUT = 'Logout',
	CONFIRM_ORDER = 'ConfirmOrder',
	DELIVERY = 'Delivery',
	SEARCHED_HISTORY = 'SearchedHistory',
	PREVIOUS_ORDERS = 'PreviousOrders',
	VEHICLE = 'Vehicle',
	BIG_VEHICLE = 'BigVehicle',
	VEHICLE_DRIVE = 'VehicleDrive',
	VEHICLE_CHECK = 'VehicleCheck',
	VEHICLE_CANCEL = 'VehicleCancel',
	VEHICLE_RIGHT = 'VehicleRight',
	CANCEL = 'Cancel',
	TRUST = 'Trust',
	BIG_TRUST = 'BigTrust',
	BOX = 'Box',
	BIG_BOX = 'BigBox',
	SMILE_BOX = 'SmileBox',
	SAD_BOX = 'SadBox',
	UP_BOX = 'UpBox',
	PATH_MAP = 'PathMap',
	TIME = 'Time',
	BLOCK_MAIL = 'BlockMail',
	MARK_MAP = 'MarkMap',
	CLOCK_1 = 'Clock1',
	CLOCK_2 = 'Clock2',
	CHECK_CIRCLE_1 = 'CheckCircle1',
	CHECK_CIRCLE_2 = 'CheckCircle2',
	PLUS = 'Plus',
	DOWNLOAD_FILE = 'DownloadFile',
	TRASH = 'Trash',
	LIKE = 'Like',
	QUESTION_CIRCLE = 'QuestionCircle',
	STAR = 'Star',
	CART = 'Cart',
	EYE = 'Eye',
	EYE_CLOSE = 'EyeClose',
	SWAP = 'Swap',
	SEARCH = 'Search',
	CHECK = 'Check',
	X_MARK = 'XMark',
}

export type IconNameType = Lowercase<keyof typeof UIIconNames>

export type IconProps = {
	type: IconNameType
	size?: number
	className?: string
	currentColor?: boolean
}
