<template>
	<NcContent>
		<NcAppNavigation :aria-label="navigationLabel">
			<template #list>
				<NcAppNavigationItem
					v-for="item in items"
					:key="item.url"
					:name="item.label"
					:href="item.url"
					:active="item.active"
					@click.prevent="navigate(item.url)">
					<template #icon>
						<span :class="[item.icon]" aria-hidden="true" />
					</template>
				</NcAppNavigationItem>
			</template>
		</NcAppNavigation>
		<NcAppContent :page-heading="pageHeading">
			<div v-html="content" />
		</NcAppContent>
	</NcContent>
</template>

<script setup>
import { onMounted } from 'vue'
import { t } from '@nextcloud/l10n'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'

const props = defineProps({
	items: { type: Array, required: true },
	content: { type: String, required: true },
	pageHeading: { type: String, required: true },
	legacyScript: { type: String, default: '' },
})

const navigationLabel = t('jugend', 'App-Navigation')

function navigate(url) {
	window.location.assign(url)
}

onMounted(() => {
	if (props.legacyScript === 'groups') import('./legacy/groups.js')
	if (props.legacyScript === 'organization') import('./legacy/organization.js')
	if (props.legacyScript === 'settings') import('./legacy/settings.js')
})
</script>
