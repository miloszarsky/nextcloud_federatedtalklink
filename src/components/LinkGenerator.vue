<!--
  - SPDX-FileCopyrightText: 2024 Your Name <admin@example.com>
  - SPDX-License-Identifier: AGPL-3.0-or-later
  -->

<template>
	<div class="link-generator">
		<div class="link-generator__input">
			<NcTextField
				v-model="roomName"
				:disabled="loading"
				placeholder="Enter room name..."
				label="Room name"
				@keyup.enter="generateLink" />
			<NcButton
				type="primary"
				:disabled="!roomName || loading"
				@click="generateLink">
				<template #icon>
					<LinkVariant v-if="!loading" :size="20" />
					<NcLoadingIcon v-else :size="20" />
				</template>
				{{ loading ? 'Searching...' : 'Generate Link' }}
			</NcButton>
		</div>

		<div v-if="error" class="link-generator__error">
			<NcNoteCard type="error">
				{{ error }}
			</NcNoteCard>
		</div>

		<div v-if="result" class="link-generator__result">
			<NcNoteCard type="success">
				<h4>Link Generated Successfully!</h4>
				<div class="room-info" v-if="result.roomInfo">
					<strong>Room:</strong> {{ result.roomInfo.name }}
				</div>
			</NcNoteCard>

			<div class="link-display">
				<label>Generated Link:</label>
				<div class="link-container">
					<input
						type="text"
						:value="result.link"
						readonly
						class="link-input"
						@click="selectLink" />
					<NcButton type="secondary" @click="copyLink">
						<template #icon>
							<ContentCopy :size="20" />
						</template>
						Copy
					</NcButton>
					<NcButton type="secondary" @click="openLink">
						<template #icon>
							<OpenInNew :size="20" />
						</template>
						Open
					</NcButton>
				</div>
			</div>

			<div class="email-send">
				<label>Send Link by Email:</label>
				<div class="email-container">
					<NcTextField
						v-model="emailAddress"
						:disabled="sendingEmail"
						placeholder="Enter email address..."
						type="email"
						@keyup.enter="sendEmail" />
					<NcButton
						type="primary"
						:disabled="!emailAddress || sendingEmail"
						@click="sendEmail">
						<template #icon>
							<EmailOutline v-if="!sendingEmail" :size="20" />
							<NcLoadingIcon v-else :size="20" />
						</template>
						{{ sendingEmail ? 'Sending...' : 'Send Email' }}
					</NcButton>
				</div>
			</div>
		</div>

		<div class="link-generator__rooms">
			<h3>Available Rooms</h3>
			<NcButton
				type="tertiary"
				:disabled="loadingRooms"
				@click="loadRooms">
				<template #icon>
					<Refresh v-if="!loadingRooms" :size="20" />
					<NcLoadingIcon v-else :size="20" />
				</template>
				{{ loadingRooms ? 'Loading...' : 'Refresh Rooms' }}
			</NcButton>

			<div v-if="rooms.length > 0" class="rooms-list">
				<div
					v-for="room in rooms"
					:key="room.token"
					class="room-item"
					@click="selectRoom(room)">
					<span class="room-name">{{ room.name }}</span>
					<span class="room-token">{{ room.token }}</span>
				</div>
			</div>

			<div v-else-if="roomsLoaded" class="no-rooms">
				<NcEmptyContent
					name="No rooms found"
					description="No rooms are available on the external server.">
					<template #icon>
						<ForumOutline :size="32" />
					</template>
				</NcEmptyContent>
			</div>
		</div>
	</div>
</template>

<script>
import { generateOcsUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import LinkVariant from 'vue-material-design-icons/LinkVariant.vue'
import ContentCopy from 'vue-material-design-icons/ContentCopy.vue'
import OpenInNew from 'vue-material-design-icons/OpenInNew.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'
import ForumOutline from 'vue-material-design-icons/ForumOutline.vue'
import EmailOutline from 'vue-material-design-icons/EmailOutline.vue'

export default {
	name: 'LinkGenerator',

	components: {
		NcTextField,
		NcButton,
		NcLoadingIcon,
		NcNoteCard,
		NcEmptyContent,
		LinkVariant,
		ContentCopy,
		OpenInNew,
		Refresh,
		ForumOutline,
		EmailOutline,
	},

	data() {
		return {
			roomName: '',
			loading: false,
			loadingRooms: false,
			roomsLoaded: false,
			error: '',
			result: null,
			rooms: [],
			emailAddress: '',
			sendingEmail: false,
		}
	},

	mounted() {
		this.loadRooms()
	},

	methods: {
		async generateLink() {
			if (!this.roomName) {
				return
			}

			this.loading = true
			this.error = ''
			this.result = null

			try {
				const response = await axios.get(
					generateOcsUrl('/apps/federatedtalklink/api/v1/link'),
					{
						params: {
							roomName: this.roomName,
						},
					}
				)

				const data = response.data.ocs?.data
				if (data?.link) {
					this.result = data
					showSuccess('Link generated successfully!')
				} else {
					throw new Error('No link returned')
				}
			} catch (error) {
				this.error = error.response?.data?.ocs?.data?.error
					|| error.message
					|| 'Failed to generate link'
				showError(this.error)
			} finally {
				this.loading = false
			}
		},

		async loadRooms() {
			this.loadingRooms = true

			try {
				const response = await axios.get(
					generateOcsUrl('/apps/federatedtalklink/api/v1/rooms')
				)

				this.rooms = response.data.ocs?.data?.rooms || []
				this.roomsLoaded = true
			} catch (error) {
				showError('Failed to load rooms')
				this.rooms = []
			} finally {
				this.loadingRooms = false
			}
		},

		selectRoom(room) {
			this.roomName = room.name
			this.generateLink()
		},

		selectLink(event) {
			event.target.select()
		},

		async copyLink() {
			if (!this.result?.link) {
				return
			}

			try {
				await navigator.clipboard.writeText(this.result.link)
				showSuccess('Link copied to clipboard!')
			} catch (error) {
				showError('Failed to copy link')
			}
		},

		openLink() {
			if (!this.result?.link) {
				return
			}

			window.open(this.result.link, '_blank')
		},

		async sendEmail() {
			if (!this.emailAddress || !this.result?.link) {
				return
			}

			this.sendingEmail = true

			try {
				const response = await axios.post(
					generateOcsUrl('/apps/federatedtalklink/api/v1/email'),
					{
						email: this.emailAddress,
						link: this.result.link,
						roomName: this.result.roomInfo?.name || '',
					}
				)

				const data = response.data.ocs?.data
				if (data?.message) {
					showSuccess(data.message)
					this.emailAddress = ''
				} else {
					showSuccess('Email sent successfully!')
					this.emailAddress = ''
				}
			} catch (error) {
				const errorMessage = error.response?.data?.ocs?.data?.error
					|| error.message
					|| 'Failed to send email'
				showError(errorMessage)
			} finally {
				this.sendingEmail = false
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.link-generator {
	&__input {
		display: flex;
		gap: 10px;
		align-items: flex-end;
		margin-bottom: 20px;

		> :first-child {
			flex: 1;
		}
	}

	&__error {
		margin-bottom: 20px;
	}

	&__result {
		margin-bottom: 30px;

		h4 {
			margin: 0;
		}

		.room-info {
			margin-top: 5px;
		}

		.link-display {
			margin-top: 15px;

			label {
				display: block;
				font-weight: 600;
				margin-bottom: 5px;
			}

			.link-container {
				display: flex;
				gap: 10px;
				align-items: center;

				.link-input {
					flex: 1;
					padding: 10px;
					border: 1px solid var(--color-border);
					border-radius: var(--border-radius);
					background: var(--color-background-dark);
					font-family: monospace;
					font-size: 13px;
				}
			}
		}

		.email-send {
			margin-top: 20px;
			padding-top: 20px;
			border-top: 1px solid var(--color-border);

			label {
				display: block;
				font-weight: 600;
				margin-bottom: 5px;
			}

			.email-container {
				display: flex;
				gap: 10px;
				align-items: flex-end;

				> :first-child {
					flex: 1;
				}
			}
		}
	}

	&__rooms {
		h3 {
			margin-bottom: 10px;
		}

		.rooms-list {
			margin-top: 15px;
			border: 1px solid var(--color-border);
			border-radius: var(--border-radius);
			max-height: 300px;
			overflow-y: auto;

			.room-item {
				display: flex;
				justify-content: space-between;
				padding: 12px 15px;
				border-bottom: 1px solid var(--color-border);
				cursor: pointer;
				transition: background-color 0.2s;

				&:last-child {
					border-bottom: none;
				}

				&:hover {
					background: var(--color-background-hover);
				}

				.room-name {
					font-weight: 500;
				}

				.room-token {
					color: var(--color-text-maxcontrast);
					font-family: monospace;
					font-size: 12px;
				}
			}
		}

		.no-rooms {
			margin-top: 20px;
		}
	}
}
</style>
