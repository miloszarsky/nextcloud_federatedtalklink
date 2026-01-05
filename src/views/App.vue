<!--
  - SPDX-FileCopyrightText: 2024 Your Name <admin@example.com>
  - SPDX-License-Identifier: AGPL-3.0-or-later
  -->

<template>
	<NcContent app-name="federatedtalklink">
		<NcAppContent>
			<div class="federated-talk-link">
				<div class="federated-talk-link__header">
					<h1>Federated Talk Link Generator</h1>
					<p class="description">
						Generate direct links to Talk rooms on external Nextcloud servers.
					</p>
				</div>

				<div v-if="!isConfigured" class="federated-talk-link__not-configured">
					<NcEmptyContent
						name="Not Configured"
						description="Please ask an administrator to configure the Federated Talk Link settings.">
						<template #icon>
							<AlertCircle :size="64" />
						</template>
					</NcEmptyContent>
				</div>

				<div v-else class="federated-talk-link__content">
					<LinkGenerator />
				</div>
			</div>
		</NcAppContent>
	</NcContent>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import AlertCircle from 'vue-material-design-icons/AlertCircle.vue'
import LinkGenerator from '../components/LinkGenerator.vue'

export default {
	name: 'App',

	components: {
		NcContent,
		NcAppContent,
		NcEmptyContent,
		AlertCircle,
		LinkGenerator,
	},

	data() {
		return {
			isConfigured: false,
		}
	},

	created() {
		const config = loadState('federatedtalklink', 'app-config', {})
		this.isConfigured = config.isConfigured || false
	},
}
</script>

<style lang="scss" scoped>
.federated-talk-link {
	padding: 20px;
	max-width: 800px;
	margin: 0 auto;

	&__header {
		margin-bottom: 30px;
		text-align: center;

		h1 {
			font-size: 24px;
			font-weight: 600;
			margin-bottom: 10px;
		}

		.description {
			color: var(--color-text-maxcontrast);
			font-size: 14px;
		}
	}

	&__not-configured {
		margin-top: 50px;
	}

	&__content {
		background: var(--color-main-background);
		border-radius: var(--border-radius-large);
		padding: 20px;
	}
}
</style>
