import { createApp } from 'vue'
import GroupsApp from './components/GroupsApp.vue'

const root = document.getElementById('jugend-vue-root')
if (root) {
	createApp(GroupsApp, {
		navigation: JSON.parse(root.dataset.navigation || '[]'),
	}).mount(root)
}
