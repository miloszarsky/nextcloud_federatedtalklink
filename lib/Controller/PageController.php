<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Your Name <admin@example.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FederatedTalkLink\Controller;

use OCA\FederatedTalkLink\AppInfo\Application;
use OCA\FederatedTalkLink\Service\SettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;
use OCP\Util;

/**
 * Page Controller for the main app page
 */
class PageController extends Controller
{
    public function __construct(
        IRequest $request,
        private SettingsService $settingsService,
        private IInitialState $initialState
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Main page for generating federated links
     *
     * @return TemplateResponse
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): TemplateResponse
    {
        // Provide initial state to frontend
        $this->initialState->provideInitialState(
            'app-config',
            [
                'isConfigured' => $this->settingsService->isConfigured(),
            ]
        );

        // Load JavaScript and CSS
        Util::addScript(Application::APP_ID, 'federatedtalklink-main');
        Util::addStyle(Application::APP_ID, 'style');

        return new TemplateResponse(
            Application::APP_ID,
            'main',
            []
        );
    }
}
