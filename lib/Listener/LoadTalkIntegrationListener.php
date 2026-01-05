<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Your Name <admin@example.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FederatedTalkLink\Listener;

use OCA\FederatedTalkLink\AppInfo\Application;
use OCA\FederatedTalkLink\Service\SettingsService;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/**
 * Listener to load Talk integration script
 *
 * @template-implements IEventListener<BeforeTemplateRenderedEvent>
 */
class LoadTalkIntegrationListener implements IEventListener
{
    public function __construct(
        private SettingsService $settingsService
    ) {
    }

    public function handle(Event $event): void
    {
        if (!($event instanceof BeforeTemplateRenderedEvent)) {
            return;
        }

        // Only load if the app is configured
        if (!$this->settingsService->isConfigured()) {
            return;
        }

        // Check if we're on a Talk page
        $appId = $event->getResponse()->getApp();
        if ($appId !== 'spreed') {
            return;
        }

        // Load our Talk integration script (vendors must be loaded first)
        Util::addScript(Application::APP_ID, 'federatedtalklink-vendors');
        Util::addScript(Application::APP_ID, 'federatedtalklink-talk-integration');
    }
}
