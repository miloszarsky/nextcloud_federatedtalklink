<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Your Name <admin@example.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FederatedTalkLink\Settings;

use OCA\FederatedTalkLink\AppInfo\Application;
use OCA\FederatedTalkLink\Service\SettingsService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Settings\ISettings;

/**
 * Admin settings panel
 */
class AdminSettings implements ISettings
{
    public function __construct(
        private SettingsService $settingsService,
        private IInitialState $initialState
    ) {
    }

    /**
     * Get the settings form
     *
     * @return TemplateResponse
     */
    public function getForm(): TemplateResponse
    {
        // Provide initial state to the frontend
        $this->initialState->provideInitialState(
            'admin-settings',
            $this->settingsService->getAllSettings()
        );

        return new TemplateResponse(
            Application::APP_ID,
            'admin-settings',
            [],
            ''
        );
    }

    /**
     * Get the section ID
     *
     * @return string
     */
    public function getSection(): string
    {
        return Application::APP_ID;
    }

    /**
     * Get the priority
     *
     * @return int
     */
    public function getPriority(): int
    {
        return 50;
    }
}
