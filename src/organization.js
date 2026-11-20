import { createApp } from 'vue'
import OrganizationApp from './components/OrganizationApp.vue'

const root = document.getElementById('jugend-vue-root')
if (root) {
	createApp(OrganizationApp, {
		navigation: JSON.parse(root.dataset.navigation || '[]'),
	}).mount(root)
}
