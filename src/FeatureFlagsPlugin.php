<?php
declare(strict_types=1);

namespace ParasBisht\FeatureFlags;

use Cake\Core\BasePlugin;
use Cake\Core\ContainerInterface;
use Cake\Core\PluginApplicationInterface;
use ParasBisht\FeatureFlags\Service\FeatureService;

/**
 * CakePHP Feature Flags Plugin
 *
 * Database-driven, scope-based feature flag management for CakePHP 5.
 * Scope is a plain string — you decide what it means for your application.
 */
class FeatureFlagsPlugin extends BasePlugin
{
    /**
     * @var string
     */
    protected string $name = 'FeatureFlags';

    /**
     * @var bool
     */
    protected bool $bootstrapEnabled = false;

    /**
     * @var bool
     */
    protected bool $routesEnabled = false;

    /**
     * @var bool
     */
    protected bool $migrationsEnabled = true;

    /**
     * Register plugin services into the DI container.
     *
     * This allows FeatureService to be injected into controllers, commands, etc.
     *
     * @param \Cake\Core\ContainerInterface $container
     * @return void
     */
    public function services(ContainerInterface $container): void
    {
        $container->add(FeatureService::class);
    }

    /**
     * @param \Cake\Core\PluginApplicationInterface $app
     * @return void
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);
    }
}
