<template>
	<NcContent app-name="jugend">
		<NcAppNavigation :aria-label="tr('App-Navigation')">
			<template #list>
				<NcAppNavigationItem v-for="item in navigation" :key="item.url" :name="item.label" :href="item.url" :active="item.active" @click.prevent="navigate(item.url)">
					<template #icon><span :class="[item.icon]" aria-hidden="true" /></template>
				</NcAppNavigationItem>
			</template>
		</NcAppNavigation>
		<NcAppContent :page-heading="mode === 'manage' ? tr('Buchhaltung verwalten') : tr('Mein Kontostand')">
			<div class="jugend-page jugend-accounting">
				<header class="jugend-page__header">
					<div>
						<h1>{{ mode === 'manage' ? tr('Buchhaltung verwalten') : tr('Mein Kontostand') }}</h1>
						<p>{{ mode === 'manage' ? tr('Bargeld-Buchungen je Mitglied erfassen.') : tr('Deine persönlichen Bargeld-Buchungen und dein Kontostand.') }}</p>
					</div>
				</header>
				<NcNoteCard v-if="feedback" :type="feedbackType">{{ feedback }}</NcNoteCard>

				<section v-if="mode === 'manage'" class="jugend-panel jugend-accounting-form">
					<h2>{{ tr('Neue Bargeld-Buchung') }}</h2>
					<form @submit.prevent="addEntry">
						<NcSelect v-model="selectedUser" :options="userOptions" label="displayName" :placeholder="tr('Person auswählen')" />
						<NcTextField v-model="amount" type="number" step="0.01" label-outside :label="tr('Betrag in Euro')" :placeholder="'10.00'" />
						<NcTextField v-model="description" :label="tr('Verwendungszweck')" :placeholder="tr('optional')" />
						<NcTextField v-model="bookedAt" type="date" :label="tr('Buchungsdatum')" />
						<div class="jugend-form-actions">
							<NcButton type="submit" variant="primary" :disabled="saving">{{ tr('Buchung speichern') }}</NcButton>
						</div>
					</form>
				</section>

				<section class="jugend-panel">
					<h2 v-if="mode === 'manage'">{{ tr('Konten') }}</h2>
					<h2 v-else>{{ tr('Kontostand') }}</h2>
					<div v-if="mode === 'personal'" class="jugend-accounting-balance">
						<strong>{{ formatMoney(balanceCents) }}</strong>
					</div>
					<div v-else class="jugend-accounting-balances">
						<div v-for="user in users" :key="user.uid" class="jugend-accounting-balance-row">
							<span>{{ user.displayName }} ({{ user.uid }})</span>
							<strong>{{ formatMoney(balances[user.uid] || 0) }}</strong>
						</div>
					</div>
					<h2 class="jugend-accounting-entries-heading">{{ tr('Buchungen') }}</h2>
					<p v-if="loading" class="jugend-muted">{{ tr('Buchungen werden geladen …') }}</p>
					<p v-else-if="!entries.length" class="jugend-muted">{{ tr('Keine Buchungen vorhanden.') }}</p>
					<div v-else class="jugend-accounting-table">
						<div v-for="entry in entries" :key="entry.id" class="jugend-accounting-entry">
							<span class="jugend-accounting-entry__date">{{ formatDate(entry.bookedAt) }}</span>
							<span v-if="mode === 'manage'" class="jugend-accounting-entry__person">{{ personName(entry.userId) }}</span>
							<span class="jugend-accounting-entry__description">{{ entry.description || tr('Bargeld-Buchung') }}</span>
							<strong class="jugend-accounting-entry__amount">{{ formatMoney(entry.amountCents) }}</strong>
						</div>
					</div>
				</section>
			</div>
		</NcAppContent>
	</NcContent>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import axios from '@nextcloud/axios'
import { t } from '@nextcloud/l10n'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelect from '@nextcloud/vue/components/NcSelect'

const props = defineProps({
	navigation: { type: Array, required: true },
	mode: { type: String, required: true },
})
const tr = (text) => t('jugend', text)
const urls = loadState('jugend', 'accountingUrls')
const users = ref([])
const userOptions = ref([])
const selectedUser = ref(null)
const amount = ref('')
const description = ref('')
const bookedAt = ref(new Date().toISOString().slice(0, 10))
const entries = ref([])
const balances = ref({})
const balanceCents = ref(0)
const loading = ref(true)
const saving = ref(false)
const feedback = ref('')
const feedbackType = ref('info')

function navigate(url) { window.location.assign(url) }
function show(message, type = 'info') { feedback.value = message; feedbackType.value = type }
function formatMoney(cents) {
	return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'EUR' }).format((Number(cents) || 0) / 100)
}
function formatDate(timestamp) {
	return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(timestamp * 1000))
}
function personName(uid) {
	return users.value.find((user) => user.uid === uid)?.displayName || uid
}

async function load() {
	loading.value = true
	try {
		if (props.mode === 'manage') {
			const { data } = await axios.get(urls.overview)
			users.value = data.users || []
			userOptions.value = users.value
			entries.value = data.entries || []
			balances.value = data.balances || {}
		} else {
			const { data } = await axios.get(urls.personal)
			entries.value = data.entries || []
			balanceCents.value = data.balanceCents || 0
		}
	} catch (error) {
		show(error.response?.data?.message || error.message, 'error')
	} finally {
		loading.value = false
	}
}

async function addEntry() {
	if (!selectedUser.value?.uid) {
		show(tr('Bitte wähle eine Person aus.'), 'error')
		return
	}
	const parsed = Number(String(amount.value).replace(',', '.'))
	if (!Number.isFinite(parsed) || parsed === 0) {
		show(tr('Bitte gib einen Betrag ungleich 0 ein.'), 'error')
		return
	}
	const date = new Date(bookedAt.value + 'T12:00:00')
	if (Number.isNaN(date.getTime())) {
		show(tr('Bitte gib ein gültiges Buchungsdatum ein.'), 'error')
		return
	}
	saving.value = true
	try {
		const body = new URLSearchParams({
			userId: selectedUser.value.uid,
			amountCents: String(Math.round(parsed * 100)),
			description: description.value,
			bookedAt: String(Math.floor(date.getTime() / 1000)),
		})
		const { data } = await axios.post(urls.addEntry, body)
		entries.value = data.entries || []
		balances.value = data.balances || {}
		amount.value = ''
		description.value = ''
		show(tr('Buchung gespeichert.'), 'success')
	} catch (error) {
		show(error.response?.data?.message || error.message, 'error')
	} finally {
		saving.value = false
	}
}

onMounted(load)
</script>
