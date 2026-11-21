import { createApp } from 'vue'
import AccountingApp from './components/AccountingApp.vue'

const root = document.getElementById('jugend-vue-root')
if (root) {
	createApp(AccountingApp, {
		navigation: JSON.parse(root.dataset.navigation || '[]'),
		mode: root.dataset.mode || 'personal',
	}).mount(root)
}
