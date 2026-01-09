<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Your Name <admin@example.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FederatedTalkLink\AppInfo;

use OCA\FederatedTalkLink\Listener\LoadTalkIntegrationListener;
use OCA\FederatedTalkLink\Service\FederatedLinkService;
use OCA\FederatedTalkLink\Service\SettingsService;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\Mail\IMailer;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;

/**
 * Application class for FederatedTalkLink
 *
 * Handles bootstrapping and dependency injection registration
 */
class Application extends App implements IBootstrap
{
    public const APP_ID = 'federatedtalklink';

    public function __construct(array $urlParams = [])
    {
        parent::__construct(self::APP_ID, $urlParams);
    }

    /**
     * Register services and dependencies
     */
    public function register(IRegistrationContext $context): void
    {
        // Register the SettingsService
        $context->registerService(SettingsService::class, function ($c) {
            return new SettingsService(
                $c->get(IConfig::class),
                $c->get(ICrypto::class)
            );
        });

        // Register the FederatedLinkService
        $context->registerService(FederatedLinkService::class, function ($c) {
            return new FederatedLinkService(
                $c->get(SettingsService::class),
                $c->get(IClientService::class),
                $c->get(LoggerInterface::class),
                $c->get(IMailer::class)
            );
        });

        // Register event listener for Talk integration
        $context->registerEventListener(
            BeforeTemplateRenderedEvent::class,
            LoadTalkIntegrationListener::class
        );
    }

    /**
     * Boot the application
     */
    public function boot(IBootContext $context): void
    {
        // Additional boot logic can be added here if needed
        // For example, registering event listeners or navigation entries
    }
}
