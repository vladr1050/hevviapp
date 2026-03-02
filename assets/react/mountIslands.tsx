import React from 'react'
import { createRoot } from 'react-dom/client'

import { Theme } from '@radix-ui/themes'

import * as components from './islands'

const registry: Record<string, React.ComponentType<any>> = { ...components }

function parseProps(el: HTMLElement) {
	const raw = el.dataset.props
	if (!raw) return {}
	try {
		return JSON.parse(raw)
	} catch (e) {
		console.error('Invalid data-props JSON for island:', el, e)
		return {}
	}
}

export function mountIslands(root: ParentNode = document) {
	const nodes = root.querySelectorAll<HTMLElement>('[data-react-island]')
	nodes.forEach((el) => {
		const name = el.dataset.reactIsland
		if (!name) return

		const Component = registry[name]
		if (!Component) {
			console.warn(`React island "${name}" not found in registry`)
			return
		}

		const props = parseProps(el)

		createRoot(el).render(
			<Theme>
				<Component {...props} />
			</Theme>
		)
	})
}
