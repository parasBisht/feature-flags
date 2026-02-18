<?php
declare(strict_types=1);

namespace ParasBisht\FeatureFlags\Service;

use Cake\ORM\Locator\LocatorAwareTrait;
use ParasBisht\FeatureFlags\Model\Table\FeaturesTable;

/**
 * FeatureService
 *
 * The primary API for checking and managing feature flags.
 *
 * ---
 * SCOPES
 * Scopes are plain strings with no built-in meaning. You decide:
 *
 *   'global'       → everyone (used as the fallback)
 *   'beta'         → a named opt-in group
 *   'plan:pro'     → a subscription tier
 *   'tenant:abc'   → a specific tenant in a multi-tenant app
 *
 * Resolution order:
 *   1. Exact scope record  → used if found
 *   2. 'global' record     → fallback
 *   3. $default parameter  → final fallback (false for isEnabled)
 *
 * ---
 * COMPUTED FEATURES
 * Register a callable via FeatureService::define().
 * It is evaluated at runtime and takes precedence over the database.
 *
 * ---
 * USAGE
 *
 *   // Via DI injection (recommended)
 *   public function index(FeatureService $features): Response
 *   {
 *       if ($features->isEnabled('dark_mode')) { ... }
 *       if ($features->for('beta')->isEnabled('new_checkout')) { ... }
 *   }
 *
 *   // Fluent scope binding
 *   $features->for('plan:pro')->isEnabled('export_pdf');
 *   $features->for('beta')->enable('new_dashboard');
 */
class FeatureService
{
    use LocatorAwareTrait;

    /**
     * Computed feature definitions: ['feature_name' => callable]
     *
     * @var array<string, callable>
     */
    private static array $definitions = [];

    /**
     * Request-level in-memory cache: ['feature:scope' => bool]
     *
     * @var array<string, bool>
     */
    private array $cache = [];

    /**
     * Active scope for this instance.
     */
    private string $scope = 'global';

    /**
     * @var \ParasBisht\FeatureFlags\Model\Table\FeaturesTable
     */
    private FeaturesTable $table;

    public function __construct()
    {
        /** @var \ParasBisht\FeatureFlags\Model\Table\FeaturesTable $table */
        $table = $this->fetchTable('FeatureFlags.Features');
        $this->table = $table;
    }

    // =========================================================================
    // Fluent scope binding
    // =========================================================================

    /**
     * Return a new instance scoped to the given string.
     *
     * The current instance is not mutated.
     *
     * Example:
     *   $features->for('beta')->isEnabled('new_ui');
     *   $features->for('plan:pro')->isEnabled('pdf_export');
     *
     * @param string $scope
     * @return static
     */
    public function for(string $scope): static
    {
        $clone = clone $this;
        $clone->scope = $scope;

        return $clone;
    }

    /**
     * Return the active scope string.
     *
     * @return string
     */
    public function getScope(): string
    {
        return $this->scope;
    }

    // =========================================================================
    // Checking features
    // =========================================================================

    /**
     * Check whether a feature is enabled for the current scope.
     *
     * @param string $name Feature name
     * @return bool
     */
    public function isEnabled(string $name): bool
    {
        // Computed features always take precedence
        if (isset(self::$definitions[$name])) {
            return (bool)(self::$definitions[$name])($this->scope);
        }

        $cacheKey = "{$name}:{$this->scope}";
        if (!array_key_exists($cacheKey, $this->cache)) {
            $this->cache[$cacheKey] = $this->table->isEnabled($name, $this->scope);
        }

        return $this->cache[$cacheKey];
    }

    /**
     * Check whether a feature is disabled for the current scope.
     *
     * @param string $name
     * @return bool
     */
    public function isDisabled(string $name): bool
    {
        return !$this->isEnabled($name);
    }

    /**
     * Return true if at least one of the given features is enabled.
     *
     * @param array<string> $names
     * @return bool
     */
    public function isEnabledAny(array $names): bool
    {
        foreach ($names as $name) {
            if ($this->isEnabled($name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return true only when every one of the given features is enabled.
     *
     * @param array<string> $names
     * @return bool
     */
    public function isEnabledAll(array $names): bool
    {
        foreach ($names as $name) {
            if (!$this->isEnabled($name)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the stored value for a feature.
     *
     * Falls back to the 'global' scope, then to $default.
     *
     * @param string $name
     * @param mixed $default Returned when no value is stored
     * @return mixed
     */
    public function getValue(string $name, mixed $default = null): mixed
    {
        return $this->table->getValue($name, $this->scope, $default);
    }

    /**
     * Execute $callback only when the feature is enabled.
     *
     * Optionally execute $fallback when disabled.
     *
     * Example:
     *   $features->when('new_ui', fn() => $this->renderNew(), fn() => $this->renderOld());
     *
     * @param string $name
     * @param callable $callback  Called with ($scope) when the feature is enabled
     * @param callable|null $fallback Called with ($scope) when disabled (optional)
     * @return mixed
     */
    public function when(string $name, callable $callback, ?callable $fallback = null): mixed
    {
        if ($this->isEnabled($name)) {
            return $callback($this->scope);
        }

        return $fallback !== null ? $fallback($this->scope) : null;
    }

    // =========================================================================
    // Managing features
    // =========================================================================

    /**
     * Enable a feature for the current scope.
     *
     * @param string $name
     * @param mixed $value Optional value to store alongside the flag
     * @return bool
     */
    public function enable(string $name, mixed $value = null): bool
    {
        $result = $this->table->enable($name, $this->scope, $value);
        $this->clearCache($name);

        return $result;
    }

    /**
     * Disable a feature for the current scope.
     *
     * @param string $name
     * @return bool
     */
    public function disable(string $name): bool
    {
        $result = $this->table->disable($name, $this->scope);
        $this->clearCache($name);

        return $result;
    }

    /**
     * Remove the feature record for the current scope entirely.
     *
     * After removal, the 'global' scope record acts as the fallback again.
     *
     * @param string $name
     * @return bool
     */
    public function remove(string $name): bool
    {
        $result = $this->table->remove($name, $this->scope);
        $this->clearCache($name);

        return $result;
    }

    /**
     * Copy all features from the current scope to another scope.
     *
     * @param string $toScope   Target scope
     * @param bool $overwrite   Whether to overwrite existing records in the target scope
     * @return int Number of records copied
     */
    public function copyTo(string $toScope, bool $overwrite = false): int
    {
        return $this->table->copyScope($this->scope, $toScope, $overwrite);
    }

    /**
     * Return all features stored for the current scope.
     *
     * @return array<\ParasBisht\FeatureFlags\Model\Entity\Feature>
     */
    public function all(): array
    {
        return $this->table->allForScope($this->scope);
    }

    // =========================================================================
    // Computed / programmatic features
    // =========================================================================

    /**
     * Register a computed feature (takes precedence over DB records).
     *
     * The callable receives the active scope string and must return bool.
     *
     * Examples:
     *   FeatureService::define('maintenance', fn($scope) => date('H') < 6);
     *   FeatureService::define('weekend_mode', fn($scope) => date('N') >= 6);
     *
     * @param string $name
     * @param callable $resolver fn(string $scope): bool
     * @return void
     */
    public static function define(string $name, callable $resolver): void
    {
        self::$definitions[$name] = $resolver;
    }

    /**
     * Remove a previously defined computed feature.
     *
     * @param string $name
     * @return void
     */
    public static function undefine(string $name): void
    {
        unset(self::$definitions[$name]);
    }

    /**
     * Clear all computed feature definitions (useful in tests).
     *
     * @return void
     */
    public static function clearDefinitions(): void
    {
        self::$definitions = [];
    }

    // =========================================================================
    // Internal helpers
    // =========================================================================

    /**
     * Clear the in-memory cache for a specific feature name.
     *
     * @param string $name
     * @return void
     */
    private function clearCache(string $name): void
    {
        foreach (array_keys($this->cache) as $key) {
            if (str_starts_with($key, "{$name}:")) {
                unset($this->cache[$key]);
            }
        }
    }
}
