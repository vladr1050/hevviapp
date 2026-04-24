/**
 * TXT → HTML for Sonata terms (carrier / sender).
 *
 *   node legal/build_legal_terms_html.mjs           # carrier + sender
 *   node legal/build_legal_terms_html.mjs carrier
 *   node legal/build_legal_terms_html.mjs sender
 */
import { buildTermsDoc, TERM_DOC_KEYS } from './terms_html_lib.mjs'

const arg = process.argv[2]
const keys = arg ? [arg] : TERM_DOC_KEYS
for (const k of keys) {
	buildTermsDoc(k)
}
