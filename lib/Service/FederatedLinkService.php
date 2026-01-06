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
 * handles invitations, joins them, and generates direct call links
 */
class FederatedLinkService
{
    private const TALK_API_ENDPOINT = '/ocs/v2.php/apps/spreed/api/v4/room';
    private const NOTIFICATIONS_API_ENDPOINT = '/ocs/v2.php/apps/notifications/api/v2/notifications';
    private const FEDERATION_ACCEPT_ENDPOINT = '/ocs/v2.php/apps/spreed/api/v4/federation/invitation';

    public function __construct(
        private SettingsService $settingsService,
        private IClientService $clientService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Find a room and generate a federated link
     *
     * Flow:
     * 1. Fetch notifications to find pending invitations
     * 2. Accept invitation if found
     * 3. Fetch rooms to find the room
     * 4. Join the room
     * 5. Generate the link
     *
     * @param string $identifier The room identifier (token, name, displayName, or objectId)
     * @param string|null $searchBy Force search by specific field
     * @return array
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
            // Step 1: Check and accept pending invitations
            $invitationResult = $this->checkAndAcceptInvitation($identifier);
            $this->logger->info('Invitation check result', [
                'app' => 'federatedtalklink',
                'identifier' => $identifier,
                'result' => $invitationResult,
            ]);

            // Step 2: Fetch rooms
            $rooms = $this->fetchRooms();

            if (!isset($rooms['ocs']['data'])) {
                return [
                    'success' => false,
                    'error' => 'Invalid response from external server.',
                ];
            }

            // Step 3: Find the room
            $foundRoom = $this->findRoom($rooms['ocs']['data'], $identifier, $searchBy);

            if ($foundRoom === null) {
                return [
                    'success' => false,
                    'error' => "Room '{$identifier}' not found on the external server.",
                    'availableRooms' => $this->getAvailableRoomsSummary($rooms['ocs']['data']),
                    'invitationResult' => $invitationResult,
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

            // Step 4: Join the room
            $joinResult = $this->joinRoom($token);

            // Generate the federated link
            $targetUrl = $this->settingsService->getTargetNextcloudUrl();
            $link = rtrim($targetUrl, '/') . '/call/' . $token;

            return [
                'success' => true,
                'link' => $link,
                'token' => $token,
                'joined' => $joinResult['success'],
                'invitationAccepted' => $invitationResult['accepted'] ?? false,
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
     * Fetch notifications and check for pending Talk invitations
     *
     * @param string $identifier Room identifier to match
     * @return array
     */
    public function checkAndAcceptInvitation(string $identifier): array
    {
        try {
            // Fetch notifications
            $notifications = $this->fetchNotifications();

            if (!isset($notifications['ocs']['data'])) {
                return [
                    'success' => true,
                    'accepted' => false,
                    'message' => 'No notifications found',
                ];
            }

            $this->logger->info('Fetched notifications', [
                'app' => 'federatedtalklink',
                'count' => count($notifications['ocs']['data']),
            ]);

            // Look for Talk invitation notifications
            foreach ($notifications['ocs']['data'] as $notification) {
                $app = $notification['app'] ?? '';
                $objectType = $notification['object_type'] ?? '';
                $subject = $notification['subject'] ?? '';
                $message = $notification['message'] ?? '';

                // Check if this is a Talk/Spreed notification
                if ($app !== 'spreed' && $app !== 'talk') {
                    continue;
                }

                $this->logger->info('Found Talk notification', [
                    'app' => 'federatedtalklink',
                    'notification' => $notification,
                ]);

                // Check if this notification matches our room
                $matchesRoom = false;

                // Check various fields for match
                $objectId = $notification['object_id'] ?? '';
                $subjectParams = $notification['subjectRichParameters'] ?? $notification['subjectParameters'] ?? [];
                $messageParams = $notification['messageRichParameters'] ?? $notification['messageParameters'] ?? [];

                // Try to match by token, name, or in the message/subject
                if (stripos($objectId, $identifier) !== false) {
                    $matchesRoom = true;
                } elseif (stripos($subject, $identifier) !== false) {
                    $matchesRoom = true;
                } elseif (stripos($message, $identifier) !== false) {
                    $matchesRoom = true;
                }

                // Check parameters for room name
                foreach (array_merge($subjectParams, $messageParams) as $param) {
                    if (is_array($param)) {
                        $paramName = $param['name'] ?? '';
                        if (stripos($paramName, $identifier) !== false) {
                            $matchesRoom = true;
                            break;
                        }
                    }
                }

                if (!$matchesRoom) {
                    // Accept any Talk invitation if no specific match
                    // This handles cases where the identifier might not be in the notification
                    if ($objectType === 'invitation' || $objectType === 'room' || stripos($subject, 'invitation') !== false) {
                        $matchesRoom = true;
                    }
                }

                if ($matchesRoom) {
                    // Try to accept the invitation using notification actions
                    $acceptResult = $this->acceptInvitationFromNotification($notification);
                    if ($acceptResult['success']) {
                        return [
                            'success' => true,
                            'accepted' => true,
                            'notification' => $notification,
                            'acceptResult' => $acceptResult,
                        ];
                    }
                }
            }

            return [
                'success' => true,
                'accepted' => false,
                'message' => 'No matching invitation found',
                'notificationCount' => count($notifications['ocs']['data']),
            ];

        } catch (\Exception $e) {
            $this->logger->warning('Failed to check invitations', [
                'app' => 'federatedtalklink',
                'exception' => $e,
            ]);

            return [
                'success' => false,
                'accepted' => false,
                'error' => 'Failed to check invitations: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Accept invitation using notification actions
     *
     * @param array $notification The notification data
     * @return array
     */
    private function acceptInvitationFromNotification(array $notification): array
    {
        $actions = $notification['actions'] ?? [];

        $this->logger->info('Processing notification actions', [
            'app' => 'federatedtalklink',
            'actions' => $actions,
        ]);

        foreach ($actions as $action) {
            $label = strtolower($action['label'] ?? '');
            $type = strtolower($action['type'] ?? '');
            $link = $action['link'] ?? '';
            $method = strtoupper($action['type'] ?? 'POST');

            // Look for accept action
            if (strpos($label, 'accept') !== false || $type === 'accept' || $label === 'yes') {
                if (!empty($link)) {
                    return $this->executeNotificationAction($link, $method);
                }
            }
        }

        // If no explicit accept action, try federation accept endpoint
        $objectId = $notification['object_id'] ?? '';
        if (!empty($objectId)) {
            // Try to extract invite ID and accept via federation API
            return $this->acceptFederationInvitation($objectId);
        }

        return [
            'success' => false,
            'error' => 'No accept action found in notification',
        ];
    }

    /**
     * Execute a notification action
     *
     * @param string $link The action URL
     * @param string $method HTTP method
     * @return array
     */
    private function executeNotificationAction(string $link, string $method = 'POST'): array
    {
        try {
            $serverUrl = $this->settingsService->getExternalServerUrl();
            $username = $this->settingsService->getUsername();
            $password = $this->settingsService->getPassword();

            // Build full URL if relative
            if (!str_starts_with($link, 'http')) {
                $link = 'https://' . ltrim($serverUrl, 'https://') . $link;
            }

            $client = $this->clientService->newClient();

            $options = [
                'auth' => [$username, $password],
                'headers' => [
                    'OCS-APIRequest' => 'true',
                    'Accept' => 'application/json',
                ],
                'timeout' => 30,
            ];

            if ($method === 'POST') {
                $response = $client->post($link, $options);
            } elseif ($method === 'DELETE') {
                $response = $client->delete($link, $options);
            } else {
                $response = $client->get($link, $options);
            }

            $body = $response->getBody();
            $data = json_decode($body, true);

            $this->logger->info('Notification action executed', [
                'app' => 'federatedtalklink',
                'link' => $link,
                'method' => $method,
                'response' => $data,
            ]);

            return [
                'success' => true,
                'response' => $data,
            ];

        } catch (\Exception $e) {
            $this->logger->warning('Failed to execute notification action', [
                'app' => 'federatedtalklink',
                'link' => $link,
                'exception' => $e,
            ]);

            return [
                'success' => false,
                'error' => 'Failed to execute action: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Accept federation invitation via Talk API
     *
     * @param string $inviteId The invitation ID
     * @return array
     */
    public function acceptFederationInvitation(string $inviteId): array
    {
        try {
            $serverUrl = $this->settingsService->getExternalServerUrl();
            $username = $this->settingsService->getUsername();
            $password = $this->settingsService->getPassword();

            // POST /ocs/v2.php/apps/spreed/api/v4/federation/invitation/{id}
            $apiUrl = 'https://' . ltrim($serverUrl, 'https://') . self::FEDERATION_ACCEPT_ENDPOINT . '/' . $inviteId;

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
                $this->logger->info('Federation invitation accepted', [
                    'app' => 'federatedtalklink',
                    'inviteId' => $inviteId,
                ]);
                return [
                    'success' => true,
                    'response' => $data,
                ];
            }

            return [
                'success' => false,
                'error' => $data['ocs']['meta']['message'] ?? 'Unknown error',
            ];

        } catch (\Exception $e) {
            $this->logger->warning('Failed to accept federation invitation', [
                'app' => 'federatedtalklink',
                'inviteId' => $inviteId,
                'exception' => $e,
            ]);

            return [
                'success' => false,
                'error' => 'Failed to accept invitation: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch notifications from the external server
     *
     * @return array
     */
    public function fetchNotifications(): array
    {
        $serverUrl = $this->settingsService->getExternalServerUrl();
        $username = $this->settingsService->getUsername();
        $password = $this->settingsService->getPassword();

        $apiUrl = 'https://' . ltrim($serverUrl, 'https://') . self::NOTIFICATIONS_API_ENDPOINT;

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
     * Get pending federation invitations
     *
     * @return array
     */
    public function getPendingInvitations(): array
    {
        try {
            $serverUrl = $this->settingsService->getExternalServerUrl();
            $username = $this->settingsService->getUsername();
            $password = $this->settingsService->getPassword();

            // GET /ocs/v2.php/apps/spreed/api/v4/federation/invitation
            $apiUrl = 'https://' . ltrim($serverUrl, 'https://') . self::FEDERATION_ACCEPT_ENDPOINT;

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

            return [
                'success' => true,
                'invitations' => $data['ocs']['data'] ?? [],
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'invitations' => [],
            ];
        }
    }

    /**
     * Find a room in the room list
     */
    private function findRoom(array $rooms, string $identifier, ?string $searchBy): ?array
    {
        if ($searchBy !== null) {
            foreach ($rooms as $room) {
                if (isset($room[$searchBy]) && $room[$searchBy] === $identifier) {
                    return $room;
                }
            }
            return null;
        }

        // Auto-search with priority
        $searchFields = ['token', 'objectId', 'name', 'displayName'];

        // Exact matches first
        foreach ($searchFields as $field) {
            foreach ($rooms as $room) {
                if (isset($room[$field]) && $room[$field] === $identifier) {
                    return $room;
                }
            }
        }

        // Case-insensitive matches
        foreach (['displayName', 'name'] as $field) {
            foreach ($rooms as $room) {
                if (isset($room[$field]) && strcasecmp($room[$field], $identifier) === 0) {
                    return $room;
                }
            }
        }

        return null;
    }

    /**
     * Get a summary of available rooms
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
     */
    public function joinRoom(string $token): array
    {
        try {
            $serverUrl = $this->settingsService->getExternalServerUrl();
            $username = $this->settingsService->getUsername();
            $password = $this->settingsService->getPassword();

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
                return ['success' => true];
            }

            return [
                'success' => false,
                'error' => $data['ocs']['meta']['message'] ?? 'Unknown error',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Join request failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Search rooms on the external server
     */
    public function searchRooms(?string $searchTerm = null): array
    {
        if (!$this->settingsService->isConfigured()) {
            return ['success' => false, 'error' => 'App is not configured.'];
        }

        try {
            $rooms = $this->fetchRooms();

            if (!isset($rooms['ocs']['data'])) {
                return ['success' => false, 'error' => 'Invalid response.'];
            }

            $roomList = [];
            foreach ($rooms['ocs']['data'] as $room) {
                $displayName = $room['displayName'] ?? $room['name'] ?? 'Unknown';

                if ($searchTerm !== null && !empty($searchTerm)) {
                    $matchFound = false;
                    foreach (['displayName', 'name', 'token', 'objectId', 'description'] as $field) {
                        if (isset($room[$field]) && stripos($room[$field], $searchTerm) !== false) {
                            $matchFound = true;
                            break;
                        }
                    }
                    if (!$matchFound) continue;
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

            return ['success' => true, 'rooms' => $roomList];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Test the connection
     */
    public function testConnection(): array
    {
        if (!$this->settingsService->isConfigured()) {
            return ['success' => false, 'error' => 'App is not configured.'];
        }

        try {
            $rooms = $this->fetchRooms();

            if (!isset($rooms['ocs']['meta']['status']) || $rooms['ocs']['meta']['status'] !== 'ok') {
                return ['success' => false, 'error' => 'API returned error.'];
            }

            return [
                'success' => true,
                'message' => 'Connection successful!',
                'roomCount' => count($rooms['ocs']['data'] ?? []),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Fetch rooms from the external Talk API
     */
    private function fetchRooms(): array
    {
        $serverUrl = $this->settingsService->getExternalServerUrl();
        $username = $this->settingsService->getUsername();
        $password = $this->settingsService->getPassword();

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
            throw new \Exception('Failed to parse JSON: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Generate link by token
     */
    public function generateLinkByToken(string $token): string
    {
        $targetUrl = $this->settingsService->getTargetNextcloudUrl();
        return rtrim($targetUrl, '/') . '/call/' . $token;
    }
}
