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
let lastGeneratedLink = null

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
            lastGeneratedLink = null
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

    const link = await generateFederatedLink(roomName)
    if (link) {
        lastGeneratedLink = link
        showLinkDialog(link, roomName)
    }
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
            return data.link
        } else if (data?.error) {
            showError(data.error)
            return null
        } else {
            showError('No link returned from server')
            return null
        }
    } catch (error) {
        const errorMessage = error.response?.data?.ocs?.data?.error
            || error.response?.data?.ocs?.meta?.message
            || error.message
            || 'Failed to generate federated link'
        showError(errorMessage)
        return null
    }
}

/**
 * Show dialog with link options (copy and email)
 */
function showLinkDialog(link, roomName) {
    // Remove existing dialog if any
    const existingDialog = document.querySelector('.federated-link-dialog-overlay')
    if (existingDialog) {
        existingDialog.remove()
    }

    // Create overlay
    const overlay = document.createElement('div')
    overlay.className = 'federated-link-dialog-overlay'
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
    `

    // Create dialog
    const dialog = document.createElement('div')
    dialog.className = 'federated-link-dialog'
    dialog.style.cssText = `
        background: var(--color-main-background, white);
        border-radius: 12px;
        padding: 24px;
        max-width: 500px;
        width: 90%;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    `

    dialog.innerHTML = `
        <h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 600;">Federated Link Generated</h3>

        <div style="margin-bottom: 16px;">
            <label style="display: block; font-weight: 500; margin-bottom: 6px;">Link:</label>
            <div style="display: flex; gap: 8px;">
                <input type="text" value="${link}" readonly style="
                    flex: 1;
                    padding: 10px;
                    border: 1px solid var(--color-border, #ddd);
                    border-radius: 6px;
                    background: var(--color-background-dark, #f5f5f5);
                    font-family: monospace;
                    font-size: 12px;
                " />
                <button class="federated-copy-btn" style="
                    padding: 10px 16px;
                    border: none;
                    border-radius: 6px;
                    background: var(--color-primary, #0082c9);
                    color: white;
                    cursor: pointer;
                    font-weight: 500;
                ">Copy</button>
            </div>
        </div>

        <hr style="border: none; border-top: 1px solid var(--color-border, #ddd); margin: 20px 0;" />

        <div style="margin-bottom: 16px;">
            <label style="display: block; font-weight: 500; margin-bottom: 6px;">Send by Email:</label>
            <div style="display: flex; gap: 8px;">
                <input type="email" placeholder="Enter email address..." class="federated-email-input" style="
                    flex: 1;
                    padding: 10px;
                    border: 1px solid var(--color-border, #ddd);
                    border-radius: 6px;
                    font-size: 14px;
                " />
                <button class="federated-send-btn" style="
                    padding: 10px 16px;
                    border: none;
                    border-radius: 6px;
                    background: #46ba61;
                    color: white;
                    cursor: pointer;
                    font-weight: 500;
                ">Send</button>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; margin-top: 20px;">
            <button class="federated-close-btn" style="
                padding: 10px 20px;
                border: 1px solid var(--color-border, #ddd);
                border-radius: 6px;
                background: transparent;
                cursor: pointer;
                font-weight: 500;
            ">Close</button>
        </div>
    `

    overlay.appendChild(dialog)
    document.body.appendChild(overlay)

    // Event handlers
    const copyBtn = dialog.querySelector('.federated-copy-btn')
    const sendBtn = dialog.querySelector('.federated-send-btn')
    const closeBtn = dialog.querySelector('.federated-close-btn')
    const emailInput = dialog.querySelector('.federated-email-input')

    copyBtn.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(link)
            showSuccess('Link copied to clipboard!')
            copyBtn.textContent = 'Copied!'
            setTimeout(() => { copyBtn.textContent = 'Copy' }, 2000)
        } catch (error) {
            showError('Failed to copy link')
        }
    })

    sendBtn.addEventListener('click', async () => {
        const email = emailInput.value.trim()
        if (!email) {
            showError('Please enter an email address')
            return
        }

        sendBtn.textContent = 'Sending...'
        sendBtn.disabled = true

        try {
            const response = await axios.post(
                generateOcsUrl('/apps/federatedtalklink/api/v1/email'),
                {
                    email: email,
                    link: link,
                    roomName: roomName,
                },
                { headers: { 'OCS-APIRequest': 'true' } }
            )

            const data = response.data.ocs?.data
            if (data?.message) {
                showSuccess(data.message)
            } else {
                showSuccess('Email sent successfully!')
            }
            emailInput.value = ''
        } catch (error) {
            const errorMessage = error.response?.data?.ocs?.data?.error
                || error.message
                || 'Failed to send email'
            showError(errorMessage)
        } finally {
            sendBtn.textContent = 'Send'
            sendBtn.disabled = false
        }
    })

    // Enter key to send email
    emailInput.addEventListener('keyup', (e) => {
        if (e.key === 'Enter') {
            sendBtn.click()
        }
    })

    closeBtn.addEventListener('click', () => {
        overlay.remove()
    })

    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
            overlay.remove()
        }
    })

    // ESC key to close
    document.addEventListener('keydown', function escHandler(e) {
        if (e.key === 'Escape') {
            overlay.remove()
            document.removeEventListener('keydown', escHandler)
        }
    })
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTalkIntegration)
} else {
    initTalkIntegration()
}

export { initTalkIntegration, generateFederatedLink }
