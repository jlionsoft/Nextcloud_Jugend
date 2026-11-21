<template>
	<NcContent app-name="jugend">
		<NcAppNavigation :aria-label="tr('App-Navigation')">
			<template #list>
				<NcAppNavigationItem
					v-for="item in navigation"
					:key="item.url"
					:name="item.label"
					:href="internalRoute(item) ? undefined : item.url"
					:to="internalRoute(item) || undefined"
					:active="item.active">
					<template #icon>
						<span :class="[item.icon]" aria-hidden="true" />
					</template>
				</NcAppNavigationItem>
			</template>
		</NcAppNavigation>
		<NcAppContent>
			<RouterView />
		</NcAppContent>
	</NcContent>
</template>

<script setup>
import { t } from '@nextcloud/l10n'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import { RouterView } from 'vue-router'

const navigationRoot = document.getElementById('jugend-vue-root')
let navigation = []
try {
	navigation = JSON.parse(navigationRoot?.dataset.navigation || '[]')
} catch (error) {
	console.error('Jugend: Invalid navigation state.', error)
}
const tr = (text) => t('jugend', text)

function internalRoute(item) {
	try {
		const url = new URL(item.url, window.location.origin)
		const baseUrl = window.location.pathname
			.replace(/\/(?:organization|groups|accounting\/(?:manage|me))\/?$/, '')
		if (!url.pathname.startsWith(baseUrl)) {
			return undefined
		}
		const route = url.pathname.slice(baseUrl.length).replace(/^\/+/, '')
		return route ? `/${route}` : '/'
	} catch {
		return undefined
	}
}
</script>
