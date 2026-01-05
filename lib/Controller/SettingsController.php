<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Your Name <admin@example.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FederatedTalkLink\Controller;

use OCA\FederatedTalkLink\AppInfo\Application;
use OCA\FederatedTalkLink\Service\FederatedLinkService;
use OCA\FederatedTalkLink\Service\SettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;

/**
 * Controller for admin settings
 */
class SettingsController extends Controller
{
    public function __construct(
        IRequest $request,
        private SettingsService $settingsService,
        private FederatedLinkService $federatedLinkService
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Show the settings page
     *
     * @return TemplateResponse
     */
    #[AuthorizedAdminSetting(settings: \OCA\FederatedTalkLink\Settings\AdminSettings::class)]
    public function index(): TemplateResponse
    {
        return new TemplateResponse(
            Application::APP_ID,
            'settings',
            [
                'settings' => $this->settingsService->getAllSettings(),
            ]
        );
    }

    /**
     * Save settings
     *
     * @return JSONResponse
     */
    #[AuthorizedAdminSetting(settings: \OCA\FederatedTalkLink\Settings\AdminSettings::class)]
    public function save(): JSONResponse
    {
        $externalServerUrl = $this->request->getParam('externalServerUrl', '');
        $username = $this->request->getParam('username', '');
        $password = $this->request->getParam('password', null);
        $targetNextcloudUrl = $this->request->getParam('targetNextcloudUrl', '');

        // Validate required fields
        if (empty($externalServerUrl)) {
            return new JSONResponse(
                ['error' => 'External server URL is required'],
                Http::STATUS_BAD_REQUEST
            );
        }

        if (empty($username)) {
            return new JSONResponse(
                ['error' => 'Username is required'],
                Http::STATUS_BAD_REQUEST
            );
        }

        if (empty($targetNextcloudUrl)) {
            return new JSONResponse(
                ['error' => 'Target Nextcloud URL is required'],
                Http::STATUS_BAD_REQUEST
            );
        }

        // Validate URLs
        if (!filter_var('https://' . ltrim($externalServerUrl, 'https://'), FILTER_VALIDATE_URL)) {
            return new JSONResponse(
                ['error' => 'Invalid external server URL format'],
                Http::STATUS_BAD_REQUEST
            );
        }

        if (!filter_var('https://' . ltrim($targetNextcloudUrl, 'https://'), FILTER_VALIDATE_URL)) {
            return new JSONResponse(
                ['error' => 'Invalid target Nextcloud URL format'],
                Http::STATUS_BAD_REQUEST
            );
        }

        try {
            $this->settingsService->saveAllSettings(
                $externalServerUrl,
                $username,
                $password,
                $targetNextcloudUrl
            );

            return new JSONResponse([
                'success' => true,
                'settings' => $this->settingsService->getAllSettings(),
            ]);
        } catch (\Exception $e) {
            return new JSONResponse(
                ['error' => 'Failed to save settings: ' . $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }
}
