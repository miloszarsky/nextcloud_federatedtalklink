/**
 * SPDX-FileCopyrightText: 2024 Your Name <admin@example.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Talk Integration Script
 *
 * Adds a "Federated Link" button to the Talk top bar
 */

import { generateOcsUrl } from '@nextcloud/router'
import { showSuccess, showError, showInfo } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'

let currentConversationToken = null
let currentConversationName = null
let buttonAdded = false

/**
 * Initialize the Talk integration
 */
function initTalkIntegration() {
    console.log('[FederatedTalkLink] Initializing Talk integration')

    // Watch for URL changes to detect conversation switches
    observeUrlChanges()

    // Watch for DOM changes to inject button
    observeDomChanges()

    // Initial check
    checkAndAddButton()
}

/**
 * Observe URL changes (Talk uses hash-based routing)
 */
function observeUrlChanges() {
    // Check periodically for conversation changes
    setInterval(() => {
        const match = window.location.pathname.match(/\/call\/([a-zA-Z0-9]+)/)
            || window.location.hash.match(/\/call\/([a-zA-Z0-9]+)/)

        const token = match ? match[1] : null

        if (token !== currentConversationToken) {
            currentConversationToken = token
            buttonAdded = false
            if (token) {
                fetchConversationName(token)
            }
        }

        checkAndAddButton()
    }, 1000)
}

/**
 * Observe DOM changes
 */
function observeDomChanges() {
    const observer = new MutationObserver(() => {
        checkAndAddButton()
    })

    observer.observe(document.body, {
        childList: true,
        subtree: true,
    })
}

/**
 * Fetch conversation name from token
 */
async function fetchConversationName(token) {
    try {
        const response = await axios.get(
            generateOcsUrl(`/apps/spreed/api/v4/room/${token}`),
            { headers: { 'OCS-APIRequest': 'true' } }
        )
        currentConversationName = response.data.ocs?.data?.displayName
            || response.data.ocs?.data?.name
            || null
        console.log('[FederatedTalkLink] Conversation name:', currentConversationName)
    } catch (error) {
        console.log('[FederatedTalkLink] Could not fetch conversation name:', error)
        currentConversationName = null
    }
}

/**
 * Check and add button to top bar
 */
function checkAndAddButton() {
    if (buttonAdded) return

    // Look for Talk top bar / header areas
    const topBar = document.querySelector('.top-bar__wrapper')
        || document.querySelector('.conversation-header')
        || document.querySelector('.top-bar')
        || document.querySelector('#call-container .top-bar')
        || document.querySelector('.talk-sidebar-callview')
        || document.querySelector('[class*="top-bar"]')

    if (!topBar) return

    // Check if button already exists
    if (document.querySelector('.federated-link-topbar-button')) {
        buttonAdded = true
        return
    }

    // Find the actions area or create insertion point
    const actionsArea = topBar.querySelector('.top-bar__buttons')
        || topBar.querySelector('.conversation-header__actions')
        || topBar.querySelector('.actions')
        || topBar.querySelector('[class*="actions"]')
        || topBar

    // Create the button
    const button = createFederatedLinkButton()

    // Insert at the beginning of actions
    if (actionsArea.firstChild) {
        actionsArea.insertBefore(button, actionsArea.firstChild)
    } else {
        actionsArea.appendChild(button)
    }

    buttonAdded = true
    console.log('[FederatedTalkLink] Button added to top bar')
}

/**
 * Create the federated link button
 */
function createFederatedLinkButton() {
    const button = document.createElement('button')
    button.className = 'federated-link-topbar-button'
    button.title = 'Generate Federated Link'
    button.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
        </svg>
        <span class="federated-link-label">Federated Link</span>
    `

    button.style.cssText = `
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        margin: 4px;
        border: none;
        border-radius: 20px;
        background: var(--color-primary, #0082c9);
        color: var(--color-primary-text, white);
        cursor: pointer;
        font-size: 13px;
        font-weight: 500;
        transition: background 0.2s;
    `

    button.addEventListener('mouseenter', () => {
        button.style.background = 'var(--color-primary-hover, #006aa3)'
    })

    button.addEventListener('mouseleave', () => {
        button.style.background = 'var(--color-primary, #0082c9)'
    })

    button.addEventListener('click', handleButtonClick)

    return button
}

/**
 * Handle button click
 */
async function handleButtonClick() {
    // Try to get conversation name from various sources
    let roomName = currentConversationName

    if (!roomName) {
        // Try to get from DOM
        const nameEl = document.querySelector('.conversation-name')
            || document.querySelector('.talk-sidebar-callview__displayname')
            || document.querySelector('[class*="conversation"] [class*="name"]')
            || document.querySelector('.displayname')

        roomName = nameEl?.textContent?.trim()
    }

    if (!roomName) {
        // Ask user for room name
        roomName = prompt('Enter the room/conversation name:')
    }

    if (!roomName) {
        showError('Room name is required')
        return
    }

    await generateFederatedLink(roomName)
}

/**
 * Generate a federated link for the given conversation
 */
async function generateFederatedLink(roomName) {
    showInfo(`Generating federated link for "${roomName}"...`)

    try {
        const response = await axios.get(
            generateOcsUrl('/apps/federatedtalklink/api/v1/link'),
            {
                params: { roomName },
                headers: { 'OCS-APIRequest': 'true' }
            }
        )

        const data = response.data.ocs?.data
        if (data?.link) {
            try {
                await navigator.clipboard.writeText(data.link)
                showSuccess(`Link copied: ${data.link}`)
            } catch (clipboardError) {
                showSuccess(`Federated link: ${data.link}`)
                prompt('Copy this link:', data.link)
            }
        } else if (data?.error) {
            showError(data.error)
        } else {
            showError('No link returned from server')
        }
    } catch (error) {
        const errorMessage = error.response?.data?.ocs?.data?.error
            || error.response?.data?.ocs?.meta?.message
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
