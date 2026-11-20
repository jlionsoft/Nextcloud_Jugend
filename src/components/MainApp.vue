<template>
	<NcContent app-name="jugend">
		<NcAppNavigation :aria-label="tr('App-Navigation')">
			<template #list>
				<NcAppNavigationItem
					v-for="item in navigation"
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
		<NcAppContent :page-heading="tr('Jugend-Nachricht versenden')">
			<div class="jugend-page">
				<header class="jugend-page__header">
					<div>
						<h1>{{ tr('Jugend-Nachricht versenden') }}</h1>
						<p>{{ tr('Erstelle eine Nachricht und versende sie über Matrix, Nextcloud Talk und SMS.') }}</p>
					</div>
				</header>
				<main class="jugend-main-layout">
					<section class="jugend-panel jugend-composer">
						<div class="jugend-panel__heading">
							<div>
								<h2>{{ tr('Neue Nachricht') }}</h2>
								<p>{{ tr('Die Nachricht wird an alle Matrix-Mitglieder der Jugendgruppe, den Talk-Raum und SMS-Opt-in-Empfänger gesendet.') }}</p>
							</div>
						</div>
						<form @submit.prevent="confirming = true">
							<NcTextArea
								v-model="message"
								:maxlength="5000"
								:rows="12"
								:label="tr('Nachrichtentext')" />
							<div class="jugend-editor-footer">
								<span class="jugend-muted">{{ smsEstimate }}</span>
								<span class="jugend-muted">{{ message.length }} / 5000</span>
							</div>
							<div class="jugend-form-actions">
								<NcButton type="submit" variant="primary" :disabled="!message.trim() || sending">
									{{ tr('Nachricht an alle senden') }}
								</NcButton>
							</div>
						</form>
					</section>
					<aside class="jugend-panel">
						<h2>{{ tr('Versand') }}</h2>
						<ul class="jugend-channel-list">
							<li><strong>Matrix</strong><small>{{ tr('Persönliche Direktnachricht an jedes Gruppenmitglied mit Matrix-Konto') }}</small></li>
							<li><strong>Nextcloud Talk</strong><small>{{ tr('Nachricht im konfigurierten Gruppenraum') }}</small></li>
							<li><strong>SMS</strong><small>{{ tr('Nur Empfänger mit aktivierter SMS-Option') }}</small></li>
						</ul>
					</aside>
				</main>
				<NcNoteCard v-if="feedback" :type="feedbackType || 'info'" class="jugend-result">
					{{ feedback }}
				</NcNoteCard>
				<NcModal v-if="confirming" :name="tr('Nachricht senden?')" @close="confirming = false">
					<div class="jugend-dialog__form">
						<h2>{{ tr('Nachricht senden?') }}</h2>
						<p>{{ strings.confirmSend }}</p>
						<div class="jugend-dialog__actions">
							<NcButton @click="confirming = false">{{ tr('Abbrechen') }}</NcButton>
							<NcButton variant="primary" :disabled="sending" @click="confirmAndSend">{{ tr('Jetzt senden') }}</NcButton>
						</div>
					</div>
				</NcModal>
			</div>
		</NcAppContent>
	</NcContent>
</template>

<script setup>
import { computed, ref } from 'vue'
import axios from '@nextcloud/axios'
import { t } from '@nextcloud/l10n'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcModal from '@nextcloud/vue/components/NcModal'

const props = defineProps({
	navigation: { type: Array, required: true },
	urls: { type: Object, required: true },
	strings: { type: Object, required: true },
})
const tr = (text) => t('jugend', text)
const message = ref('')
const sending = ref(false)
const confirming = ref(false)
const feedback = ref('')
const feedbackType = ref('')

const smsSegments = computed(() => {
	const extended = '^{}\\\\[~]|€'
	let slots = 0
	for (const char of message.value) slots += extended.includes(char) ? 2 : 1
	return slots <= 160 ? 1 : Math.ceil(slots / 153)
})
const smsEstimate = computed(() => props.strings.smsEstimate.replace('{count}', String(smsSegments.value)))

function navigate(url) {
	window.location.assign(url)
}

async function confirmAndSend() {
	confirming.value = false
	if (!message.value.trim()) return
	sending.value = true
	feedback.value = props.strings.sending
	feedbackType.value = ''
	try {
		const { data: result } = await axios.post(props.urls.send, new URLSearchParams({ message: message.value }))
		const talk = result.talk?.ok
			? props.strings.talkOk
			: props.strings.talkFailed.replace('{error}', result.talk?.error || props.strings.unknown)
		const matrix = props.strings.matrixSummary
			.replace('{sent}', String(result.matrix?.sent || 0))
			.replace('{total}', String(result.matrix?.total || 0))
		const sms = props.strings.smsSummary
			.replace('{sent}', String(result.sms?.sent || 0))
			.replace('{total}', String(result.sms?.total || 0))
		const matrixFailed = (result.matrix?.failed || []).map((item) => item.recipient + ': ' + item.error)
		const smsFailed = (result.sms?.failed || []).map((item) => item.recipient + ': ' + item.error)
		feedback.value = [talk, matrix, result.matrix?.error, ...matrixFailed, sms, result.sms?.error, ...smsFailed].filter(Boolean).join('\n')
		feedbackType.value = result.success ? 'success' : 'warning'
		if (result.success) message.value = ''
	} catch (error) {
		feedback.value = error.response?.data?.message || props.strings.unknownError
		feedbackType.value = 'error'
	} finally {
		sending.value = false
	}
}
</script>
