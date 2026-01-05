<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Your Name <admin@example.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FederatedTalkLink\Service;

use OCA\FederatedTalkLink\AppInfo\Application;
use OCP\IConfig;
use OCP\Security\ICrypto;

/**
 * Service for managing app settings
 *
 * Handles storage and retrieval of configuration values,
 * including secure storage of credentials
 */
class SettingsService
{
    private const CONFIG_EXTERNAL_SERVER = 'external_server_url';
    private const CONFIG_USERNAME = 'auth_username';
    private const CONFIG_PASSWORD = 'auth_password_encrypted';
    private const CONFIG_TARGET_URL = 'target_nextcloud_url';

    public function __construct(
        private IConfig $config,
        private ICrypto $crypto
    ) {
    }

    /**
     * Get the external server URL (API server)
     *
     * @return string The external server URL or empty string if not set
     */
    public function getExternalServerUrl(): string
    {
        return $this->config->getAppValue(
            Application::APP_ID,
            self::CONFIG_EXTERNAL_SERVER,
            ''
        );
    }

    /**
     * Set the external server URL
     *
     * @param string $url The external server URL
     */
    public function setExternalServerUrl(string $url): void
    {
        // Normalize URL - remove trailing slash
        $url = rtrim(trim($url), '/');
        $this->config->setAppValue(
            Application::APP_ID,
            self::CONFIG_EXTERNAL_SERVER,
            $url
        );
    }

    /**
     * Get the authentication username
     *
     * @return string The username or empty string if not set
     */
    public function getUsername(): string
    {
        return $this->config->getAppValue(
            Application::APP_ID,
            self::CONFIG_USERNAME,
            ''
        );
    }

    /**
     * Set the authentication username
     *
     * @param string $username The username
     */
    public function setUsername(string $username): void
    {
        $this->config->setAppValue(
            Application::APP_ID,
            self::CONFIG_USERNAME,
            trim($username)
        );
    }

    /**
     * Get the decrypted authentication password
     *
     * @return string The password or empty string if not set
     */
    public function getPassword(): string
    {
        $encrypted = $this->config->getAppValue(
            Application::APP_ID,
            self::CONFIG_PASSWORD,
            ''
        );

        if (empty($encrypted)) {
            return '';
        }

        try {
            return $this->crypto->decrypt($encrypted);
        } catch (\Exception $e) {
            // If decryption fails, return empty string
            return '';
        }
    }

    /**
     * Set and encrypt the authentication password
     *
     * @param string $password The password to store
     */
    public function setPassword(string $password): void
    {
        if (empty($password)) {
            $this->config->setAppValue(
                Application::APP_ID,
                self::CONFIG_PASSWORD,
                ''
            );
            return;
        }

        $encrypted = $this->crypto->encrypt($password);
        $this->config->setAppValue(
            Application::APP_ID,
            self::CONFIG_PASSWORD,
            $encrypted
        );
    }

    /**
     * Get the target Nextcloud URL (for generated links)
     *
     * @return string The target URL or empty string if not set
     */
    public function getTargetNextcloudUrl(): string
    {
        return $this->config->getAppValue(
            Application::APP_ID,
            self::CONFIG_TARGET_URL,
            ''
        );
    }

    /**
     * Set the target Nextcloud URL
     *
     * @param string $url The target Nextcloud URL
     */
    public function setTargetNextcloudUrl(string $url): void
    {
        // Normalize URL - remove trailing slash
        $url = rtrim(trim($url), '/');
        $this->config->setAppValue(
            Application::APP_ID,
            self::CONFIG_TARGET_URL,
            $url
        );
    }

    /**
     * Check if the app is configured
     *
     * @return bool True if all required settings are present
     */
    public function isConfigured(): bool
    {
        return !empty($this->getExternalServerUrl())
            && !empty($this->getUsername())
            && !empty($this->getPassword())
            && !empty($this->getTargetNextcloudUrl());
    }

    /**
     * Get all settings as an array (password masked)
     *
     * @return array<string, mixed> The settings array
     */
    public function getAllSettings(): array
    {
        return [
            'externalServerUrl' => $this->getExternalServerUrl(),
            'username' => $this->getUsername(),
            'hasPassword' => !empty($this->getPassword()),
            'targetNextcloudUrl' => $this->getTargetNextcloudUrl(),
            'isConfigured' => $this->isConfigured(),
        ];
    }

    /**
     * Save all settings at once
     *
     * @param string $externalServerUrl The external server URL
     * @param string $username The authentication username
     * @param string|null $password The password (null to keep existing)
     * @param string $targetNextcloudUrl The target Nextcloud URL
     */
    public function saveAllSettings(
        string $externalServerUrl,
        string $username,
        ?string $password,
        string $targetNextcloudUrl
    ): void {
        $this->setExternalServerUrl($externalServerUrl);
        $this->setUsername($username);

        // Only update password if provided (not null and not empty placeholder)
        if ($password !== null && $password !== '') {
            $this->setPassword($password);
        }

        $this->setTargetNextcloudUrl($targetNextcloudUrl);
    }
}
