<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Your Name <admin@example.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FederatedTalkLink\Settings;

use OCA\FederatedTalkLink\AppInfo\Application;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

/**
 * Admin section for the settings page
 */
class AdminSection implements IIconSection
{
    public function __construct(
        private IURLGenerator $urlGenerator,
        private IL10N $l
    ) {
    }

    /**
     * Get the section ID
     *
     * @return string
     */
    public function getID(): string
    {
        return Application::APP_ID;
    }

    /**
     * Get the section name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->l->t('Federated Talk Link');
    }

    /**
     * Get the section priority
     *
     * @return int
     */
    public function getPriority(): int
    {
        return 80;
    }

    /**
     * Get the section icon
     *
     * @return string
     */
    public function getIcon(): string
    {
        return $this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg');
    }
}
