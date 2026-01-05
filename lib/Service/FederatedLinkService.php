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
 * Queries external Nextcloud Talk servers to find rooms
 * and generates direct call links
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
     * Find a room by name and generate a federated link
     *
     * @param string $roomName The name of the room to find
     * @return array{success: bool, link?: string, token?: string, error?: string, roomInfo?: array}
     */
    public function generateFederatedLink(string $roomName): array
    {
        if (!$this->settingsService->isConfigured()) {
            return [
                'success' => false,
                'error' => 'App is not configured. Please configure the settings first.',
            ];
        }

        $roomName = trim($roomName);
        if (empty($roomName)) {
            return [
                'success' => false,
                'error' => 'Room name cannot be empty.',
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

            // Search for the room by name
            $foundRoom = null;
            foreach ($rooms['ocs']['data'] as $room) {
                if (isset($room['displayName']) && $room['displayName'] === $roomName) {
                    $foundRoom = $room;
                    break;
                }
                // Also check 'name' field as fallback
                if (isset($room['name']) && $room['name'] === $roomName) {
                    $foundRoom = $room;
                    break;
                }
            }

            if ($foundRoom === null) {
                return [
                    'success' => false,
                    'error' => "Room '{$roomName}' not found on the external server.",
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

            // Generate the federated link
            $targetUrl = $this->settingsService->getTargetNextcloudUrl();
            $link = rtrim($targetUrl, '/') . '/call/' . $token;

            return [
                'success' => true,
                'link' => $link,
                'token' => $token,
                'roomInfo' => [
                    'name' => $foundRoom['displayName'] ?? $foundRoom['name'] ?? $roomName,
                    'type' => $foundRoom['type'] ?? null,
                    'participantType' => $foundRoom['participantType'] ?? null,
                ],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate federated link', [
                'app' => 'federatedtalklink',
                'exception' => $e,
                'roomName' => $roomName,
            ]);

            return [
                'success' => false,
                'error' => 'Failed to query external server: ' . $e->getMessage(),
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
                    if (stripos($displayName, $searchTerm) === false) {
                        continue;
                    }
                }

                $roomList[] = [
                    'token' => $room['token'] ?? '',
                    'name' => $displayName,
                    'type' => $room['type'] ?? null,
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
