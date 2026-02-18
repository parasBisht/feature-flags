<?php
declare(strict_types=1);

namespace ParasBisht\FeatureFlags\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use ParasBisht\FeatureFlags\Service\FeatureService;

/**
 * Enable a feature flag via the CLI.
 *
 * Usage:
 *   bin/cake feature_flags enable dark_mode
 *   bin/cake feature_flags enable dark_mode --scope=beta
 *   bin/cake feature_flags enable api_limit --scope=plan:pro --value=1000
 */
class FeatureEnableCommand extends Command
{
    /**
     * @return string
     */
    public static function defaultName(): string
    {
        return 'feature_flags enable';
    }

    /**
     * @param \Cake\Console\ConsoleOptionParser $parser
     * @return \Cake\Console\ConsoleOptionParser
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Enable a feature flag.')
            ->addArgument('name', [
                'help' => 'The feature name to enable.',
                'required' => true,
            ])
            ->addOption('scope', [
                'short' => 's',
                'help' => 'Scope string (default: global).',
                'default' => 'global',
            ])
            ->addOption('value', [
                'short' => 'v',
                'help' => 'Optional JSON value to store alongside the flag.',
                'default' => null,
            ]);

        return $parser;
    }

    /**
     * @param \Cake\Console\Arguments $args
     * @param \Cake\Console\ConsoleIo $io
     * @return int
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $name = (string)$args->getArgument('name');
        $scope = (string)$args->getOption('scope');
        $rawValue = $args->getOption('value');

        $value = null;
        if ($rawValue !== null) {
            $decoded = json_decode((string)$rawValue, true);
            $value = json_last_error() === JSON_ERROR_NONE ? $decoded : $rawValue;
        }

        $service = (new FeatureService())->for($scope);
        $result = $service->enable($name, $value);

        if ($result) {
            $io->success("Feature '{$name}' enabled for scope '{$scope}'.");

            return static::CODE_SUCCESS;
        }

        $io->error("Failed to enable feature '{$name}' for scope '{$scope}'.");

        return static::CODE_ERROR;
    }
}
