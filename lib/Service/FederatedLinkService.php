<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Your Name <admin@example.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FederatedTalkLink\Service;

use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;

/**
 * Service for generating federated Talk links
 *
 * Queries external Nextcloud Talk servers to find rooms,
 * joins them, and generates direct call links
 */
class FederatedLinkService
{
    private const TALK_API_ENDPOINT = '/ocs/v2.php/apps/spreed/api/v4/room';

    public function __construct(
        private SettingsService $settingsService,
        private IClientService $clientService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Find a room and generate a federated link
     *
     * Search priority:
     * 1. token (exact match) - most specific
     * 2. objectId (exact match) - for file/object-linked rooms
     * 3. name (exact match) - internal name
     * 4. displayName (exact match) - user-visible name
     * 5. displayName (partial match) - fuzzy search
     *
     * @param string $identifier The room identifier (token, name, displayName, or objectId)
     * @param string|null $searchBy Force search by specific field: 'token', 'name', 'displayName', 'objectId', or null for auto
     * @return array{success: bool, link?: string, token?: string, error?: string, roomInfo?: array}
     */
    public function generateFederatedLink(string $identifier, ?string $searchBy = null): array
    {
        if (!$this->settingsService->isConfigured()) {
            return [
                'success' => false,
                'error' => 'App is not configured. Please configure the settings first.',
            ];
        }

        $identifier = trim($identifier);
        if (empty($identifier)) {
            return [
                'success' => false,
                'error' => 'Room identifier cannot be empty.',
            ];
        }

        try {
            $rooms = $this->fetchRooms();

            if (!isset($rooms['ocs']['data'])) {
                return [
                    'success' => false,
                    'error' => 'Invalid response from external server.',
                ];
            }

            // Find the room using the identifier
            $foundRoom = $this->findRoom($rooms['ocs']['data'], $identifier, $searchBy);

            if ($foundRoom === null) {
                return [
                    'success' => false,
                    'error' => "Room '{$identifier}' not found on the external server.",
                    'availableRooms' => $this->getAvailableRoomsSummary($rooms['ocs']['data']),
                ];
            }

            // Get the room token
            $token = $foundRoom['token'] ?? null;
            if (empty($token)) {
                return [
                    'success' => false,
                    'error' => 'Room found but token is missing.',
                ];
            }

            // Join the room first
            $joinResult = $this->joinRoom($token);
            if (!$joinResult['success']) {
                $this->logger->warning('Failed to join room, continuing anyway', [
                    'app' => 'federatedtalklink',
                    'token' => $token,
                    'error' => $joinResult['error'] ?? 'Unknown error',
                ]);
                // Don't fail - the link might still work
            }

            // Generate the federated link
            $targetUrl = $this->settingsService->getTargetNextcloudUrl();
            $link = rtrim($targetUrl, '/') . '/call/' . $token;

            return [
                'success' => true,
                'link' => $link,
                'token' => $token,
                'joined' => $joinResult['success'],
                'roomInfo' => [
                    'token' => $token,
                    'name' => $foundRoom['name'] ?? null,
                    'displayName' => $foundRoom['displayName'] ?? null,
                    'description' => $foundRoom['description'] ?? null,
                    'type' => $foundRoom['type'] ?? null,
                    'objectType' => $foundRoom['objectType'] ?? null,
                    'objectId' => $foundRoom['objectId'] ?? null,
                    'participantType' => $foundRoom['participantType'] ?? null,
                ],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate federated link', [
                'app' => 'federatedtalklink',
                'exception' => $e,
                'identifier' => $identifier,
            ]);

            return [
                'success' => false,
                'error' => 'Failed to query external server: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Find a room in the room list
     *
     * @param array $rooms List of rooms
     * @param string $identifier The identifier to search for
     * @param string|null $searchBy Force search by specific field
     * @return array|null The found room or null
     */
    private function findRoom(array $rooms, string $identifier, ?string $searchBy): ?array
    {
        // If specific field is requested
        if ($searchBy !== null) {
            foreach ($rooms as $room) {
                if (isset($room[$searchBy]) && $room[$searchBy] === $identifier) {
                    return $room;
                }
            }
            return null;
        }

        // Auto-search with priority

        // 1. Exact match by token (most specific)
        foreach ($rooms as $room) {
            if (isset($room['token']) && $room['token'] === $identifier) {
                return $room;
            }
        }

        // 2. Exact match by objectId (for file/object-linked rooms)
        foreach ($rooms as $room) {
            if (isset($room['objectId']) && $room['objectId'] === $identifier) {
                return $room;
            }
        }

        // 3. Exact match by name (internal name)
        foreach ($rooms as $room) {
            if (isset($room['name']) && $room['name'] === $identifier) {
                return $room;
            }
        }

        // 4. Exact match by displayName
        foreach ($rooms as $room) {
            if (isset($room['displayName']) && $room['displayName'] === $identifier) {
                return $room;
            }
        }

        // 5. Case-insensitive match by displayName
        foreach ($rooms as $room) {
            if (isset($room['displayName']) && strcasecmp($room['displayName'], $identifier) === 0) {
                return $room;
            }
        }

        // 6. Case-insensitive match by name
        foreach ($rooms as $room) {
            if (isset($room['name']) && strcasecmp($room['name'], $identifier) === 0) {
                return $room;
            }
        }

        return null;
    }

    /**
     * Get a summary of available rooms for error messages
     */
    private function getAvailableRoomsSummary(array $rooms): array
    {
        $summary = [];
        foreach ($rooms as $room) {
            $summary[] = [
                'token' => $room['token'] ?? '',
                'name' => $room['name'] ?? '',
                'displayName' => $room['displayName'] ?? '',
                'objectId' => $room['objectId'] ?? null,
            ];
        }
        return $summary;
    }

    /**
     * Join a room on the external server
     *
     * @param string $token The room token
     * @return array{success: bool, error?: string}
     */
    public function joinRoom(string $token): array
    {
        try {
            $serverUrl = $this->settingsService->getExternalServerUrl();
            $username = $this->settingsService->getUsername();
            $password = $this->settingsService->getPassword();

            // Build the join API URL
            $apiUrl = 'https://' . ltrim($serverUrl, 'https://') . self::TALK_API_ENDPOINT . '/' . $token . '/participants/active';

            $client = $this->clientService->newClient();

            $response = $client->post($apiUrl, [
                'auth' => [$username, $password],
                'headers' => [
                    'OCS-APIRequest' => 'true',
                    'Accept' => 'application/json',
                ],
                'timeout' => 30,
            ]);

            $body = $response->getBody();
            $data = json_decode($body, true);

            $status = $data['ocs']['meta']['status'] ?? null;
            if ($status === 'ok') {
                $this->logger->info('Successfully joined room', [
                    'app' => 'federatedtalklink',
                    'token' => $token,
                ]);
                return ['success' => true];
            }

            $message = $data['ocs']['meta']['message'] ?? 'Unknown error';
            return [
                'success' => false,
                'error' => "Join failed: {$message}",
            ];
        } catch (\Exception $e) {
            $this->logger->warning('Failed to join room', [
                'app' => 'federatedtalklink',
                'token' => $token,
                'exception' => $e,
            ]);

            return [
                'success' => false,
                'error' => 'Join request failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Search rooms on the external server
     *
     * @param string|null $searchTerm Optional search term to filter rooms
     * @return array{success: bool, rooms?: array, error?: string}
     */
    public function searchRooms(?string $searchTerm = null): array
    {
        if (!$this->settingsService->isConfigured()) {
            return [
                'success' => false,
                'error' => 'App is not configured. Please configure the settings first.',
            ];
        }

        try {
            $rooms = $this->fetchRooms();

            if (!isset($rooms['ocs']['data'])) {
                return [
                    'success' => false,
                    'error' => 'Invalid response from external server.',
                ];
            }

            $roomList = [];
            foreach ($rooms['ocs']['data'] as $room) {
                $displayName = $room['displayName'] ?? $room['name'] ?? 'Unknown';

                // Filter by search term if provided
                if ($searchTerm !== null && !empty($searchTerm)) {
                    $searchLower = strtolower($searchTerm);
                    $matchFound = false;

                    // Search in multiple fields
                    foreach (['displayName', 'name', 'token', 'objectId', 'description'] as $field) {
                        if (isset($room[$field]) && stripos($room[$field], $searchTerm) !== false) {
                            $matchFound = true;
                            break;
                        }
                    }

                    if (!$matchFound) {
                        continue;
                    }
                }

                $roomList[] = [
                    'token' => $room['token'] ?? '',
                    'name' => $room['name'] ?? '',
                    'displayName' => $displayName,
                    'description' => $room['description'] ?? '',
                    'type' => $room['type'] ?? null,
                    'objectType' => $room['objectType'] ?? null,
                    'objectId' => $room['objectId'] ?? null,
                    'participantCount' => $room['participantCount'] ?? 0,
                ];
            }

            return [
                'success' => true,
                'rooms' => $roomList,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to search rooms', [
                'app' => 'federatedtalklink',
                'exception' => $e,
            ]);

            return [
                'success' => false,
                'error' => 'Failed to query external server: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Test the connection to the external server
     *
     * @return array{success: bool, message?: string, error?: string, roomCount?: int}
     */
    public function testConnection(): array
    {
        if (!$this->settingsService->isConfigured()) {
            return [
                'success' => false,
                'error' => 'App is not configured. Please configure the settings first.',
            ];
        }

        try {
            $rooms = $this->fetchRooms();

            if (!isset($rooms['ocs']['meta']['status'])) {
                return [
                    'success' => false,
                    'error' => 'Invalid response format from external server.',
                ];
            }

            $status = $rooms['ocs']['meta']['status'];
            if ($status !== 'ok') {
                $statusCode = $rooms['ocs']['meta']['statuscode'] ?? 'unknown';
                $message = $rooms['ocs']['meta']['message'] ?? 'Unknown error';
                return [
                    'success' => false,
                    'error' => "API returned status '{$status}' (code: {$statusCode}): {$message}",
                ];
            }

            $roomCount = count($rooms['ocs']['data'] ?? []);

            return [
                'success' => true,
                'message' => 'Connection successful!',
                'roomCount' => $roomCount,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Connection test failed', [
                'app' => 'federatedtalklink',
                'exception' => $e,
            ]);

            return [
                'success' => false,
                'error' => 'Connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch rooms from the external Talk API
     *
     * @return array The decoded JSON response
     * @throws \Exception If the request fails
     */
    private function fetchRooms(): array
    {
        $serverUrl = $this->settingsService->getExternalServerUrl();
        $username = $this->settingsService->getUsername();
        $password = $this->settingsService->getPassword();

        // Build the API URL
        $apiUrl = 'https://' . ltrim($serverUrl, 'https://') . self::TALK_API_ENDPOINT;

        $client = $this->clientService->newClient();

        $response = $client->get($apiUrl, [
            'auth' => [$username, $password],
            'headers' => [
                'OCS-APIRequest' => 'true',
                'Accept' => 'application/json',
            ],
            'timeout' => 30,
        ]);

        $body = $response->getBody();
        $data = json_decode($body, true);

        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Failed to parse JSON response: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Generate a direct link to a room by token
     *
     * @param string $token The room token
     * @return string The full URL to the call
     */
    public function generateLinkByToken(string $token): string
    {
        $targetUrl = $this->settingsService->getTargetNextcloudUrl();
        return rtrim($targetUrl, '/') . '/call/' . $token;
    }
}
