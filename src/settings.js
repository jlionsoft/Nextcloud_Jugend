import { createApp, h } from 'vue'

createApp({
	render: () => h('span', { hidden: true }),
	mounted() {
		import('./legacy/settings.js')
	},
}).mount(document.createElement('div'))
