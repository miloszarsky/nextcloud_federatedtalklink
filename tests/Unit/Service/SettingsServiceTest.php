<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Your Name <admin@example.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FederatedTalkLink\Tests\Unit\Service;

use OCA\FederatedTalkLink\Service\SettingsService;
use OCP\IConfig;
use OCP\Security\ICrypto;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SettingsServiceTest extends TestCase
{
    private SettingsService $service;
    private IConfig&MockObject $config;
    private ICrypto&MockObject $crypto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = $this->createMock(IConfig::class);
        $this->crypto = $this->createMock(ICrypto::class);

        $this->service = new SettingsService(
            $this->config,
            $this->crypto
        );
    }

    public function testGetExternalServerUrl(): void
    {
        $this->config->expects($this->once())
            ->method('getAppValue')
            ->with('federatedtalklink', 'external_server_url', '')
            ->willReturn('ext.example.com');

        $result = $this->service->getExternalServerUrl();

        $this->assertEquals('ext.example.com', $result);
    }

    public function testSetExternalServerUrl(): void
    {
        $this->config->expects($this->once())
            ->method('setAppValue')
            ->with('federatedtalklink', 'external_server_url', 'ext.example.com');

        $this->service->setExternalServerUrl('ext.example.com/');
    }

    public function testIsConfiguredReturnsFalseWhenNotConfigured(): void
    {
        $this->config->method('getAppValue')
            ->willReturn('');

        $result = $this->service->isConfigured();

        $this->assertFalse($result);
    }

    public function testIsConfiguredReturnsTrueWhenFullyConfigured(): void
    {
        $this->config->method('getAppValue')
            ->willReturnCallback(function ($appId, $key, $default) {
                return match ($key) {
                    'external_server_url' => 'ext.example.com',
                    'auth_username' => 'guest',
                    'auth_password_encrypted' => 'encrypted_password',
                    'target_nextcloud_url' => 'nextcloud.example.com',
                    default => $default,
                };
            });

        $this->crypto->method('decrypt')
            ->willReturn('password');

        $result = $this->service->isConfigured();

        $this->assertTrue($result);
    }
}
