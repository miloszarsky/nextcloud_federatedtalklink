<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Your Name <admin@example.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Standalone settings page template (alternative to admin settings)
 */

/** @var array $_ */
$settings = $_['settings'] ?? [];

\OCP\Util::addScript('federatedtalklink', 'federatedtalklink-admin-settings');
\OCP\Util::addStyle('federatedtalklink', 'admin-settings');
?>

<div id="app-content">
    <div id="federatedtalklink-admin-settings"></div>
</div>
