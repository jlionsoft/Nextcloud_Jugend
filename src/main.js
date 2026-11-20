import { createApp } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import MainApp from './components/MainApp.vue'

const root = document.getElementById('jugend-vue-root')
if (root) {
	let navigation = []
	try {
		navigation = JSON.parse(root.dataset.navigation || '[]')
	} catch (error) {
		console.error('Jugend: Invalid navigation state.', error)
	}
	const urls = loadState('jugend', 'urls', {})
	const strings = loadState('jugend', 'strings', {})
	createApp(MainApp, { navigation, urls, strings }).mount(root)
}
