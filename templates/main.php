<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Your Name <admin@example.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Main application template
 *
 * This template serves as the mounting point for the Vue.js application
 */

\OCP\Util::addScript('federatedtalklink', 'federatedtalklink-vendors');
\OCP\Util::addScript('federatedtalklink', 'federatedtalklink-main');
?>

<div id="federatedtalklink-app"></div>
