<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;

// Load CakePHP core
$findRoot = function (string $root): string {
    do {
        $lastRoot = $root;
        $root = dirname($root);
        if (is_dir($root . '/vendor/cakephp/cakephp')) {
            return $root;
        }
    } while ($root !== $lastRoot);
    throw new RuntimeException('Cannot find the root of the project, unable to run tests.');
};
$root = $findRoot(__FILE__);
unset($findRoot);

chdir($root);

require_once $root . '/vendor/autoload.php';

define('ROOT', $root);
define('APP_DIR', 'src');
define('APP', rtrim(sys_get_temp_dir(), DS) . DS . 'cakephp-feature-flags' . DS . 'src' . DS);
define('TMP', sys_get_temp_dir() . DS . 'cakephp-feature-flags' . DS . 'tmp' . DS);
define('LOGS', TMP . 'logs' . DS);
define('CACHE', TMP . 'cache' . DS);
define('CONFIG', ROOT . DS . 'config' . DS);

foreach ([TMP, LOGS, CACHE . 'models', CACHE . 'persistent', CACHE . 'views'] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0770, true);
    }
}

Configure::write('debug', true);
Configure::write('App', [
    'namespace' => 'App',
    'encoding' => 'UTF-8',
    'base' => false,
    'baseUrl' => false,
    'dir' => APP_DIR,
    'webroot' => 'webroot',
    'wwwRoot' => WWW_ROOT ?? '/webroot/',
    'fullBaseUrl' => 'http://localhost',
    'imageBaseUrl' => 'img/',
    'jsBaseUrl' => 'js/',
    'cssBaseUrl' => 'css/',
    'paths' => [
        'plugins' => [ROOT . DS . 'plugins' . DS],
        'templates' => [ROOT . DS . 'templates' . DS],
        'locales' => [ROOT . DS . 'resources' . DS . 'locales' . DS],
    ],
]);

// Database connection — uses environment variable or SQLite fallback for tests
$dbUrl = env('DB_URL', 'sqlite:///' . TMP . 'feature_flags_test.sqlite');
ConnectionManager::setConfig('default', ['url' => $dbUrl]);
ConnectionManager::setConfig('test', ['url' => $dbUrl]);

// Load the plugin
Cake\Core\Plugin::getCollection()->add(new \ParasBisht\FeatureFlags\FeatureFlagsPlugin());
