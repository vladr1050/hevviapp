import { type FC, useState } from 'react'

import {
	AlertDialog,
	Avatar,
	Box,
	// Button,
	// Checkbox,
	Dialog,
	DropdownMenu,
	Flex,
	Heading,
	HoverCard,
	IconButton,
	Link,
	Popover,
	RadioGroup,
	Select as RadixSelect,
	SegmentedControl,
	Text,
	// TextArea,
	TextField,
	Tooltip,
} from '@radix-ui/themes'
import { Button } from '@ui/Button/Button'
import { Checkbox } from '@ui/Checkbox/Checkbox'
// import { Input } from '@ui/Input/Input'
import { Modal } from '@ui/Modal/Modal'
// import { Select } from '@ui/Select/Select'
import { Slider } from '@ui/Slider/Slider'
import { Switch } from '@ui/Switch/Switch'
import { Tabs } from '@ui/Tabs/Tabs'

// import { Textarea } from '@ui/Textarea/Textarea'

import styles from './test.module.css'

// import { Header } from '@/islands/components/Header/Header'
// import { Info } from '@/islands/components/Info/Info'

interface OrdersFiltersIslandProps {}

export const OrdersFiltersIsland: FC<OrdersFiltersIslandProps> = (props) => {
	const [count, setCount] = useState(10)

	console.log(props)

	const [isOpen, setIsOpen] = useState(false)

	return (
		<>
			{/* <Header /> */}
			<div className="container bg-emerald-700">
				<h2 className={styles.test}>count1: {count}</h2>

				<button type="button" onClick={() => setCount((v) => v + 1)}>
					add1
				</button>
			</div>
			<br />
			<br />
			<button onClick={() => setIsOpen(true)}>Open</button>
			<Modal isOpen={isOpen} onClose={() => setIsOpen(false)}>
				12345
			</Modal>
			<br />
			{/* <div className="bg-red-400 animate-spin">123</div> */}
			<Tabs
				items={[
					{ label: 'tab1', value: 'tab1' },
					{ label: 'tab2', value: 'tab2' },
					{ label: 'tab3', value: 'tab3' },
				]}
				defaultValue="tab1"
			/>
			<br />
			<br />
			<div className="p-5 w-full h-[100px] border border-red-500">
				<Slider label="label" value={0} />
			</div>
			<br />
			{/* <Input register={{}} placeholder="placeholder" label="password" /> */}
			{/* <Input placeholder="placeholder" label="label" disabled /> */}
			<br />
			{/* <Textarea placeholder="placeholder" label="label" /> */}
			{/* <Textarea placeholder="placeholder" label="label" disabled /> */}
			<br />
			<Button className={styles.test}>Button</Button>
			<Button className={styles.test} variant="outline">
				Button
			</Button>
			<br />
			<Button className={styles.test} disabled>
				Button
			</Button>
			<Button className={styles.test} variant="outline" disabled>
				Button
			</Button>
			<Button className={styles.test} variant="outline" disabled>
				Button
			</Button>
			<br />
			<div className="flex flex-col gap-3">
				<Switch label="default" />
				<Switch checked label="checked" />
				<Switch disabled label="disabled" />
				<Switch checked disabled label="checked & disabled" />
			</div>
			<br />
			<div className="flex flex-col gap-3">
				<Checkbox defaultChecked onChange={() => {}} color="default">
					checked - active
				</Checkbox>
				<Checkbox defaultChecked onChange={() => {}} color="default" disabled>
					checked - disabled
				</Checkbox>
				<Checkbox defaultChecked={false} onChange={() => {}} color="default">
					unchecked - active
				</Checkbox>
				<Checkbox defaultChecked={false} onChange={() => {}} color="default" disabled>
					unchecked - disabled
				</Checkbox>
			</div>
			<br />
			<div className="flex gap-2">
				{/* <div className="border border-red w-[200px] h-[200px]"> 
				 <Select
						placeholder="Pick a fruit"
						color="black"
						values={[
							{ value: 'Orange', label: 'Orange', disabled: false },
							{ value: 'Apple', label: 'Apple', disabled: !false },
							{ value: 'Grape', label: 'Grape', disabled: false },
						]}
					/>
				</div>

				<div className="border border-red w-[200px] h-[200px]">
					<Select
						placeholder="Pick a fruit"
						color="gray"
						values={[
							{ value: 'Orange', label: 'Orange', disabled: false },
							{ value: 'Apple', label: 'Apple', disabled: !false },
							{ value: 'Grape', label: 'Grape', disabled: false },
						]}
					/>
				</div>

				<div className="border border-red w-[200px] h-[200px]">
					<Select
						placeholder="Pick a fruit"
						color="green"
						values={[
							{ value: 'Orange', label: 'Orange', disabled: false },
							{ value: 'Apple', label: 'Apple', disabled: !false },
							{ value: 'Grape', label: 'Grape', disabled: false },
						]}
					/>
				</div>

				<div className="border border-red w-[200px] h-[200px]">
					<Select
						placeholder="Pick a fruit"
						disabled
						color="green"
						values={[
							{ value: 'Orange', label: 'Orange', disabled: false },
							{ value: 'Apple', label: 'Apple', disabled: !false },
							{ value: 'Grape', label: 'Grape', disabled: false },
						]}
					/>
				</div> */}
			</div>
			<br />
			<br />
			<br />
			<AlertDialog.Root>
				<AlertDialog.Trigger>
					<Button>Revoke access</Button>
				</AlertDialog.Trigger>
				<AlertDialog.Content maxWidth="450px">
					<AlertDialog.Title>Revoke access</AlertDialog.Title>
					<AlertDialog.Description size="2">
						Are you sure? This application will no longer be accessible and any existing sessions
						will be expired.
					</AlertDialog.Description>

					<Flex gap="3" mt="4" justify="end">
						<AlertDialog.Cancel>
							<Button>Cancel</Button>
						</AlertDialog.Cancel>
						<AlertDialog.Action>
							<Button>Revoke access</Button>
						</AlertDialog.Action>
					</Flex>
				</AlertDialog.Content>
			</AlertDialog.Root>
			<br />
			<Dialog.Root>
				<Dialog.Trigger>
					<Button>Edit profile</Button>
				</Dialog.Trigger>

				<Dialog.Content maxWidth="450px">
					<Dialog.Title>Edit profile</Dialog.Title>
					<Dialog.Description size="2" mb="4">
						Make changes to your profile.
					</Dialog.Description>

					<Flex direction="column" gap="3">
						<label>
							<Text as="div" size="2" mb="1" weight="bold">
								Name
							</Text>
							<TextField.Root defaultValue="Freja Johnsen" placeholder="Enter your full name" />
						</label>
						<label>
							<Text as="div" size="2" mb="1" weight="bold">
								Email
							</Text>
							<TextField.Root defaultValue="freja@example.com" placeholder="Enter your email" />
						</label>
					</Flex>

					<Flex gap="3" mt="4" justify="end">
						<Dialog.Close>
							<Button>Cancel</Button>
						</Dialog.Close>
						<Dialog.Close>
							<Button>Save</Button>
						</Dialog.Close>
					</Flex>
				</Dialog.Content>
			</Dialog.Root>
			<br />
			<DropdownMenu.Root>
				<DropdownMenu.Trigger>
					<Button>
						Options
						<DropdownMenu.TriggerIcon />
					</Button>
				</DropdownMenu.Trigger>
				<DropdownMenu.Content>
					<DropdownMenu.Item shortcut="⌘ E">Edit</DropdownMenu.Item>
					<DropdownMenu.Item shortcut="⌘ D">Duplicate</DropdownMenu.Item>
					<DropdownMenu.Separator />
					<DropdownMenu.Item shortcut="⌘ N">Archive</DropdownMenu.Item>

					<DropdownMenu.Sub>
						<DropdownMenu.SubTrigger>More</DropdownMenu.SubTrigger>
						<DropdownMenu.SubContent>
							<DropdownMenu.Item>Move to project…</DropdownMenu.Item>
							<DropdownMenu.Item>Move to folder…</DropdownMenu.Item>

							<DropdownMenu.Separator />
							<DropdownMenu.Item>Advanced options…</DropdownMenu.Item>
						</DropdownMenu.SubContent>
					</DropdownMenu.Sub>

					<DropdownMenu.Separator />
					<DropdownMenu.Item>Share</DropdownMenu.Item>
					<DropdownMenu.Item>Add to favorites</DropdownMenu.Item>
					<DropdownMenu.Separator />
					<DropdownMenu.Item shortcut="⌘ ⌫" color="red">
						Delete
					</DropdownMenu.Item>
				</DropdownMenu.Content>
			</DropdownMenu.Root>
			<br />
			<Text>
				Follow{' '}
				<HoverCard.Root>
					<HoverCard.Trigger>
						<Link href="https://twitter.com/radix_ui" target="_blank">
							@radix_ui
						</Link>
					</HoverCard.Trigger>
					<HoverCard.Content maxWidth="300px">
						<Flex gap="4">
							<Avatar
								size="3"
								fallback="R"
								radius="full"
								src="https://pbs.twimg.com/profile_images/1337055608613253126/r_eiMp2H_400x400.png"
							/>
							<Box>
								<Heading size="3" as="h3">
									Radix
								</Heading>
								<Text as="div" size="2" color="gray" mb="2">
									@radix_ui
								</Text>
								<Text as="div" size="2">
									React components, icons, and colors for building high-quality, accessible UI.
								</Text>
							</Box>
						</Flex>
					</HoverCard.Content>
				</HoverCard.Root>{' '}
				for updates.
			</Text>
			<br />
			<Popover.Root>
				<Popover.Trigger>
					<Button>Comment</Button>
				</Popover.Trigger>
				<Popover.Content width="360px">
					<Flex gap="3">
						<Avatar
							size="2"
							src="https://images.unsplash.com/photo-1607346256330-dee7af15f7c5?&w=64&h=64&dpr=2&q=70&crop=focalpoint&fp-x=0.67&fp-y=0.5&fp-z=1.4&fit=crop"
							fallback="A"
							radius="full"
						/>
						<Box flexGrow="1">
							{/* <Textarea placeholder="Write a comment…" /> */}
							<Flex gap="3" mt="3" justify="between">
								<Flex align="center" gap="2" asChild>
									<Text as="label" size="2">
										<Checkbox />
										<Text>Send to group</Text>
									</Text>
								</Flex>

								<Popover.Close>
									<Button>Comment</Button>
								</Popover.Close>
							</Flex>
						</Box>
					</Flex>
				</Popover.Content>
			</Popover.Root>
			<br />
			<RadioGroup.Root defaultValue="1" name="example">
				<RadioGroup.Item value="1">Default</RadioGroup.Item>
				<RadioGroup.Item value="2">Comfortable</RadioGroup.Item>
				<RadioGroup.Item value="3">Compact</RadioGroup.Item>
			</RadioGroup.Root>
			<br />
			<SegmentedControl.Root defaultValue="inbox">
				<SegmentedControl.Item value="inbox">Inbox</SegmentedControl.Item>
				<SegmentedControl.Item value="drafts">Drafts</SegmentedControl.Item>
				<SegmentedControl.Item value="sent">Sent</SegmentedControl.Item>
			</SegmentedControl.Root>
			<br />
			<RadixSelect.Root defaultValue="apple">
				<RadixSelect.Trigger />
				<RadixSelect.Content>
					<RadixSelect.Group>
						<RadixSelect.Label>Fruits</RadixSelect.Label>
						<RadixSelect.Item value="orange">Orange</RadixSelect.Item>
						<RadixSelect.Item value="apple">Apple</RadixSelect.Item>
						<RadixSelect.Item value="grape" disabled>
							Grape
						</RadixSelect.Item>
					</RadixSelect.Group>
					<RadixSelect.Separator />
					<RadixSelect.Group>
						<RadixSelect.Label>Vegetables</RadixSelect.Label>
						<RadixSelect.Item value="carrot">Carrot</RadixSelect.Item>
						<RadixSelect.Item value="potato">Potato</RadixSelect.Item>
					</RadixSelect.Group>
				</RadixSelect.Content>
			</RadixSelect.Root>
			<br />
			<Tooltip content="Add to library">
				<IconButton radius="full">PlusIcon</IconButton>
			</Tooltip>
			<br />
			Lorem, ipsum dolor sit amet consectetur adipisicing elit. Ipsam temporibus distinctio deserunt
			tempora eos labore nam laudantium praesentium velit modi. Labore neque iusto pariatur, placeat
			temporibus dignissimos itaque quod at, in cupiditate molestiae. Quasi maiores, porro facilis
			ad minus sapiente voluptate culpa quo, velit iusto animi ullam dolorum dolor voluptatum a odit
			vel. Pariatur saepe obcaecati voluptatibus nihil culpa est officia itaque quo corporis quis,
			totam dolorem architecto eveniet deleniti in necessitatibus sed nostrum placeat accusamus
			provident rem unde cum consectetur sapiente. Excepturi quam unde nulla numquam blanditiis.
			Ipsum, nihil autem atque nesciunt eaque eos molestias ea voluptatum cum blanditiis at, quod
			eius fuga quidem fugiat vel ab in. Nihil maxime corporis nobis eos quae placeat quidem
			molestias, velit iure mollitia dolorem, laborum perferendis cumque minus voluptatum hic quo
			doloribus beatae at dolores. Fuga, dolor. Eaque itaque odio expedita aut eveniet cumque quo,
			provident fugiat delectus, amet hic nisi dolorem repudiandae quisquam, reiciendis rem tenetur
			ipsam doloremque vero magni vitae. Expedita quas alias numquam. Odit unde minus reiciendis
			facere neque ullam error inventore? Explicabo aspernatur totam, amet autem nobis non, dicta
			eius quis placeat voluptatibus, aliquam nemo sed vero quam reiciendis ab vel. Maxime quidem
			voluptatibus odit id sint neque dolorum amet vitae magni fugit alias vero, saepe vel itaque
			eligendi sed rem, minima animi repellat omnis sit architecto quam quae? Atque, optio? Enim
			voluptate, iste quibusdam aut tenetur reprehenderit eligendi fuga! Ad ipsa asperiores culpa at
			odio magnam aut optio, nesciunt ut minima voluptatum quam qui neque sapiente repellendus
			ratione excepturi. Ipsum voluptatum molestiae eos repellendus, commodi eaque perferendis porro
			nam tempore aspernatur optio facilis quasi vero esse at, a suscipit fugiat beatae nesciunt
			sit. Labore quis temporibus sed dignissimos nisi ipsa optio qui quos dolore aperiam. Labore
			minima officia obcaecati placeat et. Aliquam, delectus praesentium rem tenetur, error repellat
			doloremque, facilis alias incidunt labore quae laboriosam ratione optio dicta. Facilis,
			impedit officiis cum odio fugiat nam harum sed debitis doloremque commodi quae iusto nostrum
			dolores voluptas eum corporis beatae ex natus nesciunt voluptatum possimus iste! Cumque
			facilis, magnam animi itaque pariatur cupiditate perspiciatis, dolorem assumenda consequatur
			modi obcaecati error, voluptatem mollitia repellendus ratione soluta nisi labore saepe sequi.
			Aliquid magnam quisquam officia veritatis nisi saepe deserunt nam ut adipisci ea at, provident
			itaque accusamus quos totam sapiente commodi iusto animi ducimus molestias. Dignissimos esse
			rerum earum, repudiandae ipsa tenetur facere accusamus, blanditiis cumque nisi consequuntur
			deserunt? Vero sint cumque officiis, placeat natus laborum praesentium cum dignissimos eius
			voluptatibus minima amet veritatis aspernatur provident aliquid similique commodi! Illum earum
			nihil non? Fuga nostrum id quidem, vero facilis quo quisquam ad iusto quibusdam distinctio
			nihil repellendus. Recusandae aliquam soluta similique non dolor ab laborum. Neque, unde sunt
			cumque mollitia modi, magni nam, sit voluptas itaque quod perspiciatis! Fuga sequi asperiores
			corrupti libero? Cumque tempore quibusdam quam porro soluta repudiandae hic aperiam ducimus
			obcaecati voluptates? Voluptate consequatur beatae et iusto rerum rem deleniti error? Modi
			quae libero non laboriosam neque aut quo explicabo itaque voluptatibus a, perspiciatis
			perferendis delectus, eveniet eaque consectetur molestiae iste! Facere, assumenda unde soluta
			neque maxime magnam molestiae ipsa reiciendis mollitia possimus nostrum exercitationem
			accusantium quas optio voluptate incidunt excepturi veritatis dignissimos odit deserunt
			blanditiis consequatur consectetur. Molestias dolore vel quaerat? Quas consequatur dignissimos
			a rerum, nisi, sequi error voluptatum dicta unde distinctio itaque vel fugiat temporibus
			sapiente ad sit quaerat qui aspernatur commodi id ab atque assumenda eum est. Officia id illum
			asperiores obcaecati? At iure harum cupiditate vel ipsam dolor mollitia voluptates facere. Eum
			repudiandae laboriosam, cum harum quaerat dignissimos rem enim. Tenetur eligendi dolorem eaque
			accusamus blanditiis aliquam ex. Reiciendis obcaecati omnis commodi magni at expedita earum.
			Atque, impedit! Voluptate deserunt aspernatur atque hic, nisi culpa quos iste deleniti
			molestiae pariatur ipsam id minus eligendi. Ea dicta omnis sed blanditiis praesentium alias ab
			sit reprehenderit, esse sunt quia laborum nisi atque, quos unde vitae aperiam odit optio
			asperiores laboriosam fuga eligendi ducimus explicabo sapiente? Quos, magni minus.
			Necessitatibus porro aspernatur, eaque quod neque doloremque fugit ut atque expedita est
			impedit vero qui doloribus obcaecati rem voluptatum dicta iste eligendi possimus beatae eius
			omnis vel explicabo! Accusantium, nam est? Voluptate culpa ratione ipsa, distinctio rerum,
			ullam explicabo ad magnam quam adipisci at minima nulla odit mollitia qui inventore.
			Voluptatibus minus autem nesciunt quaerat tempore dolor? Dolores natus recusandae, ullam
			necessitatibus doloremque molestiae blanditiis ratione ipsam mollitia illo cum asperiores
			voluptates odio amet unde labore odit aliquid cupiditate placeat voluptas ipsa molestias
			voluptatibus corrupti ad. Omnis quis amet soluta nihil fugiat pariatur ipsum eum, ab sapiente
			quam rem in cum porro dignissimos, blanditiis voluptatibus corporis velit? Architecto
			praesentium voluptate vel maxime inventore voluptas nesciunt nemo non? Ab dolores error
			perspiciatis corrupti odio deserunt obcaecati. Atque tenetur hic et molestiae. Nostrum
			dolores, dolorum atque, vitae deleniti, commodi aspernatur id itaque blanditiis autem eum. Aut
			cum amet ratione ab quisquam vitae maxime obcaecati quod accusantium hic praesentium iste
			itaque ipsam exercitationem blanditiis eos, ea dolorem harum error, inventore corporis atque
			animi sunt dignissimos. Cumque amet aperiam facere unde numquam, error itaque molestias odio
			modi perspiciatis, natus, voluptatem in corrupti beatae corporis rem soluta ex dicta
			inventore! Unde nobis error fugiat obcaecati tempora voluptatum corrupti maiores ea voluptas
			porro corporis nulla ab deleniti temporibus hic enim sequi dignissimos ipsum amet, commodi
			aperiam fugit laboriosam! Repudiandae ducimus voluptatum labore distinctio corrupti culpa quas
			architecto ab, nesciunt fuga adipisci consectetur porro voluptates et maxime earum voluptate
			quaerat minima explicabo assumenda. Doloremque quaerat iste repellendus expedita? Ipsa enim
			error tempore explicabo repellat? Provident deserunt autem ab, ipsa quasi voluptates minima
			vel nisi nulla enim a. Eos perferendis, quisquam non quam accusantium aliquam facilis, quo
			voluptatibus a aperiam eaque unde qui labore quaerat eveniet quidem ex praesentium omnis sint
			natus et odit optio? Soluta veritatis error, ipsum nam sequi ipsa modi unde et nihil delectus
			inventore ab similique debitis? Molestias doloribus consectetur cum qui tenetur maiores quis
			eos, repudiandae, accusamus ipsa animi recusandae nisi facilis dolor quod rem. Sunt
			accusantium, dicta reprehenderit possimus beatae assumenda ipsum obcaecati dolor libero aut
			est debitis similique.
		</>
	)
}
