<template>
	<NcContent>
		<NcAppNavigation :aria-label="tr('App-Navigation')">
			<template #list>
				<NcAppNavigationItem v-for="item in navigation" :key="item.url" :name="item.label" :href="item.url" :active="item.active" @click.prevent="navigate(item.url)">
					<template #icon><span :class="[item.icon]" aria-hidden="true" /></template>
				</NcAppNavigationItem>
			</template>
		</NcAppNavigation>
		<NcAppContent :page-heading="tr('Organisationsverwaltung')">
			<div class="jugend-page jugend-organization">
				<header class="jugend-page__header">
					<div><h1>{{ tr('Organisationsverwaltung') }}</h1><p>{{ tr('Plane Organisationsperioden und stelle Arbeitsgruppen für den nächsten Zeitraum zusammen.') }}</p></div>
					<NcButton @click="navigate(urls.home)">{{ tr('Zur Nachrichtenübersicht') }}</NcButton>
				</header>
				<NcNoteCard v-if="feedback" :type="feedbackType">{{ feedback }}</NcNoteCard>
				<main class="jugend-organization-layout">
					<section class="jugend-panel">
						<h2>{{ tr('Organisationsperioden') }}</h2>
						<p class="jugend-muted">{{ tr('Aktive und geplante Organisationsperioden.') }}</p>
						<div class="jugend-period-list">
							<NcButton v-for="period in periods" :key="period.id" :variant="selectedPeriod?.id === period.id ? 'primary' : 'secondary'" wide @click="selectPeriod(period)">
								{{ period.title + ' · ' + period.status + ' · ' + dateLabel(period) }}
							</NcButton>
						</div>
						<section v-if="selectedPeriod?.status === 'draft'" class="jugend-rollover-preview">
							<h3>{{ tr('Organisationswechsel prüfen') }}</h3>
							<p class="jugend-muted">{{ previewStatus }}</p>
							<p v-for="error in previewErrors" :key="error" class="error">{{ error }}</p>
							<div class="jugend-form-actions">
								<NcButton variant="secondary" @click="loadPreview">{{ tr('Vorschau aktualisieren') }}</NcButton>
								<NcButton variant="secondary" :disabled="!previewValid" @click="prepareResources">{{ tr('Ressourcen vorbereiten / erneut versuchen') }}</NcButton>
								<NcButton variant="primary" :disabled="!activationReady" @click="confirmActivation = true">{{ tr('Periode aktivieren') }}</NcButton>
							</div>
							<NcModal v-if="confirmActivation" :name="tr('Periode aktivieren')" @close="confirmActivation = false">
								<div class="jugend-dialog__form">
									<h2>{{ tr('Periode aktivieren') }}</h2>
									<p>Organisationsperiode „{{ selectedPeriod.title }}“ jetzt aktivieren?</p>
									<div class="jugend-dialog__actions">
										<NcButton @click="confirmActivation = false">{{ tr('Abbrechen') }}</NcButton>
										<NcButton variant="primary" @click="activatePeriod">{{ tr('Periode aktivieren') }}</NcButton>
									</div>
								</div>
							</NcModal>
						</section>
						<section v-if="selectedPeriod?.status === 'active'" class="jugend-organization-form">
							<h3>{{ tr('Ressourcen der aktiven Periode') }}</h3>
							<div class="jugend-form-actions">
								<NcButton variant="primary" @click="provision('talk')">Talk-Räume bereitstellen / abgleichen</NcButton>
								<NcButton variant="secondary" @click="provision('groupfolders')">Dateiordner bereitstellen / abgleichen</NcButton>
								<NcButton variant="secondary" @click="provision('calendars')">Kalender bereitstellen / abgleichen</NcButton>
							</div>
							<NcNoteCard v-if="resourceResult" :type="resourceResultType">{{ resourceResult }}</NcNoteCard>
						</section>
						<form class="jugend-organization-form" @submit.prevent="createPeriod">
							<h3>{{ tr('Neue Periode planen') }}</h3>
							<div class="jugend-fields">
								<div class="jugend-field"><NcTextField v-model="periodForm.key" :label="tr('Schlüssel')" label-outside required maxlength="32" /></div>
								<div class="jugend-field"><NcTextField v-model="periodForm.title" :label="tr('Bezeichnung')" label-outside required maxlength="128" /></div>
								<div class="jugend-field"><NcTextField v-model="periodForm.startsOn" :label="tr('Beginn')" label-outside type="date" /></div>
								<div class="jugend-field"><NcTextField v-model="periodForm.endsOn" :label="tr('Ende')" label-outside type="date" /></div>
								<div class="jugend-field jugend-field--wide"><NcSelect v-model="periodTemplate" :options="periodTemplateOptions" label="title" :input-label="tr('Vorlage')" label-outside /></div>
							</div>
							<NcButton type="submit" variant="primary" :disabled="periodSaving">{{ tr('Entwurf erstellen') }}</NcButton>
						</form>
					</section>
					<section class="jugend-panel">
						<h2>{{ tr('Arbeitsgruppen') }}</h2>
						<p class="jugend-muted">{{ selectedPeriod ? selectedPeriod.title : tr('Wähle eine geplante Periode aus.') }}</p>
						<div class="jugend-group-list">
							<NcButton v-for="group in groups" :key="group.id" :variant="selectedGroup?.id === group.id ? 'primary' : 'secondary'" wide @click="selectGroup(group)">
								{{ group.name + ' · ' + group.members.length + ' Mitglieder' }}
							</NcButton>
						</div>
						<form v-if="selectedPeriod?.status === 'draft'" class="jugend-organization-form" @submit.prevent="createGroup">
							<h3>{{ tr('Arbeitsgruppe hinzufügen') }}</h3>
							<div class="jugend-fields">
								<div class="jugend-field"><NcTextField v-model="groupForm.slug" :label="tr('Technischer Schlüssel')" label-outside required maxlength="64" /></div>
								<div class="jugend-field"><NcTextField v-model="groupForm.name" :label="tr('Gruppenname')" label-outside required maxlength="128" /></div>
								<div class="jugend-field jugend-field--wide"><NcTextField v-model="groupForm.description" :label="tr('Beschreibung')" label-outside maxlength="500" /></div>
							</div>
							<NcButton type="submit" variant="primary">{{ tr('Gruppe anlegen') }}</NcButton>
						</form>
						<form v-if="selectedGroup && selectedPeriod?.status === 'draft'" class="jugend-organization-form" @submit.prevent="saveMemberships">
							<h3>{{ tr('Mitglieder und Gruppenleitung') }}: {{ selectedGroup.name }}</h3>
							<div class="jugend-membership-list">
								<div v-for="user in users" :key="user.uid" class="jugend-member-row">
									<NcCheckboxRadioSwitch v-model="membershipIds" type="checkbox" :value="user.uid">{{ user.displayName }} ({{ user.uid }})</NcCheckboxRadioSwitch>
									<NcCheckboxRadioSwitch v-model="leaderIds" type="checkbox" :value="user.uid" :disabled="!membershipIds.includes(user.uid)">Gruppenleitung</NcCheckboxRadioSwitch>
								</div>
							</div>
							<NcButton type="submit" variant="primary">{{ tr('Mitgliedschaften speichern') }}</NcButton>
						</form>
					</section>
				</main>
				<section v-if="manageAppAdmins" class="jugend-panel jugend-admin-section">
					<h2>{{ tr('App-Administratoren') }}</h2>
					<div v-for="admin in admins" :key="admin.user_id" class="jugend-member-row"><span>{{ admin.user_id }}</span><NcButton variant="secondary" @click="revokeAdmin(admin.user_id)">Entziehen</NcButton></div>
					<div class="jugend-organization-form">
						<NcSelect v-model="adminSelection" :options="adminUserOptions" label="label" :input-label="tr('Benutzer auswählen')" label-outside />
						<NcButton variant="primary" :disabled="!adminUserId" @click="grantAdmin">Berechtigung vergeben</NcButton>
					</div>
				</section>
				<section v-if="manageRoles" class="jugend-panel jugend-admin-section">
					<h2>{{ tr('Rollen und Berechtigungen') }}</h2>
					<div v-for="role in roles" :key="role.id" class="jugend-member-row">
						<span>{{ role.name }} · {{ role.role_key }} · {{ role.permissions.join(', ') }}</span>
						<NcButton v-if="!role.is_system" variant="secondary" @click="deleteRole(role)">Löschen</NcButton>
					</div>
					<div v-for="assignment in assignments" :key="assignment.id" class="jugend-member-row">
						<span>{{ assignment.user_id }} · {{ assignment.name }} · {{ assignmentScopeLabel(assignment) }}</span>
						<NcButton v-if="assignment.role_key !== 'app_admin'" variant="secondary" @click="revokeRole(assignment)">Entziehen</NcButton>
					</div>
					<form class="jugend-organization-form" @submit.prevent="saveRole">
						<div class="jugend-fields">
							<div class="jugend-field"><NcTextField v-model="roleForm.roleKey" label="Technischer Schlüssel" label-outside required /></div>
							<div class="jugend-field"><NcTextField v-model="roleForm.name" label="Rollenname" label-outside required /></div>
							<div class="jugend-field jugend-field--wide"><NcTextField v-model="roleForm.description" label="Beschreibung" label-outside /></div>
						</div>
						<fieldset class="jugend-role-permissions"><legend>Berechtigungen</legend><NcCheckboxRadioSwitch v-for="permission in permissions" :key="permission.permission_key" v-model="roleForm.permissionKeys" type="checkbox" :value="permission.permission_key">{{ permission.name }}</NcCheckboxRadioSwitch></fieldset>
						<NcButton type="submit" variant="primary">Rolle speichern</NcButton>
					</form>
					<form class="jugend-organization-form" @submit.prevent="assignRole">
						<h3>Rolle zuweisen</h3>
						<NcSelect v-model="assignmentUserSelection" :options="assignmentUserOptions" label="label" :input-label="tr('Person')" label-outside />
						<NcSelect v-model="assignmentRoleSelection" :options="assignmentRoleOptions" label="label" :input-label="tr('Rolle')" label-outside />
						<NcSelect v-model="assignmentScopeSelection" :options="assignmentScopeTypeOptions" label="label" :input-label="tr('Geltungsbereich')" label-outside />
						<NcSelect v-if="assignmentForm.scopeType !== 'global'" v-model="assignmentScopeIdSelection" :options="assignmentScopes" label="label" :input-label="tr('Geltungsbereich')" label-outside />
						<NcButton type="submit" variant="primary" :disabled="!assignmentForm.userId || !assignmentForm.roleId">Rolle zuweisen</NcButton>
					</form>
				</section>
			</div>
		</NcAppContent>
	</NcContent>
</template>

<script setup>
import { reactive, ref, onMounted, watch, computed } from 'vue'
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
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcModal from '@nextcloud/vue/components/NcModal'

const props = defineProps({ navigation: { type: Array, required: true } })
const tr = (text) => t('jugend', text)
const urls = loadState('jugend', 'organizationUrls')
const periods = ref([])
const groups = ref([])
const users = ref([])
const selectedPeriod = ref(null)
const selectedGroup = ref(null)
const membershipIds = ref([])
const leaderIds = ref([])
const feedback = ref('')
const feedbackType = ref('info')
const previewStatus = ref(tr('Die Vorschau prüft Gruppen, Mitgliedschaften und Gruppenleitungen.'))
const previewErrors = ref([])
const previewValid = ref(false)
const activationReady = ref(false)
const resourceResult = ref('')
const resourceResultType = ref('info')
const periodSaving = ref(false)
const periodForm = reactive({ key: '', title: '', startsOn: '', endsOn: '', sourcePeriodId: '' })
const groupForm = reactive({ slug: '', name: '', description: '' })
const manageAppAdmins = ref(urls.manageAppAdmins === true)
const manageRoles = ref(urls.manageRoles === true)
const admins = ref([])
const adminUserId = ref('')
const roles = ref([])
const permissions = ref([])
const roleForm = reactive({ roleId: 0, roleKey: '', name: '', description: '', permissionKeys: [] })
const assignments = ref([])
const assignmentForm = reactive({ userId: '', roleId: '', scopeType: 'global', scopeId: '' })
const assignmentScopes = ref([])
const confirmActivation = ref(false)
const periodTemplate = ref(null)
const adminSelection = ref(null)
const assignmentUserSelection = ref(null)
const assignmentRoleSelection = ref(null)
const assignmentScopeSelection = ref({ id: 'global', label: 'Global' })
const assignmentScopeIdSelection = ref(null)

const periodTemplateOptions = computed(() => [
	{ id: '', title: tr('Leere Periode erstellen') },
	...periods.value.map((period) => ({ id: period.id, title: period.title })),
])
const adminUserOptions = computed(() => users.value.map((user) => ({ id: user.uid, label: user.displayName + ' (' + user.uid + ')' })))
const assignmentUserOptions = computed(() => users.value.map((user) => ({ id: user.uid, label: user.displayName + ' (' + user.uid + ')' })))
const assignmentRoleOptions = computed(() => roles.value.map((role) => ({ id: role.id, label: role.name })))
const assignmentScopeTypeOptions = [
	{ id: 'global', label: 'Global' },
	{ id: 'period', label: 'Organisationsperiode' },
	{ id: 'group', label: 'Arbeitsgruppe' },
]

function navigate(url) { window.location.assign(url) }
function show(message, type = 'info') { feedback.value = message; feedbackType.value = type }
async function request(url, method = 'get', data = null) {
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
function dateLabel(period) {
	const start = period.starts_on ? new Date(period.starts_on).toLocaleDateString('de-DE') : ''
	const end = period.ends_on ? new Date(period.ends_on).toLocaleDateString('de-DE') : ''
	return start && end ? start + ' bis ' + end : start ? 'ab ' + start : end ? 'bis ' + end : 'ohne festgelegten Zeitraum'
}

async function loadPeriods() {
	const payload = await request(urls.periods)
	periods.value = payload.periods || []
}
async function selectPeriod(period) {
	selectedPeriod.value = period
	selectedGroup.value = null
	await loadGroups()
	if (period.status === 'draft') await loadPreview()
}
async function loadGroups() {
	if (!selectedPeriod.value) return
	groups.value = (await request(urls.groups + '?periodId=' + encodeURIComponent(selectedPeriod.value.id))).groups || []
}
async function selectGroup(group) {
	selectedGroup.value = group
	membershipIds.value = group.members.map((member) => member.user_id)
	leaderIds.value = group.members.filter((member) => member.is_leader).map((member) => member.user_id)
}

async function createPeriod() {
	periodSaving.value = true
	try {
		periodForm.sourcePeriodId = periodTemplate.value?.id || ''
		const endpoint = periodForm.sourcePeriodId ? urls.clonePeriod : urls.createPeriod
		const data = { ...periodForm }
		const payload = await request(endpoint, 'post', data)
		show('Organisationsperiode „' + payload.period.title + '“ wurde gespeichert.', 'success')
		periodForm.key = ''; periodForm.title = ''; periodForm.startsOn = ''; periodForm.endsOn = ''; periodForm.sourcePeriodId = ''
		await loadPeriods()
		await selectPeriod(payload.period)
	} catch (error) { show(error.response?.data?.message || error.message, 'error') }
	finally { periodSaving.value = false }
}
async function createGroup() {
	try {
		const payload = await request(urls.createGroup, 'post', { ...groupForm, periodId: selectedPeriod.value.id })
		show('Arbeitsgruppe „' + payload.group.name + '“ wurde angelegt.', 'success')
		groupForm.slug = ''; groupForm.name = ''; groupForm.description = ''
		await loadGroups()
	} catch (error) { show(error.response?.data?.message || error.message, 'error') }
}
async function saveMemberships() {
	if (leaderIds.value.length !== 2) { show(tr('Bitte markiere genau zwei Personen als Gruppenleitung.'), 'error'); return }
	try {
		const payload = await request(urls.setMembers, 'put', { groupId: selectedGroup.value.id, 'members[]': membershipIds.value, 'leaders[]': leaderIds.value })
		show('Mitgliedschaften für „' + selectedGroup.value.name + '“ gespeichert.', 'success')
		await loadGroups()
		if (payload.group) await selectGroup(payload.group)
	} catch (error) { show(error.response?.data?.message || error.message, 'error') }
}

async function loadPreview() {
	if (!selectedPeriod.value || selectedPeriod.value.status !== 'draft') return
	previewStatus.value = tr('Prüfe Organisationsperiode …')
	previewErrors.value = []
	try {
		const preview = await request(urls.previewPeriod + '?periodId=' + encodeURIComponent(selectedPeriod.value.id))
		previewValid.value = preview.valid
		activationReady.value = preview.activationReady
		previewErrors.value = preview.errors || []
		previewStatus.value = (preview.valid ? tr('Die Periode ist aktivierbar.') : tr('Die Periode enthält noch Fehler.')) + ' ' + preview.summary.groupCount + ' Arbeitsgruppen.'
	} catch (error) { previewStatus.value = error.response?.data?.message || error.message }
}
async function prepareResources() {
	try {
		const payload = await request(urls.preparePeriodResources, 'post', { periodId: selectedPeriod.value.id })
		resourceResult.value = (payload.result?.prepared || []).length ? 'Ressourcenvorbereitung abgeschlossen.' : 'Ressourcenvorbereitung geprüft.'
		resourceResultType.value = 'info'
		await loadPreview()
	} catch (error) { show(error.response?.data?.message || error.message, 'error') }
}
async function activatePeriod() {
	confirmActivation.value = false
	if (!activationReady.value) return
	try {
		const payload = await request(urls.activatePeriod, 'post', { periodId: selectedPeriod.value.id })
		show('„' + payload.period.title + '“ ist jetzt die aktive Organisationsperiode.', 'success')
		await loadPeriods()
		await selectPeriod(payload.period)
	} catch (error) { show(error.response?.data?.message || error.message, 'error') }
}
async function provision(channel) {
	const endpoint = channel === 'talk' ? urls.provisionTalkRooms : channel === 'groupfolders' ? urls.provisionGroupFolders : urls.provisionCalendars
	try {
		await request(endpoint, 'post', { periodId: selectedPeriod.value.id })
		resourceResult.value = 'Ressourcen wurden bereitgestellt und abgeglichen.'
		resourceResultType.value = 'success'
	} catch (error) {
		resourceResult.value = error.response?.data?.message || error.message
		resourceResultType.value = 'error'
	}
}

async function loadAppAdmins() {
	if (!manageAppAdmins.value) return
	admins.value = (await request(urls.appAdmins)).admins || []
}
async function grantAdmin() {
	adminUserId.value = adminSelection.value?.id || ''
	await request(urls.grantAppAdmin, 'post', { userId: adminUserId.value })
	adminUserId.value = ''
	await loadAppAdmins()
}
async function revokeAdmin(userId) {
	await request(urls.revokeAppAdmin, 'delete', { userId })
	await loadAppAdmins()
}
async function loadRoles() {
	if (!manageRoles.value) return
	const payload = await request(urls.roles)
	roles.value = payload.roles || []
	permissions.value = payload.permissions || []
	assignments.value = payload.assignments || []
}
function assignmentScopeLabel(assignment) {
	if (assignment.scope_type === 'global') return 'Global'
	return (assignment.scope_type === 'period' ? 'Periode #' : 'Gruppe #') + assignment.scope_id
}
async function saveRole() {
	await request(urls.saveRole, 'post', { roleId: roleForm.roleId, roleKey: roleForm.roleKey, name: roleForm.name, description: roleForm.description, permissionKeys: roleForm.permissionKeys })
	roleForm.roleKey = ''; roleForm.name = ''; roleForm.description = ''; roleForm.permissionKeys = []
	await loadRoles()
}
async function deleteRole(role) {
	await request(urls.deleteRole, 'delete', { roleId: role.id })
	await loadRoles()
}

watch(() => assignmentScopeSelection.value?.id || 'global', async (scopeType) => {
	assignmentForm.scopeType = scopeType
	assignmentScopes.value = []
	assignmentForm.scopeId = ''
	if (scopeType === 'global') return
	const periodPayload = await request(urls.periods)
	if (scopeType === 'period') {
		assignmentScopes.value = periodPayload.periods.map((period) => ({ id: period.id, label: period.title + ' (' + period.status + ')' }))
		return
	}
	for (const period of periodPayload.periods) {
		const groupPayload = await request(urls.groups + '?periodId=' + encodeURIComponent(period.id))
		for (const group of groupPayload.groups) {
			assignmentScopes.value.push({ id: group.id, label: period.title + ' — ' + group.name })
		}
	}
})

async function assignRole() {
	assignmentForm.userId = assignmentUserSelection.value?.id || ''
	assignmentForm.roleId = assignmentRoleSelection.value?.id || ''
	assignmentForm.scopeType = assignmentScopeSelection.value?.id || 'global'
	assignmentForm.scopeId = assignmentScopeIdSelection.value?.id || ''
	const data = { userId: assignmentForm.userId, roleId: assignmentForm.roleId, scopeType: assignmentForm.scopeType }
	if (assignmentForm.scopeType !== 'global') data.scopeId = assignmentForm.scopeId
	await request(urls.assignRole, 'post', data)
	show('Rollenzuweisung wurde gespeichert.', 'success')
	await loadRoles()
}
async function revokeRole(assignment) {
	await request(urls.revokeRole, 'delete', { assignmentId: assignment.id })
	show('Rollenzuweisung wurde entzogen.', 'success')
	await loadRoles()
}

onMounted(async () => {
	try {
		users.value = (await request(urls.users)).users || []
		await loadPeriods()
		await loadAppAdmins()
		await loadRoles()
	} catch (error) { show(error.response?.data?.message || error.message, 'error') }
})
</script>
