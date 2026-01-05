<!--
  - SPDX-FileCopyrightText: 2024 Your Name <admin@example.com>
  - SPDX-License-Identifier: AGPL-3.0-or-later
  -->

<template>
	<div class="federated-talk-link-admin">
		<NcSettingsSection
			name="Federated Talk Link"
			description="Configure external Nextcloud Talk server connection for federated link generation.">
			<form @submit.prevent="saveSettings">
				<div class="settings-group">
					<label for="external-server-url">External Server URL (API Server)</label>
					<NcTextField
						id="external-server-url"
						v-model="settings.externalServerUrl"
						:disabled="saving"
						placeholder="ext.kara-uas.cz"
						label="External server hostname (without https://)" />
					<p class="hint">
						The hostname of the external Nextcloud server to query for rooms (e.g., ext.kara-uas.cz)
					</p>
				</div>

				<div class="settings-group">
					<label for="username">Username</label>
					<NcTextField
						id="username"
						v-model="settings.username"
						:disabled="saving"
						placeholder="guest"
						label="Authentication username" />
					<p class="hint">
						Username for authenticating with the external Talk API
					</p>
				</div>

				<div class="settings-group">
					<label for="password">Password</label>
					<NcPasswordField
						id="password"
						v-model="settings.password"
						:disabled="saving"
						:placeholder="settings.hasPassword ? '(unchanged)' : 'Enter password'"
						label="Authentication password" />
					<p class="hint">
						Password for authenticating with the external Talk API.
						Leave empty to keep the existing password.
					</p>
				</div>

				<div class="settings-group">
					<label for="target-url">Target Nextcloud URL (Link Destination)</label>
					<NcTextField
						id="target-url"
						v-model="settings.targetNextcloudUrl"
						:disabled="saving"
						placeholder="nextcloud.kara-uas.cz"
						label="Target Nextcloud hostname (without https://)" />
					<p class="hint">
						The hostname used in generated links (e.g., nextcloud.kara-uas.cz).
						Links will be formatted as https://[hostname]/call/[token]
					</p>
				</div>

				<div class="settings-actions">
					<NcButton
						type="primary"
						native-type="submit"
						:disabled="saving || !isValid">
						<template #icon>
							<Check v-if="!saving" :size="20" />
							<NcLoadingIcon v-else :size="20" />
						</template>
						{{ saving ? 'Saving...' : 'Save Settings' }}
					</NcButton>

					<NcButton
						type="secondary"
						:disabled="saving || !settings.isConfigured"
						@click="testConnection">
						<template #icon>
							<Connection v-if="!testing" :size="20" />
							<NcLoadingIcon v-else :size="20" />
						</template>
						{{ testing ? 'Testing...' : 'Test Connection' }}
					</NcButton>
				</div>
			</form>

			<div v-if="message" :class="['message', messageType]">
				{{ message }}
			</div>

			<div v-if="connectionResult" class="connection-result">
				<h4>Connection Test Result</h4>
				<p v-if="connectionResult.success" class="success">
					Connection successful! Found {{ connectionResult.roomCount }} room(s).
				</p>
				<p v-else class="error">
					Connection failed: {{ connectionResult.error }}
				</p>
			</div>
		</NcSettingsSection>

		<NcSettingsSection
			name="Quick Link Generator"
			description="Generate a federated link to test the configuration.">
			<div class="quick-generator">
				<div class="settings-group">
					<label for="room-name">Room Name</label>
					<NcTextField
						id="room-name"
						v-model="testRoomName"
						:disabled="!settings.isConfigured || generating"
						placeholder="Enter room name"
						label="Room name to search for" />
				</div>

				<NcButton
					type="primary"
					:disabled="!settings.isConfigured || !testRoomName || generating"
					@click="generateLink">
					<template #icon>
						<LinkVariant v-if="!generating" :size="20" />
						<NcLoadingIcon v-else :size="20" />
					</template>
					{{ generating ? 'Generating...' : 'Generate Link' }}
				</NcButton>

				<div v-if="generatedLink" class="generated-link">
					<h4>Generated Link</h4>
					<div class="link-container">
						<code>{{ generatedLink }}</code>
						<NcButton type="tertiary" @click="copyLink">
							<template #icon>
								<ContentCopy :size="20" />
							</template>
						</NcButton>
					</div>
				</div>

				<div v-if="generateError" class="error">
					{{ generateError }}
				</div>
			</div>
		</NcSettingsSection>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import NcSettingsSection from '@nextcloud/vue/dist/Components/NcSettingsSection.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'
import NcPasswordField from '@nextcloud/vue/dist/Components/NcPasswordField.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import Check from 'vue-material-design-icons/Check.vue'
import Connection from 'vue-material-design-icons/Connection.vue'
import LinkVariant from 'vue-material-design-icons/LinkVariant.vue'
import ContentCopy from 'vue-material-design-icons/ContentCopy.vue'

export default {
	name: 'AdminSettings',

	components: {
		NcSettingsSection,
		NcTextField,
		NcPasswordField,
		NcButton,
		NcLoadingIcon,
		Check,
		Connection,
		LinkVariant,
		ContentCopy,
	},

	data() {
		return {
			settings: {
				externalServerUrl: '',
				username: '',
				password: '',
				hasPassword: false,
				targetNextcloudUrl: '',
				isConfigured: false,
			},
			saving: false,
			testing: false,
			generating: false,
			message: '',
			messageType: '',
			connectionResult: null,
			testRoomName: '',
			generatedLink: '',
			generateError: '',
		}
	},

	computed: {
		isValid() {
			return this.settings.externalServerUrl
				&& this.settings.username
				&& this.settings.targetNextcloudUrl
				&& (this.settings.hasPassword || this.settings.password)
		},
	},

	created() {
		const initialSettings = loadState('federatedtalklink', 'admin-settings', {})
		this.settings = {
			...this.settings,
			...initialSettings,
			password: '',
		}
	},

	methods: {
		async saveSettings() {
			this.saving = true
			this.message = ''
			this.connectionResult = null

			try {
				const response = await axios.post(
					generateUrl('/apps/federatedtalklink/settings'),
					{
						externalServerUrl: this.settings.externalServerUrl,
						username: this.settings.username,
						password: this.settings.password || null,
						targetNextcloudUrl: this.settings.targetNextcloudUrl,
					}
				)

				if (response.data.success) {
					this.settings = {
						...this.settings,
						...response.data.settings,
						password: '',
					}
					this.message = 'Settings saved successfully!'
					this.messageType = 'success'
					showSuccess('Settings saved successfully!')
				} else {
					throw new Error(response.data.error || 'Unknown error')
				}
			} catch (error) {
				const errorMessage = error.response?.data?.error || error.message || 'Failed to save settings'
				this.message = errorMessage
				this.messageType = 'error'
				showError(errorMessage)
			} finally {
				this.saving = false
			}
		},

		async testConnection() {
			this.testing = true
			this.connectionResult = null

			try {
				const response = await axios.get(
					generateOcsUrl('/apps/federatedtalklink/api/v1/test')
				)

				this.connectionResult = {
					success: true,
					roomCount: response.data.ocs?.data?.roomCount || 0,
				}
			} catch (error) {
				this.connectionResult = {
					success: false,
					error: error.response?.data?.ocs?.data?.error || error.message || 'Connection failed',
				}
			} finally {
				this.testing = false
			}
		},

		async generateLink() {
			this.generating = true
			this.generatedLink = ''
			this.generateError = ''

			try {
				const response = await axios.get(
					generateOcsUrl('/apps/federatedtalklink/api/v1/link'),
					{
						params: {
							roomName: this.testRoomName,
						},
					}
				)

				this.generatedLink = response.data.ocs?.data?.link || ''
				if (!this.generatedLink) {
					throw new Error('No link returned')
				}
			} catch (error) {
				this.generateError = error.response?.data?.ocs?.data?.error || error.message || 'Failed to generate link'
			} finally {
				this.generating = false
			}
		},

		async copyLink() {
			try {
				await navigator.clipboard.writeText(this.generatedLink)
				showSuccess('Link copied to clipboard!')
			} catch (error) {
				showError('Failed to copy link')
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.federated-talk-link-admin {
	padding: 20px;

	.settings-group {
		margin-bottom: 20px;

		label {
			display: block;
			font-weight: 600;
			margin-bottom: 5px;
		}

		.hint {
			font-size: 12px;
			color: var(--color-text-maxcontrast);
			margin-top: 5px;
		}
	}

	.settings-actions {
		display: flex;
		gap: 10px;
		margin-top: 20px;
	}

	.message {
		margin-top: 15px;
		padding: 10px 15px;
		border-radius: var(--border-radius);

		&.success {
			background: var(--color-success);
			color: white;
		}

		&.error {
			background: var(--color-error);
			color: white;
		}
	}

	.connection-result {
		margin-top: 20px;
		padding: 15px;
		background: var(--color-background-dark);
		border-radius: var(--border-radius);

		h4 {
			margin: 0 0 10px 0;
		}

		.success {
			color: var(--color-success);
		}

		.error {
			color: var(--color-error);
		}
	}

	.quick-generator {
		.generated-link {
			margin-top: 20px;

			h4 {
				margin: 0 0 10px 0;
			}

			.link-container {
				display: flex;
				align-items: center;
				gap: 10px;
				background: var(--color-background-dark);
				padding: 10px 15px;
				border-radius: var(--border-radius);

				code {
					flex: 1;
					word-break: break-all;
				}
			}
		}

		.error {
			margin-top: 15px;
			color: var(--color-error);
		}
	}
}
</style>
