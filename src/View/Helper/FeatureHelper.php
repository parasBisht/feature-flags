<?php
declare(strict_types=1);

namespace ParasBisht\FeatureFlags\View\Helper;

use Cake\View\Helper;
use ParasBisht\FeatureFlags\Service\FeatureService;

/**
 * Feature Helper
 *
 * Provides feature flag checks inside CakePHP templates.
 *
 * Setup in your AppView::initialize():
 *   $this->loadHelper('FeatureFlags.Feature');
 *
 * Usage in templates:
 *   <?php if ($this->Feature->isEnabled('dark_mode')): ?>
 *       <link rel="stylesheet" href="/css/dark.css">
 *   <?php endif; ?>
 *
 *   <?php if ($this->Feature->for('beta')->isEnabled('new_ui')): ?>
 *       ...
 *   <?php endif; ?>
 *
 * Note: Authorization/access control should always be enforced in the
 * controller, not just in the view. Use this helper for UI-level display
 * logic only.
 *
 * @property \Cake\View\Helper\HtmlHelper $Html
 */
class FeatureHelper extends Helper
{
    /**
     * @var \ParasBisht\FeatureFlags\Service\FeatureService
     */
    protected FeatureService $service;

    /**
     * @var string
     */
    protected string $scope = 'global';

    /**
     * @param array<string, mixed> $config
     * @return void
     */
    public function initialize(array $config): void
    {
        $this->service = new FeatureService();
    }

    /**
     * Bind a scope for the next check.
     *
     * @param string $scope
     * @return $this
     */
    public function for(string $scope): static
    {
        $clone = clone $this;
        $clone->scope = $scope;
        $clone->service = $this->service->for($scope);

        return $clone;
    }

    /**
     * Return true if the feature is enabled.
     *
     * @param string $name
     * @return bool
     */
    public function isEnabled(string $name): bool
    {
        return $this->service->isEnabled($name);
    }

    /**
     * Return true if the feature is disabled.
     *
     * @param string $name
     * @return bool
     */
    public function isDisabled(string $name): bool
    {
        return $this->service->isDisabled($name);
    }

    /**
     * Return true if at least one feature is enabled.
     *
     * @param array<string> $names
     * @return bool
     */
    public function isEnabledAny(array $names): bool
    {
        return $this->service->isEnabledAny($names);
    }

    /**
     * Return true only if all features are enabled.
     *
     * @param array<string> $names
     * @return bool
     */
    public function isEnabledAll(array $names): bool
    {
        return $this->service->isEnabledAll($names);
    }

    /**
     * Get the stored value for a feature.
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function getValue(string $name, mixed $default = null): mixed
    {
        return $this->service->getValue($name, $default);
    }
}
