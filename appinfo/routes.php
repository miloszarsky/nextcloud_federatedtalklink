<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Your Name <admin@example.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
    'routes' => [
        // Admin settings page
        [
            'name' => 'settings#index',
            'url' => '/settings',
            'verb' => 'GET',
        ],
        [
            'name' => 'settings#save',
            'url' => '/settings',
            'verb' => 'POST',
        ],

        // Main page for standalone link generation
        [
            'name' => 'page#index',
            'url' => '/',
            'verb' => 'GET',
        ],
    ],

    'ocs' => [
        // OCS API endpoint to generate federated link
        [
            'name' => 'api#generateLink',
            'url' => '/api/v1/link',
            'verb' => 'GET',
        ],

        // OCS API endpoint to search rooms
        [
            'name' => 'api#searchRooms',
            'url' => '/api/v1/rooms',
            'verb' => 'GET',
        ],

        // OCS API endpoint to test connection
        [
            'name' => 'api#testConnection',
            'url' => '/api/v1/test',
            'verb' => 'GET',
        ],

        // OCS API endpoint to get current settings (for admin)
        [
            'name' => 'api#getSettings',
            'url' => '/api/v1/settings',
            'verb' => 'GET',
        ],
    ],
];
