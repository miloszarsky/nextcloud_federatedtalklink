/**
 * SPDX-FileCopyrightText: 2024 Your Name <admin@example.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Talk Integration Script
 *
 * This script integrates with Nextcloud Talk to add a "Generate Federated Link"
 * button in conversation settings. It uses Talk's public JavaScript API if available.
 */

import { generateOcsUrl } from '@nextcloud/router'
import { showSuccess, showError, showInfo } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'

/**
 * Initialize the Talk integration
 */
function initTalkIntegration() {
    // Check if we're on a Talk page
    if (!document.getElementById('talk-container') && !window.location.pathname.includes('/call/')) {
        return
    }

    console.log('[FederatedTalkLink] Initializing Talk integration')

    // Add a button to generate federated links when conversation info is shown
    observeConversationSettings()
}

/**
 * Observe for conversation settings panel
 */
function observeConversationSettings() {
    // Create a MutationObserver to watch for the conversation settings panel
    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    // Look for conversation settings or info panels
                    const settingsPanel = node.querySelector?.('.conversation-settings')
                        || (node.classList?.contains('conversation-settings') ? node : null)

                    if (settingsPanel) {
                        injectFederatedLinkButton(settingsPanel)
                    }
                }
            }
        }
    })

    observer.observe(document.body, {
        childList: true,
        subtree: true,
    })
}

/**
 * Inject the federated link button into the conversation settings
 *
 * @param {HTMLElement} settingsPanel The settings panel element
 */
function injectFederatedLinkButton(settingsPanel) {
    // Check if button already exists
    if (settingsPanel.querySelector('.federated-link-button')) {
        return
    }

    // Try to get the conversation name from the panel
    const conversationNameElement = settingsPanel.querySelector('.conversation-name')
        || settingsPanel.querySelector('[data-conversation-name]')
        || settingsPanel.querySelector('.displayname')

    if (!conversationNameElement) {
        console.log('[FederatedTalkLink] Could not find conversation name element')
        return
    }

    const conversationName = conversationNameElement.textContent?.trim()
        || conversationNameElement.getAttribute('data-conversation-name')

    if (!conversationName) {
        return
    }

    // Create the button
    const button = document.createElement('button')
    button.className = 'federated-link-button button'
    button.innerHTML = `
        <span class="icon icon-link"></span>
        <span>Generate Federated Link</span>
    `
    button.style.cssText = 'margin: 10px 0; display: flex; align-items: center; gap: 8px;'

    button.addEventListener('click', async () => {
        await generateFederatedLink(conversationName)
    })

    // Find a suitable place to insert the button
    const actionsContainer = settingsPanel.querySelector('.conversation-actions')
        || settingsPanel.querySelector('.settings-actions')
        || settingsPanel

    actionsContainer.appendChild(button)
}

/**
 * Generate a federated link for the given conversation
 *
 * @param {string} conversationName The name of the conversation
 */
async function generateFederatedLink(conversationName) {
    showInfo(`Generating federated link for "${conversationName}"...`)

    try {
        const response = await axios.get(
            generateOcsUrl('/apps/federatedtalklink/api/v1/link'),
            {
                params: {
                    roomName: conversationName,
                },
            }
        )

        const data = response.data.ocs?.data
        if (data?.link) {
            // Copy to clipboard
            try {
                await navigator.clipboard.writeText(data.link)
                showSuccess(`Federated link copied to clipboard: ${data.link}`)
            } catch (clipboardError) {
                // Show in a dialog if clipboard fails
                showSuccess(`Federated link generated: ${data.link}`)
                prompt('Copy this link:', data.link)
            }
        } else {
            throw new Error('No link returned')
        }
    } catch (error) {
        const errorMessage = error.response?.data?.ocs?.data?.error
            || error.message
            || 'Failed to generate federated link'
        showError(errorMessage)
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTalkIntegration)
} else {
    initTalkIntegration()
}

export { initTalkIntegration, generateFederatedLink }
