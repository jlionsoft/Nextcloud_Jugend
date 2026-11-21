import { createApp } from 'vue'
import App from './App.vue'
import { createAppRouter } from './router'

const root = document.getElementById('jugend-vue-root')
if (root) {
	const app = createApp(App)
	app.use(createAppRouter())
	app.mount(root)
}
