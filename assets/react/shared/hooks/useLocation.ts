export const useLocation = () => {
	const pathname = window.location.pathname
	const urlParams = new URLSearchParams(window.location.search)

	const push = (url: string, options?: { replace?: boolean }) => {
		if (options?.replace) window.location.replace(url)
		else window.location.assign(url)
	}

	return { pathname, urlParams, push }
}
