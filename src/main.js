import { createApp } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import axios from '@nextcloud/axios'
import { t } from '@nextcloud/l10n'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcContent from '@nextcloud/vue/components/NcContent'

const root = document.getElementById('jugend-vue-root')
if (root) {
	createApp({
		components: {
			NcAppNavigation,
			NcAppNavigationItem,
			NcAppContent,
			NcButton,
			NcTextArea,
			NcNoteCard,
			NcModal,
			NcContent,
		},
		data() {
			let navigation = []
			try {
				navigation = JSON.parse(root.dataset.navigation || '[]')
			} catch (_) {}
			let urls = {}
			let strings = {}
			try { urls = loadState('jugend', 'urls') } catch (_) {}
			try { strings = loadState('jugend', 'strings') } catch (_) {}
			strings = {
				smsEstimate: 'SMS: ca. {count} Segmente',
				confirmSend: 'Nachricht jetzt senden?',
				sending: 'Nachricht wird versendet …',
				unknownError: 'Unbekannter Fehler beim Versand.',
				unknown: 'unbekannt',
				talkOk: 'Talk: erfolgreich',
				talkFailed: 'Talk: fehlgeschlagen ({error})',
				matrixSummary: 'Matrix: {sent}/{total} erfolgreich',
				smsSummary: 'SMS: {sent}/{total} erfolgreich',
				...strings,
			}
			return {
				navigation,
				message: '',
				sending: false,
				confirming: false,
				feedback: '',
				feedbackType: '',
				urls,
				strings,
			}
		},
		computed: {
			smsSegments() {
				const extended = '^{}\\\\[~]|€'
				let slots = 0
				for (const char of this.message) slots += extended.includes(char) ? 2 : 1
				return slots <= 160 ? 1 : Math.ceil(slots / 153)
			},
			smsEstimate() {
				return this.strings.smsEstimate.replace('{count}', String(this.smsSegments))
			},
		},
		methods: {
			navigate(url) {
				window.location.assign(url)
			},
			tr(text) {
				return t('jugend', text)
			},
			async send() {
				if (!this.message.trim()) return
				this.sending = true
				this.feedback = this.strings.sending
				this.feedbackType = ''
				try {
					const form = new URLSearchParams({ message: this.message })
					const { data: result } = await axios.post(this.urls.send, form)
					const talk = result.talk?.ok
						? this.strings.talkOk
						: this.strings.talkFailed.replace('{error}', result.talk?.error || this.strings.unknown)
					const matrix = this.strings.matrixSummary
						.replace('{sent}', String(result.matrix?.sent || 0))
						.replace('{total}', String(result.matrix?.total || 0))
					const sms = this.strings.smsSummary
						.replace('{sent}', String(result.sms?.sent || 0))
						.replace('{total}', String(result.sms?.total || 0))
					const matrixFailed = (result.matrix?.failed || []).map((item) => item.recipient + ': ' + item.error)
					const smsFailed = (result.sms?.failed || []).map((item) => item.recipient + ': ' + item.error)
					this.feedback = [talk, matrix, result.matrix?.error, ...matrixFailed, sms, result.sms?.error, ...smsFailed].filter(Boolean).join('\n')
					this.feedbackType = result.success ? 'success' : 'warning'
					if (result.success) this.message = ''
				} catch (error) {
					this.feedback = error.response?.data?.message || this.strings.unknownError
					this.feedbackType = 'error'
				} finally {
					this.sending = false
				}
			},
		},
		template: `
			<NcContent>
				<NcAppNavigation :aria-label="tr('App-Navigation')">
				<template #list>
					<NcAppNavigationItem v-for="item in navigation" :key="item.url" :name="item.label" :href="item.url" :active="item.active" @click.prevent="navigate(item.url)">
						<template #icon><span :class="[item.icon]" aria-hidden="true" /></template>
					</NcAppNavigationItem>
				</template>
				</NcAppNavigation>
				<NcAppContent :page-heading="tr('Jugend-Nachricht versenden')">
				<div class="jugend-page">
					<header class="jugend-page__header">
						<div><h1>{{ tr('Jugend-Nachricht versenden') }}</h1><p>{{ tr('Erstelle eine Nachricht und versende sie über Matrix, Nextcloud Talk und SMS.') }}</p></div>
					</header>
					<main class="jugend-main-layout">
						<section class="jugend-panel jugend-composer">
							<div class="jugend-panel__heading"><div><h2>{{ tr('Neue Nachricht') }}</h2><p>{{ tr('Die Nachricht wird an alle Matrix-Mitglieder der Jugendgruppe, den Talk-Raum und SMS-Opt-in-Empfänger gesendet.') }}</p></div></div>
							<form @submit.prevent="confirming = true">
								<label class="jugend-label" for="jugend-message">{{ tr('Nachrichtentext') }}</label>
								<NcTextArea id="jugend-message" v-model="message" :maxlength="5000" :rows="12" :label="tr('Nachrichtentext')" />
								<div class="jugend-editor-footer"><span class="jugend-muted">{{ smsEstimate }}</span><span class="jugend-muted">{{ message.length }} / 5000</span></div>
								<div class="jugend-form-actions"><NcButton type="submit" variant="primary" :disabled="!message.trim() || sending">{{ tr('Nachricht an alle senden') }}</NcButton></div>
							</form>
						</section>
						<aside class="jugend-panel"><h2>{{ tr('Versand') }}</h2><ul class="jugend-channel-list"><li><strong>Matrix</strong><small>{{ tr('Persönliche Direktnachricht an jedes Gruppenmitglied mit Matrix-Konto') }}</small></li><li><strong>Nextcloud Talk</strong><small>{{ tr('Nachricht im konfigurierten Gruppenraum') }}</small></li><li><strong>SMS</strong><small>{{ tr('Nur Empfänger mit aktivierter SMS-Option') }}</small></li></ul></aside>
					</main>
					<NcNoteCard v-if="feedback" :type="feedbackType || 'info'" class="jugend-result">{{ feedback }}</NcNoteCard>
					<NcModal v-if="confirming" :name="tr('Nachricht senden?')" @close="confirming = false">
						<div class="jugend-dialog__form"><h2>{{ tr('Nachricht senden?') }}</h2><p>{{ strings.confirmSend }}</p><div class="jugend-dialog__actions"><NcButton @click="confirming = false">{{ tr('Abbrechen') }}</NcButton><NcButton variant="primary" :disabled="sending" @click="confirming = false; send()">{{ tr('Jetzt senden') }}</NcButton></div></div>
					</NcModal>
				</div>
				</NcAppContent>
			</NcContent>
		`,
	}).mount(root)
}
