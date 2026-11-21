<template>
	<div id="jugend-settings" class="section jugend-page jugend-page--embedded">
		<h2>Jugend</h2>
		<p class="settings-hint">{{ tr('Verwalte die Verbindungen und Zugangsdaten für den Nachrichtenversand.') }}</p>
		<form class="jugend-settings-form" novalidate @submit.prevent="save(true)">
			<section v-for="section in sections" :id="section.id" :key="section.id" class="jugend-settings-group">
				<h3>{{ section.title }}</h3>
				<p class="settings-hint">{{ section.hint }}</p>
				<div class="jugend-fields">
					<div v-for="field in section.fields" :key="field.name" class="jugend-field" :class="{ 'jugend-field--wide': field.wide }">
						<NcTextField
							:id="field.name"
							v-model="form[field.name]"
							:name="field.name"
							:type="field.type || 'text'"
							:label="field.label"
							label-outside
							:autocomplete="field.autocomplete"
							:placeholder="field.placeholder" />
						<p v-if="field.hint" class="settings-hint">{{ field.hint }}</p>
						<div v-if="field.secret" class="jugend-secret-controls">
							<span class="jugend-secret-status" :data-configured="configured[field.name] ? '1' : '0'">
								{{ removing[field.name] ? tr('Wird beim Speichern entfernt') : (configured[field.name] ? tr('Zugangsdaten sind gespeichert') : tr('Keine Zugangsdaten gespeichert')) }}
							</span>
							<NcButton type="button" variant="secondary" :disabled="!configured[field.name] && !removing[field.name]" @click="toggleRemove(field.name)">
								{{ removing[field.name] ? tr('Entfernen rückgängig') : tr('Zugangsdaten entfernen') }}
							</NcButton>
						</div>
					</div>
				</div>
				<div v-if="section.channel" class="jugend-section-actions">
					<NcButton type="button" :disabled="testing === section.channel" variant="secondary" @click="testConnection(section.channel)">
						{{ section.testLabel }}
					</NcButton>
					<span class="jugend-test-result" :class="testTypes[section.channel]" role="status" aria-live="polite">{{ testResults[section.channel] }}</span>
				</div>
				<div v-if="section.webhook" class="jugend-webhook">
					<p class="jugend-webhook-label"><strong>{{ tr('Talk-Webhook-URL') }}</strong></p>
					<div class="jugend-copy-row">
						<code>{{ webhookUrl }}</code>
						<NcButton type="button" variant="secondary" @click="copyWebhook">{{ tr('Kopieren') }}</NcButton>
					</div>
				</div>
			</section>
			<div class="jugend-settings-footer">
				<NcNoteCard v-if="feedback" :type="feedbackType">{{ feedback }}</NcNoteCard>
				<NcButton type="submit" variant="primary" :disabled="saving">{{ tr('Alle Einstellungen speichern') }}</NcButton>
			</div>
		</form>
	</div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import axios from '@nextcloud/axios'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'

const props = defineProps({
	values: { type: Object, required: true },
	configured: { type: Object, required: true },
	talkWebhookUrl: { type: String, required: true },
})
const tr = (text) => t('jugend', text)
const urls = loadState('jugend', 'urls', {})
const strings = loadState('jugend', 'strings', {})
const form = reactive({ ...props.values })
const configured = reactive({ ...props.configured })
const removing = reactive({})
const webhookUrl = props.talkWebhookUrl
const feedback = ref('')
const feedbackType = ref('info')
const saving = ref(false)
const testing = ref('')
const testResults = reactive({})
const testTypes = reactive({})

const secretNames = ['authentik_token', 'talk_bot_secret', 'sms_password', 'matrix_bot_token', 'provisioning_app_password']
secretNames.forEach((name) => { form[name] = ''; removing[name] = false })

const sections = [
	{ id: 'jugend-settings-accounting', title: 'Buchhaltung', hint: tr('Diese Nextcloud-Benutzer dürfen Bargeld-Buchungen verwalten. Mehrere Benutzer-IDs durch Kommas trennen.'), fields: [
		{ name: 'accounting_treasurers', label: tr('Buchhalter (Benutzer-IDs)'), wide: true, placeholder: 'buchhalter1, buchhalter2' },
		{ name: 'accounting_monthly_fee_cents', label: tr('Monatlicher Beitrag in Cent'), type: 'number', wide: false, placeholder: '1500' },
	] },
	{ id: 'jugend-settings-authentik', title: 'Authentik – Zugriff und Empfänger', hint: tr('Diese Gruppe bestimmt die zugelassenen Benutzer der Jugend-App und die Empfänger der Nachrichten.'), channel: 'authentik', testLabel: tr('Authentik-Verbindung testen'), fields: [
		{ name: 'authentik_url', label: tr('Authentik-URL'), type: 'url', placeholder: 'https://auth.example.org' },
		{ name: 'authentik_group_id', label: tr('Gruppen-ID') },
		{ name: 'authentik_token', label: tr('API-Token'), type: 'password', autocomplete: 'new-password', secret: true, wide: true },
	] },
	{ id: 'jugend-settings-talk', title: 'Nextcloud Talk', hint: tr('Gruppenraum und Bot-Anbindung'), channel: 'talk', testLabel: tr('Talk-Testnachricht senden'), webhook: true, fields: [
		{ name: 'talk_url', label: tr('Nextcloud-URL'), type: 'url', placeholder: 'https://cloud.example.org' },
		{ name: 'talk_room_token', label: tr('Talk-Raum-Token') },
		{ name: 'talk_bot_secret', label: tr('Talk-Bot-Secret'), type: 'password', autocomplete: 'new-password', secret: true, wide: true },
	] },
	{ id: 'jugend-settings-resources', title: 'Ressourcenverwaltung', hint: tr('Separates Dienstkonto zur automatischen Anlage von Organisationsressourcen.'), channel: 'provisioning', testLabel: tr('Dienstkonto-Verbindung testen'), fields: [
		{ name: 'provisioning_url', label: tr('Nextcloud-Basis-URL'), type: 'url', placeholder: 'https://cloud.example.org' },
		{ name: 'provisioning_user', label: tr('Dienstkonto-Benutzername'), autocomplete: 'username' },
		{ name: 'provisioning_group_id', label: tr('Dienstgruppe für Groupfolders') },
		{ name: 'provisioning_groupfolder_root', label: tr('Name des bestehenden Groupfolders') },
		{ name: 'department_group_id', label: tr('Nextcloud-Zugriffsgruppe der Abteilung') },
		{ name: 'provisioning_app_password', label: tr('App-Passwort'), type: 'password', autocomplete: 'new-password', secret: true, wide: true },
	] },
	{ id: 'jugend-settings-matrix', title: 'Matrix', hint: tr('Persönliche Direktnachrichten an alle Mitglieder der Jugendgruppe'), channel: 'matrix', testLabel: tr('Matrix-Verbindung testen'), fields: [
		{ name: 'matrix_homeserver', label: tr('Homeserver-URL'), type: 'url', placeholder: 'https://matrix.example.org' },
		{ name: 'matrix_server_name', label: tr('Matrix-Servername'), placeholder: 'matrix.example.org' },
		{ name: 'matrix_bot_user_id', label: tr('Bot-Benutzer-ID'), wide: true, placeholder: '@nachrichten:matrix.example.org' },
		{ name: 'matrix_bot_token', label: tr('Bot-Access-Token'), type: 'password', autocomplete: 'new-password', secret: true, wide: true },
	] },
	{ id: 'jugend-settings-sms', title: 'MikroTik SMS-Gateway', hint: tr('RouterOS REST API und LTE-Schnittstelle'), channel: 'sms', testLabel: tr('SMS-Gateway-Verbindung testen'), fields: [
		{ name: 'sms_gateway_url', label: tr('Gateway-URL'), type: 'url', placeholder: 'http://192.168.178.100' },
		{ name: 'sms_interface', label: tr('LTE-Interface') },
		{ name: 'sms_username', label: tr('RouterOS-Benutzername'), autocomplete: 'username' },
		{ name: 'sms_password', label: tr('RouterOS-Passwort'), type: 'password', autocomplete: 'new-password', secret: true },
	] },
]

function toggleRemove(name) {
	removing[name] = !removing[name]
}

async function save(showMessage) {
	saving.value = true
	if (showMessage) feedback.value = strings.saving
	const data = { ...form }
	secretNames.forEach((name) => { data['clear_' + name] = removing[name] ? '1' : '0' })
	try {
		const response = await axios.post(urls.save, new URLSearchParams(data))
		secretNames.forEach((name) => {
			form[name] = ''
			removing[name] = false
			configured[name] = response.data.configured?.[name] === true
		})
		if (showMessage) { feedback.value = strings.saved; feedbackType.value = 'success' }
		return true
	} catch (error) {
		feedback.value = error.response?.data?.message || strings.saveFailed
		feedbackType.value = 'error'
		return false
	} finally {
		saving.value = false
	}
}

async function testConnection(channel) {
	testing.value = channel
	testResults[channel] = strings.testing
	testTypes[channel] = ''
	try {
		await save(false)
		const response = await axios.post(urls.test, new URLSearchParams({ channel }))
		testResults[channel] = response.data.message || strings.testOk
		testTypes[channel] = 'success'
	} catch (error) {
		testResults[channel] = error.response?.data?.message || strings.testFailed
		testTypes[channel] = 'error'
	} finally {
		testing.value = ''
	}
}

async function copyWebhook() {
	try {
		await navigator.clipboard.writeText(webhookUrl)
		feedback.value = strings.copied
		feedbackType.value = 'success'
	} catch (_) {
		feedback.value = strings.copyFailed
		feedbackType.value = 'error'
	}
}
</script>
