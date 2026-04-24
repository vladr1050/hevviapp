import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))

export const TERM_DOC_KEYS = ['carrier', 'sender']

const CONFIG = {
	carrier: {
		input: 'Hevvi_Noteikumi_Carrier.txt',
		output: 'Hevvi_Noteikumi_Carrier.html',
		divClass: 'terms-carrier-doc',
		titleLine: (t) => /^HEVVI APP VISPARIGIE NOTEIKUMI/i.test(t),
		defsEndLine: (line) => /^2\.\s+Vispārīgie/.test(line),
	},
	sender: {
		input: 'Hevvi_Noteikumi_Sender.txt',
		output: 'Hevvi_Noteikumi_Sender.html',
		divClass: 'terms-sender-doc',
		titleLine: (t) => /^HEVVI APP VISPARIGIE NOTEIKUMI/i.test(t),
		defsEndLine: (line) => /^2\.\s+Vispārīgie/.test(line),
	},
}

function esc(s) {
	return s
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
}

function isMajorSectionHeading(line) {
	const t = line.trim()
	if (!/^\d+\./.test(t)) return false
	if (/^\d+\.\d/.test(t)) return false
	return /^\d+\.\s+/.test(t)
}

function matchNumberedSubclause(line) {
	const t = line.trim()
	const m = t.match(/^(\d+(?:\.\d+)+)\s+(.+)$/)
	return m || null
}

function isTableHeaderRow(line) {
	if (!line.includes('\t')) return false
	const cells = line.split('\t').map((c) => c.trim()).filter(Boolean)
	return cells.length >= 2
}

function tableFromLines(lines, startIdx) {
	const rows = []
	let j = startIdx
	while (j < lines.length) {
		const line = lines[j]
		if (line.trim() === '') break
		if (!line.includes('\t')) break
		rows.push(line.split('\t').map((c) => c.trim()))
		j++
	}
	return { rows, next: j }
}

export function buildTermsDoc(key) {
	const cfg = CONFIG[key]
	if (!cfg) {
		console.error('Unknown document:', key, '| use:', TERM_DOC_KEYS.join(', '))
		process.exit(1)
	}
	const inp = path.join(__dirname, cfg.input)
	const out = path.join(__dirname, cfg.output)
	if (!fs.existsSync(inp)) {
		console.error('Missing input:', inp)
		process.exit(1)
	}

	const raw = fs.readFileSync(inp, 'utf8')
	const lines = raw.split(/\r?\n/)
	const parts = []
	const push = (s) => parts.push(s)

	let i = 0
	while (i < lines.length && !cfg.titleLine(lines[i].trim())) i++

	if (i >= lines.length) {
		console.error('Title line not found in', cfg.input)
		process.exit(1)
	}

	push('<h1>' + esc(lines[i].trim()) + '</h1>')
	i++

	while (i < lines.length && lines[i].trim() === '') i++
	push('<p class="terms-doc-subtitle">' + esc(lines[i].trim()) + '</p>')
	i++
	while (i < lines.length && lines[i].trim() === '') i++
	push('<p class="terms-doc-meta">' + esc(lines[i].trim()) + '</p>')
	i++
	while (i < lines.length && lines[i].trim() === '') i++

	/** Optional lines before "Saturs" (e.g. sender consumer notice); carrier goes straight to Saturs */
	while (i < lines.length && lines[i].trim() !== 'Saturs') {
		const pre = lines[i].trim()
		if (pre === '') {
			i++
			continue
		}
		push('<p>' + esc(pre) + '</p>')
		i++
	}
	while (i < lines.length && lines[i].trim() === '') i++

	if (i >= lines.length || lines[i].trim() !== 'Saturs') {
		console.error('Expected heading "Saturs" after title block in', cfg.input)
		process.exit(1)
	}

	push('<h2>' + esc(lines[i].trim()) + '</h2>')
	i++
	push('<ol>')
	while (i < lines.length && /^\d+\.\s+/.test(lines[i])) {
		const m = lines[i].match(/^\d+\.\s+(.*)$/)
		if (m) push('<li>' + esc(m[1].trim()) + '</li>')
		i++
	}
	push('</ol>')

	if (lines[i]?.trim().startsWith('Pielikums')) {
		const L = lines[i].trim()
		const dash = L.indexOf('–')
		if (dash > 0) {
			push(
				'<p><strong>' +
					esc(L.slice(0, dash).trim()) +
					'</strong> – ' +
					esc(L.slice(dash + 1).trim()) +
					'</p>',
			)
		} else push('<p>' + esc(L) + '</p>')
		i++
	}
	while (i < lines.length && lines[i].trim() === '') i++

	push('<hr />')

	push('<h2>' + esc(lines[i].trim()) + '</h2>')
	i++

	const sep = ' – '
	while (i < lines.length && !cfg.defsEndLine(lines[i])) {
		const line = lines[i].trim()
		if (line === '') {
			i++
			continue
		}
		const idx = line.indexOf(sep)
		if (idx > 0) {
			push('<p><strong>' + esc(line.slice(0, idx)) + '</strong> – ' + esc(line.slice(idx + sep.length)) + '</p>')
		} else push('<p>' + esc(line) + '</p>')
		i++
	}

	while (i < lines.length) {
		const line = lines[i]
		const t = line.trim()

		if (t === '') {
			i++
			continue
		}

		if (t.startsWith('- ')) {
			push('<ul>')
			while (i < lines.length && lines[i].trim().startsWith('- ')) {
				push('<li>' + esc(lines[i].trim().slice(2)) + '</li>')
				i++
			}
			push('</ul>')
			continue
		}

		if (line.includes('\t') && isTableHeaderRow(line)) {
			const { rows, next } = tableFromLines(lines, i)
			if (rows.length) {
				push('<table><thead><tr>')
				for (const cell of rows[0]) push('<th>' + esc(cell) + '</th>')
				push('</tr></thead><tbody>')
				for (let r = 1; r < rows.length; r++) {
					push('<tr>')
					for (const cell of rows[r]) push('<td>' + esc(cell) + '</td>')
					push('</tr>')
				}
				push('</tbody></table>')
			}
			i = next
			continue
		}

		if (t.startsWith('*')) {
			push('<p class="terms-doc-meta">' + esc(t) + '</p>')
			i++
			continue
		}

		if (t.startsWith('HEVVI APP – Digitāla') || t.startsWith('HEVVI APP - Digitāla')) {
			push('<p class="terms-doc-meta">' + esc(t) + '</p>')
			i++
			continue
		}

		if (t.includes('support@hevvi.app') && t.includes('hevvi.app')) {
			push('<p class="terms-doc-meta">' + esc(t) + '</p>')
			i++
			continue
		}

		if (isMajorSectionHeading(line)) {
			push('<h2>' + esc(t) + '</h2>')
			i++
			continue
		}

		const sub = matchNumberedSubclause(line)
		if (sub) {
			push('<p><strong>' + esc(sub[1]) + '</strong> ' + esc(sub[2]) + '</p>')
			i++
			continue
		}

		push('<p>' + esc(t) + '</p>')
		i++
	}

	const html = `<div class="${cfg.divClass}">
${parts.join('\n')}
</div>
`
	fs.writeFileSync(out, html, 'utf8')
	console.log('OK', key, out, Buffer.byteLength(html, 'utf8'), 'bytes')
}
