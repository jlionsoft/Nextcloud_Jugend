<template>
	<NcAppNavigation :aria-label="label">
		<template #list>
			<NcAppNavigationItem
				v-for="item in items"
				:key="item.url"
				:name="item.label"
				:href="item.url"
				:active="item.active"
				@click="navigate(item.url)">
				<template #icon>
					<span :class="[item.icon]" aria-hidden="true" />
				</template>
			</NcAppNavigationItem>
		</template>
	</NcAppNavigation>
</template>

<script setup>
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import { t } from '@nextcloud/l10n'

defineProps({
	items: {
		type: Array,
		required: true,
	},
	label: {
		type: String,
		default: t('jugend', 'App-Navigation'),
	},
})

function navigate(url) {
	window.location.assign(url)
}
</script>
