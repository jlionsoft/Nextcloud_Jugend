import { createApp } from 'vue'
import SettingsApp from './components/SettingsApp.vue'

const root = document.getElementById('jugend-vue-root')
if (root) {
	createApp(SettingsApp, {
		values: JSON.parse(root.dataset.values || '{}'),
		configured: JSON.parse(root.dataset.configured || '{}'),
		talkWebhookUrl: root.dataset.talkWebhookUrl || '',
	}).mount(root)
}
