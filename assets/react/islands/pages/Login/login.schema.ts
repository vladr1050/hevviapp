import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'

const schema1 = z.object({
	login: z
		.string()
		.trim()
		.min(1, { error: 'E-mail is required' })
		.refine((val) => z.email({ error: 'E-mail is invalid' }).safeParse(val).success, {
			error: 'E-mail is invalid',
		}),
	password: z.string().trim().min(1, { error: 'Password is required' }),
})

const schema = z.object({
	login: z
		.string({
			error: (iss) => {
				if (iss.code === 'invalid_type') {
					return 'E-mail is required'
				}
				return undefined
			},
		})
		.trim()
		.min(1, { error: 'E-mail is required' })
		.refine((val) => z.email({ error: 'E-mail is invalid' }).safeParse(val).success, {
			error: 'E-mail is invalid',
		}),

	password: z
		.string({
			error: (iss) => {
				if (iss.code === 'invalid_type') {
					return 'Password is required'
				}
				return undefined
			},
		})
		.trim()
		.min(1, { error: 'Password is required' }),
})

export const resolver = zodResolver(schema)
