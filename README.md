# CakePHP Feature Flags

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue)](https://php.net)
[![CakePHP](https://img.shields.io/badge/CakePHP-5.x-red)](https://cakephp.org)
[![License](https://img.shields.io/github/license/parasBisht/cakephp-feature-flags)](LICENSE)
[![CI](https://github.com/parasBisht/cakephp-feature-flags/actions/workflows/ci.yml/badge.svg)](https://github.com/parasBisht/cakephp-feature-flags/actions/workflows/ci.yml)

This branch is for use with **CakePHP 5.x**. See [version map](https://github.com/parasBisht/feature-flags/wiki#cakephp-version-map) for details.

Database-driven, scope-based feature flag management for **CakePHP 5**.

Unlike config-file approaches, this plugin stores flags in the database, so you can toggle them at runtime without a redeploy.

**Scope** is a plain string — you decide what it means for your app. No user/client/group hierarchy is assumed.

---

## Features

- ✅ Database-driven — toggle flags at runtime without redeploying
- ✅ Scope-based — `'global'`, `'beta'`, `'plan:pro'`, `'tenant:abc'` — you choose
- ✅ Automatic fallback — scope → `'global'` → `false`
- ✅ Optional extra values — store integers, arrays, strings alongside a flag
- ✅ Computed features — register callables that run at evaluation time
- ✅ Request-level cache — no repeated DB hits within a single request
- ✅ CLI commands — enable/disable/list without touching the database directly
- ✅ View Helper — clean, readable template checks
- ✅ DI Container — inject `FeatureService` into controllers and commands

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | `^8.1` |
| CakePHP | `^5.0` |

---

## Installation

```bash
composer require parasbisht/cakephp-feature-flags
```

Load the plugin in `src/Application.php`:

```php
// In Application::bootstrap()
$this->addPlugin(\ParasBisht\FeatureFlags\FeatureFlagsPlugin::class);
```

Run the migration to create the `features` table:

```bash
bin/cake migrations migrate --plugin FeatureFlags
```

---

## Usage

### 1. Via Dependency Injection (recommended)

Register the service in `Application::services()`:

```php
use ParasBisht\FeatureFlags\Service\FeatureService;

public function services(ContainerInterface $container): void
{
    $container->add(FeatureService::class);
}
```

Inject it into a controller:

```php
public function index(FeatureService $features): Response
{
    if ($features->isEnabled('dark_mode')) {
        // dark mode is on globally
    }

    if ($features->for('beta')->isEnabled('new_checkout')) {
        // enabled only for 'beta' scope
    }

    return $this->render();
}
```

### 2. Instantiate directly

```php
use ParasBisht\FeatureFlags\Service\FeatureService;

$features = new FeatureService();
$features->isEnabled('dark_mode');
$features->for('plan:pro')->isEnabled('pdf_export');
```

---

## Scope

Scope is a **plain string**. There is no built-in meaning — use whatever fits your app:

```php
$features->isEnabled('feature');               // defaults to 'global'
$features->for('global')->isEnabled('feature');
$features->for('beta')->isEnabled('feature');
$features->for('plan:pro')->isEnabled('feature');
$features->for('tenant:abc123')->isEnabled('feature');
$features->for('env:staging')->isEnabled('feature');
```

### Resolution order

1. Exact scope record → returned if found  
2. `'global'` record → fallback  
3. `false` → if neither exists

---

## API Reference

### Checking flags

```php
$features->isEnabled('feature_name');                    // bool
$features->isDisabled('feature_name');                   // bool
$features->isEnabledAny(['feature_a', 'feature_b']);     // bool — any enabled
$features->isEnabledAll(['feature_a', 'feature_b']);     // bool — all enabled
$features->getValue('api_limit', 100);                   // mixed — stored value or default
```

### Conditional execution

```php
$features->when(
    'new_ui',
    fn($scope) => $this->renderNew(),   // called when enabled
    fn($scope) => $this->renderOld()    // called when disabled (optional)
);
```

### Managing flags

```php
$features->enable('dark_mode');                  // enable in 'global' scope
$features->for('beta')->enable('new_checkout');  // enable for 'beta' only
$features->enable('api_limit', 1000);            // enable with a value

$features->disable('dark_mode');
$features->for('beta')->disable('new_checkout');

$features->remove('old_feature');                // delete the DB record entirely

$features->copyTo('beta');                       // copy all global flags to 'beta'
$features->copyTo('beta', overwrite: true);

$features->all();                                // all Feature entities for current scope
```

### Fluent scope binding

`for()` returns a **new instance** — the original is not mutated:

```php
$beta = $features->for('beta');
$pro  = $features->for('plan:pro');

$beta->isEnabled('new_ui');   // 'beta' scope
$features->isEnabled('new_ui'); // still 'global'
```

---

## Optional values

Store any JSON-serializable value alongside a flag:

```php
$features->enable('rate_limit', 500);          // integer
$features->enable('theme', 'midnight');        // string
$features->enable('config', ['a' => 1]);       // array

$features->getValue('rate_limit', 100);        // 500
$features->getValue('missing_flag', 'default'); // 'default'
```

---

## Computed features

Register a callable that runs at check time. Takes precedence over DB records:

```php
use ParasBisht\FeatureFlags\Service\FeatureService;

// In Application::bootstrap() or a service provider
FeatureService::define('maintenance_mode', fn(string $scope) => date('H') < 6);
FeatureService::define('weekend_special', fn(string $scope) => date('N') >= 6);

// Can use the scope string in the callable
FeatureService::define('premium_export', function (string $scope) {
    return str_starts_with($scope, 'plan:pro') || str_starts_with($scope, 'plan:enterprise');
});
```

To remove a definition:

```php
FeatureService::undefine('maintenance_mode');
FeatureService::clearDefinitions(); // remove all (useful in tests)
```

---

## View Helper

Load in `src/View/AppView.php`:

```php
public function initialize(): void
{
    $this->loadHelper('FeatureFlags.Feature');
}
```

Use in templates:

```php
<?php if ($this->Feature->isEnabled('dark_mode')): ?>
    <link rel="stylesheet" href="/css/dark.css">
<?php endif; ?>

<?php if ($this->Feature->for('beta')->isEnabled('new_checkout')): ?>
    <!-- new checkout UI -->
<?php endif; ?>

<?php if ($this->Feature->isEnabledAny(['pdf_export', 'csv_export'])): ?>
    <div class="export-options">...</div>
<?php endif; ?>

<?php $limit = $this->Feature->getValue('api_limit', 100); ?>
```

> **Note:** Feature checks in views are for display logic only.  
> Always enforce access control in your controllers.

---

## CLI Commands

```bash
# Enable a feature
bin/cake feature_flags enable dark_mode
bin/cake feature_flags enable dark_mode --scope=beta
bin/cake feature_flags enable api_limit --scope=plan:pro --value=1000
bin/cake feature_flags enable config_block --scope=global --value='{"max":10}'

# Disable a feature
bin/cake feature_flags disable dark_mode
bin/cake feature_flags disable dark_mode --scope=beta

# List features for a scope
bin/cake feature_flags list
bin/cake feature_flags list --scope=beta
```

---

## Testing

Clear definitions between tests to avoid bleed-through:

```php
use ParasBisht\FeatureFlags\Service\FeatureService;

protected function setUp(): void
{
    parent::setUp();
    FeatureService::clearDefinitions();
}
```

---

## Database schema

```sql
CREATE TABLE features (
    id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name     VARCHAR(100) NOT NULL,
    scope    VARCHAR(100) NOT NULL DEFAULT 'global',
    enabled  TINYINT(1)   NOT NULL DEFAULT 0,
    value    TEXT         NULL,
    created  DATETIME     NOT NULL,
    modified DATETIME     NOT NULL,
    UNIQUE KEY UNIQUE_NAME_SCOPE (name, scope),
    KEY IDX_SCOPE (scope)
);
```

---

## License

[MIT](LICENSE) © Paras Bisht
