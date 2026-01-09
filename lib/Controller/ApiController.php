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
     * Generate a federated link for a room
     *
     * Search priority (when searchBy is not specified):
     * 1. token - unique room identifier (most specific)
     * 2. objectId - for file/object-linked rooms
     * 3. name - internal room name
     * 4. displayName - user-visible name
     *
     * @param string $roomName The room identifier (token, name, displayName, or objectId)
     * @param string $searchBy Force search by specific field: 'token', 'name', 'displayName', 'objectId'
     * @return DataResponse
     */
    #[NoAdminRequired]
    public function generateLink(string $roomName = '', string $searchBy = ''): DataResponse
    {
        $roomName = trim($roomName);
        $searchBy = trim($searchBy);

        if (empty($roomName)) {
            return new DataResponse(
                ['error' => 'Room identifier is required. Use token, name, displayName, or objectId.'],
                Http::STATUS_BAD_REQUEST
            );
        }

        // Validate searchBy if provided
        $validSearchFields = ['token', 'name', 'displayName', 'objectId', ''];
        if (!in_array($searchBy, $validSearchFields)) {
            return new DataResponse(
                ['error' => "Invalid searchBy value. Valid options: token, name, displayName, objectId"],
                Http::STATUS_BAD_REQUEST
            );
        }

        $searchByParam = !empty($searchBy) ? $searchBy : null;
        $result = $this->federatedLinkService->generateFederatedLink($roomName, $searchByParam);

        if (!$result['success']) {
            $response = ['error' => $result['error']];

            // Include available rooms if room not found
            if (isset($result['availableRooms'])) {
                $response['availableRooms'] = $result['availableRooms'];
            }

            return new DataResponse($response, Http::STATUS_NOT_FOUND);
        }

        return new DataResponse([
            'link' => $result['link'],
            'token' => $result['token'],
            'joined' => $result['joined'] ?? false,
            'roomInfo' => $result['roomInfo'] ?? null,
        ]);
    }

    /**
     * Search for rooms on the external server
     *
     * @param string $search Optional search term (searches in displayName, name, token, objectId, description)
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

    /**
     * Send a federated link by email
     *
     * @param string $email Recipient email address
     * @param string $link The federated Talk link
     * @param string $roomName Optional room name for the email subject
     * @param string $message Optional custom message to include
     * @return DataResponse
     */
    #[NoAdminRequired]
    public function sendEmail(string $email = '', string $link = '', string $roomName = '', string $message = ''): DataResponse
    {
        $email = trim($email);
        $link = trim($link);

        if (empty($email)) {
            return new DataResponse(
                ['error' => 'Email address is required.'],
                Http::STATUS_BAD_REQUEST
            );
        }

        if (empty($link)) {
            return new DataResponse(
                ['error' => 'Link is required.'],
                Http::STATUS_BAD_REQUEST
            );
        }

        $roomNameParam = !empty($roomName) ? $roomName : null;
        $messageParam = !empty($message) ? $message : null;

        $result = $this->federatedLinkService->sendLinkByEmail($email, $link, $roomNameParam, $messageParam);

        if (!$result['success']) {
            return new DataResponse(
                ['error' => $result['error']],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }

        return new DataResponse([
            'message' => $result['message'],
        ]);
    }
}
