<template>
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
						<NcTextField v-model="bookedAt" type="text" :label="tr('Buchungsdatum')" placeholder="YYYY-MM-DD" />
						<div class="jugend-form-actions">
							<NcButton type="submit" variant="primary" :disabled="saving">{{ tr('Buchung speichern') }}</NcButton>
						</div>
					</form>
				</section>

				<section v-if="mode === 'manage'" class="jugend-panel">
					<div class="jugend-panel__heading">
						<div>
							<h2>{{ tr('Monatlicher Beitrag') }}</h2>
							<p>{{ tr('Fester Betrag:') }} {{ formatMoney(monthlyFeeCents) }} {{ tr('pro Monat') }}</p>
						</div>
					</div>
					<NcTextField v-model="membershipSearch" :label="tr('Personen durchsuchen')" :placeholder="tr('Name oder Benutzer-ID')" />
					<div class="jugend-accounting-member-list">
						<div v-for="user in filteredMembershipUsers" :key="user.uid" class="jugend-accounting-member-row">
							<span>{{ user.displayName }} <small>({{ user.uid }})</small></span>
							<input v-model="startMonths[user.uid]" type="month" :aria-label="tr('Zahlungspflicht ab')">
							<NcButton variant="secondary" @click="saveStart(user.uid)">{{ tr('Speichern') }}</NcButton>
						</div>
					</div>
				</section>

				<section v-if="mode === 'manage'" class="jugend-panel jugend-accounting-form">
					<h2>{{ tr('Ereignisbuchung') }}</h2>
					<form @submit.prevent="addEvent">
						<NcTextField v-model="eventName" :label="tr('Ereignisname')" :placeholder="tr('z. B. Sommerlager')" />
						<NcTextField v-model="eventAmount" type="number" step="0.01" label-outside :label="tr('Betrag je Person in Euro')" :placeholder="'25.00'" />
						<NcTextField v-model="eventDate" type="date" :label="tr('Ereignisdatum')" />
						<NcTextField v-model="eventSearch" :label="tr('Teilnehmer suchen')" :placeholder="tr('Name oder Benutzer-ID')" />
						<div class="jugend-accounting-event-users">
							<NcCheckboxRadioSwitch v-for="user in filteredEventUsers" :key="user.uid" v-model="selectedEventUsers" type="checkbox" :value="user.uid">
								{{ user.displayName }} ({{ user.uid }})
							</NcCheckboxRadioSwitch>
						</div>
						<div class="jugend-form-actions">
							<NcButton type="submit" variant="primary" :disabled="saving">{{ tr('Ereignis allen ausgewählten Personen buchen') }}</NcButton>
						</div>
					</form>
				</section>

				<section v-if="mode === 'manage'" class="jugend-panel jugend-accounting-reports">
					<h2>{{ tr('Berichte') }}</h2>
					<div class="jugend-form-actions">
						<NcButton variant="secondary" @click="downloadReport('excel')">{{ tr('Excel herunterladen') }}</NcButton>
						<NcButton variant="secondary" @click="downloadReport('pdf')">{{ tr('PDF herunterladen') }}</NcButton>
					</div>
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
					<template v-if="mode === 'personal'">
						<h2 class="jugend-accounting-entries-heading">{{ tr('Monatliche Beiträge') }}</h2>
						<p v-if="!monthlyEntries.length" class="jugend-muted">{{ tr('Keine monatlichen Beiträge vorhanden.') }}</p>
						<div v-else class="jugend-accounting-table">
							<div v-for="entry in monthlyEntries" :key="entry.id" class="jugend-accounting-entry">
								<span>{{ formatDate(entry.bookedAt) }}</span>
								<span>{{ entry.description }}</span>
								<strong>{{ formatMoney(entry.amountCents) }}</strong>
							</div>
						</div>
						<h2 class="jugend-accounting-entries-heading">{{ tr('Ereignisse') }}</h2>
						<p v-if="!eventEntries.length" class="jugend-muted">{{ tr('Keine Ereignisse vorhanden.') }}</p>
						<div v-else class="jugend-accounting-table">
							<div v-for="entry in eventEntries" :key="entry.id" class="jugend-accounting-entry">
								<span>{{ formatDate(entry.bookedAt) }}</span>
								<span><strong>{{ entry.eventName }}</strong><small v-if="entry.description">{{ entry.description }}</small></span>
								<strong>{{ formatMoney(entry.amountCents) }}</strong>
							</div>
						</div>
					</template>
					<h2 class="jugend-accounting-entries-heading">{{ tr('Sonstige Buchungen') }}</h2>
					<p v-if="loading" class="jugend-muted">{{ tr('Buchungen werden geladen …') }}</p>
					<p v-else-if="!personalOtherEntries.length" class="jugend-muted">{{ tr('Keine sonstigen Buchungen vorhanden.') }}</p>
					<div v-else class="jugend-accounting-table">
						<div v-for="entry in personalOtherEntries" :key="entry.id" class="jugend-accounting-entry">
							<span class="jugend-accounting-entry__date">{{ formatDate(entry.bookedAt) }}</span>
							<span v-if="mode === 'manage'" class="jugend-accounting-entry__person">{{ personName(entry.userId) }}</span>
							<span class="jugend-accounting-entry__description">{{ entry.description || tr('Bargeld-Buchung') }}</span>
							<strong class="jugend-accounting-entry__amount">{{ formatMoney(entry.amountCents) }}</strong>
						</div>
					</div>
				</section>
			</div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import axios from '@nextcloud/axios'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'

const props = defineProps({
	mode: { type: String, required: true },
})
const tr = (text) => t('jugend', text)
const urls = loadState('jugend', 'accountingUrls', {})
const users = ref([])
const userOptions = ref([])
const selectedUser = ref(null)
const amount = ref('')
const description = ref('')
const bookedAt = ref(new Date().toISOString().slice(0, 10))
const entries = ref([])
const balances = ref({})
const balanceCents = ref(0)
const monthlyFeeCents = ref(0)
const starts = ref({})
const startMonths = reactive({})
const membershipSearch = ref('')
const eventSearch = ref('')
const eventName = ref('')
const eventAmount = ref('')
const eventDate = ref(new Date().toISOString().slice(0, 10))
const selectedEventUsers = ref([])
const loading = ref(true)
const saving = ref(false)
const feedback = ref('')
const feedbackType = ref('info')

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

function matchesSearch(user, search) {
	const needle = search.trim().toLowerCase()
	return !needle || user.uid.toLowerCase().includes(needle) || user.displayName.toLowerCase().includes(needle)
}
const filteredMembershipUsers = computed(() => users.value.filter((user) => matchesSearch(user, membershipSearch.value)))
const filteredEventUsers = computed(() => users.value.filter((user) => matchesSearch(user, eventSearch.value)))
const monthlyEntries = computed(() => entries.value.filter((entry) => entry.entryType === 'monthly'))
const eventEntries = computed(() => entries.value.filter((entry) => entry.entryType === 'event'))
const personalOtherEntries = computed(() => props.mode === 'manage'
	? entries.value
	: entries.value.filter((entry) => entry.entryType !== 'monthly' && entry.entryType !== 'event'))

async function load() {
	loading.value = true
	try {
		if (props.mode === 'manage') {
			const { data } = await axios.get(urls.overview)
			users.value = data.users || []
			userOptions.value = users.value
			entries.value = data.entries || []
			balances.value = data.balances || {}
			monthlyFeeCents.value = data.monthlyFeeCents || 0
			starts.value = data.starts || {}
			users.value.forEach((user) => {
				startMonths[user.uid] = starts.value[user.uid] || ''
			})
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

async function saveStart(userId) {
	const month = startMonths[userId]
	if (!month) {
		show(tr('Bitte wähle den Monat der Zahlungspflicht.'), 'error')
		return
	}
	try {
		await axios.post(urls.setStart, new URLSearchParams({ userId, startMonth: month }))
		starts.value[userId] = month
		await load()
		show(tr('Zahlungspflicht gespeichert.'), 'success')
	} catch (error) {
		show(error.response?.data?.message || error.message, 'error')
	}
}

async function addEvent() {
	if (!eventName.value.trim()) {
		show(tr('Bitte gib einen Ereignisnamen ein.'), 'error')
		return
	}
	const parsed = Number(String(eventAmount.value).replace(',', '.'))
	if (!Number.isFinite(parsed) || parsed === 0) {
		show(tr('Bitte gib einen Betrag ungleich 0 je Person ein.'), 'error')
		return
	}
	if (!selectedEventUsers.value.length) {
		show(tr('Bitte wähle mindestens eine Person aus.'), 'error')
		return
	}
	const date = new Date(eventDate.value + 'T12:00:00')
	if (Number.isNaN(date.getTime())) {
		show(tr('Bitte gib ein gültiges Ereignisdatum ein.'), 'error')
		return
	}
	saving.value = true
	try {
		const allocations = selectedEventUsers.value.map((userId) => ({
			userId,
			amountCents: Math.round(parsed * 100),
		}))
		const body = new URLSearchParams({
			eventName: eventName.value,
			description: eventName.value,
			bookedAt: String(Math.floor(date.getTime() / 1000)),
			allocations: JSON.stringify(allocations),
		})
		const { data } = await axios.post(urls.addEvent, body)
		entries.value = data.entries || []
		balances.value = data.balances || {}
		eventName.value = ''
		eventAmount.value = ''
		selectedEventUsers.value = []
		show(tr('Ereignisbuchung gespeichert.'), 'success')
	} catch (error) {
		show(error.response?.data?.message || error.message, 'error')
	} finally {
		saving.value = false
	}
}

async function downloadReport(format) {
	window.location.assign(urls.report + '?format=' + encodeURIComponent(format))
}

onMounted(load)
</script>
