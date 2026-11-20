<template>
	<NcContent>
		<NcAppNavigation :aria-label="tr('App-Navigation')">
			<template #list>
				<NcAppNavigationItem v-for="item in navigation" :key="item.url" :name="item.label" :href="item.url" :active="item.active" @click.prevent="navigate(item.url)">
					<template #icon><span :class="[item.icon]" aria-hidden="true" /></template>
				</NcAppNavigationItem>
			</template>
		</NcAppNavigation>
		<NcAppContent :page-heading="tr('Meine Arbeitsgruppen')">
			<div class="jugend-page jugend-organization">
				<header class="jugend-page__header">
					<div><h1>{{ tr('Meine Arbeitsgruppen') }}</h1><p>{{ tr('Verwalte die Mitglieder und die beiden Gruppenleitungen deiner aktiven und geplanten Arbeitsgruppen.') }}</p></div>
					<NcButton @click="navigate(homeUrl)">{{ tr('Zurück') }}</NcButton>
				</header>
				<NcNoteCard v-if="feedback" :type="feedbackType">{{ feedback }}</NcNoteCard>
				<main class="jugend-organization-layout">
					<section class="jugend-panel">
						<h2>{{ tr('Arbeitsgruppen') }}</h2>
						<p class="jugend-muted">{{ tr('Aktive Arbeitsgruppen und gleichnamige Entwürfe werden angezeigt.') }}</p>
						<div class="jugend-group-list">
							<p v-if="loading" class="jugend-muted">{{ tr('Arbeitsgruppen werden geladen …') }}</p>
							<p v-else-if="!groups.length" class="jugend-muted">{{ tr('Du bist aktuell in keiner Arbeitsgruppe als Leitung eingetragen.') }}</p>
							<NcButton v-for="group in groups" :key="group.id" :variant="selectedGroup?.id === group.id ? 'primary' : 'secondary'" wide @click="selectGroup(group)">
								{{ (group.period_status === 'draft' ? 'Geplant' : 'Aktiv') + ' · ' + (group.period_title ? group.period_title + ' · ' : '') + group.name + ' · ' + group.members.length + ' Mitglieder' }}
							</NcButton>
						</div>
					</section>
					<section class="jugend-panel">
						<h2>{{ tr('Mitglieder verwalten') }}</h2>
						<p class="jugend-muted">{{ selectedLabel }}</p>
						<div v-if="selectedGroup && selectedGroup.period_status !== 'draft'" class="jugend-form-actions">
							<NcButton v-if="selectedGroup.resources?.talk" :href="selectedGroup.resources.talk" target="_blank" variant="secondary">Talk öffnen</NcButton>
							<NcButton v-if="selectedGroup.resources?.groupfolder" :href="selectedGroup.resources.groupfolder" target="_blank" variant="secondary">Dateiordner öffnen</NcButton>
							<NcButton v-if="selectedGroup.resources?.calendar" :href="selectedGroup.resources.calendar" target="_blank" variant="secondary">Kalender öffnen</NcButton>
						</div>
						<p v-if="outOfScope" class="error">{{ outOfScope }}</p>
						<form v-if="selectedGroup" class="jugend-organization-form" @submit.prevent="save">
							<p class="jugend-muted">{{ tr('Wähle die Mitglieder und markiere genau zwei Personen als Gruppenleitung.') }}</p>
							<div class="jugend-membership-list">
								<div v-for="user in users" :key="user.uid" class="jugend-member-row">
									<label class="jugend-member-label"><input v-model="selectedMembers" type="checkbox" :value="user.uid"> {{ user.displayName }} ({{ user.uid }})</label>
									<label class="jugend-leader-label"><input v-model="selectedLeaders" type="checkbox" :value="user.uid" :disabled="!selectedMembers.includes(user.uid)"> Gruppenleitung</label>
								</div>
							</div>
							<NcButton type="submit" variant="primary" :disabled="saving">{{ tr('Mitgliedschaften speichern') }}</NcButton>
						</form>
					</section>
				</main>
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

const props = defineProps({ navigation: { type: Array, required: true } })
const tr = (text) => t('jugend', text)
const urls = loadState('jugend', 'groupUrls')
const homeUrl = urls.home
const groups = ref([])
const users = ref([])
const selectedGroup = ref(null)
const selectedMembers = ref([])
const selectedLeaders = ref([])
const selectedLabel = ref(tr('Wähle links eine Arbeitsgruppe aus.'))
const outOfScope = ref('')
const feedback = ref('')
const feedbackType = ref('info')
const loading = ref(true)
const saving = ref(false)

function navigate(url) { window.location.assign(url) }
function show(message, type = 'info') { feedback.value = message; feedbackType.value = type }

async function request(url, method = 'get', data) {
	let body
	if (data) {
		body = new URLSearchParams()
		Object.entries(data).forEach(([key, value]) => {
			if (Array.isArray(value)) value.forEach((item) => body.append(key, item))
			else if (value !== undefined && value !== null) body.set(key, String(value))
		})
	}
	const response = await axios({ url, method, data: body })
	return response.data
}

async function load() {
	loading.value = true
	try {
		groups.value = (await request(urls.groups)).groups || []
	} catch (error) {
		show(error.response?.data?.message || error.message, 'error')
	} finally {
		loading.value = false
	}
}

async function selectGroup(group) {
	selectedGroup.value = group
	selectedLabel.value = (group.period_title ? group.period_title + ' · ' : '') + group.name
	try {
		users.value = (await request(urls.users + '?groupId=' + encodeURIComponent(group.id))).users || []
		selectedMembers.value = group.members.map((member) => member.user_id)
		selectedLeaders.value = group.members.filter((member) => member.is_leader === true || member.is_leader === 1 || member.is_leader === '1').map((member) => member.user_id)
		const available = new Set(users.value.map((user) => user.uid))
		const unavailable = selectedMembers.value.filter((uid) => !available.has(uid))
		outOfScope.value = unavailable.length ? tr('Nicht mehr in der aktuellen App-Zugriffsgruppe:') + ' ' + unavailable.join(', ') : ''
	} catch (error) {
		show(error.response?.data?.message || error.message, 'error')
	}
}

async function save() {
	if (!selectedGroup.value) return
	if (selectedLeaders.value.length !== 2) {
		show(tr('Bitte markiere genau zwei Personen als Gruppenleitung.'), 'error')
		return
	}
	const data = { groupId: selectedGroup.value.id }
	selectedMembers.value.forEach((uid) => data['members[]'] ? data['members[]'].push(uid) : (data['members[]'] = [uid]))
	selectedLeaders.value.forEach((uid) => data['leaders[]'] ? data['leaders[]'].push(uid) : (data['leaders[]'] = [uid]))
	saving.value = true
	try {
		const payload = await request(urls.setMembers, 'put', data)
		show(payload.message || tr('Mitgliedschaften gespeichert.'), 'success')
		await load()
		const refreshed = groups.value.find((group) => Number(group.id) === Number(selectedGroup.value.id))
		if (refreshed) await selectGroup(refreshed)
	} catch (error) {
		show(error.response?.data?.message || error.message, 'error')
	} finally {
		saving.value = false
	}
}

onMounted(load)
</script>
