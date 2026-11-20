import { createApp } from 'vue'
import AppShell from './AppShell.vue'

const root = document.getElementById('jugend-vue-root')
if (root) {
	const app = createApp(AppShell, {
		items: JSON.parse(root.dataset.navigation || '[]'),
		content: root.innerHTML,
		pageHeading: 'Organisationsverwaltung',
		legacyScript: 'organization',
	})
	app.mount(root)
}
