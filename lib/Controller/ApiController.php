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
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

/**
 * OCS API Controller for Federated Talk Link
 *
 * Provides API endpoints for generating federated links
 * and searching rooms on external servers
 */
class ApiController extends OCSController
{
    public function __construct(
        IRequest $request,
        private FederatedLinkService $federatedLinkService,
        private SettingsService $settingsService
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Generate a federated link for a room by name
     *
     * @param string $roomName The name of the room
     * @return DataResponse
     */
    #[NoAdminRequired]
    public function generateLink(string $roomName = ''): DataResponse
    {
        $roomName = trim($roomName);

        if (empty($roomName)) {
            return new DataResponse(
                ['error' => 'Room name is required'],
                Http::STATUS_BAD_REQUEST
            );
        }

        $result = $this->federatedLinkService->generateFederatedLink($roomName);

        if (!$result['success']) {
            return new DataResponse(
                ['error' => $result['error']],
                Http::STATUS_NOT_FOUND
            );
        }

        return new DataResponse([
            'link' => $result['link'],
            'token' => $result['token'],
            'roomInfo' => $result['roomInfo'] ?? null,
        ]);
    }

    /**
     * Search for rooms on the external server
     *
     * @param string $search Optional search term
     * @return DataResponse
     */
    #[NoAdminRequired]
    public function searchRooms(string $search = ''): DataResponse
    {
        $search = trim($search);
        $searchTerm = !empty($search) ? $search : null;

        $result = $this->federatedLinkService->searchRooms($searchTerm);

        if (!$result['success']) {
            return new DataResponse(
                ['error' => $result['error']],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }

        return new DataResponse([
            'rooms' => $result['rooms'],
        ]);
    }

    /**
     * Test the connection to the external server
     *
     * @return DataResponse
     */
    #[NoAdminRequired]
    public function testConnection(): DataResponse
    {
        $result = $this->federatedLinkService->testConnection();

        if (!$result['success']) {
            return new DataResponse(
                ['error' => $result['error']],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }

        return new DataResponse([
            'message' => $result['message'],
            'roomCount' => $result['roomCount'],
        ]);
    }

    /**
     * Get current settings (for admin panel)
     *
     * @return DataResponse
     */
    public function getSettings(): DataResponse
    {
        $settings = $this->settingsService->getAllSettings();

        return new DataResponse($settings);
    }
}
