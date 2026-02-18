<?php
declare(strict_types=1);

namespace ParasBisht\FeatureFlags\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use ParasBisht\FeatureFlags\Service\FeatureService;

/**
 * Disable a feature flag via the CLI.
 *
 * Usage:
 *   bin/cake feature_flags disable dark_mode
 *   bin/cake feature_flags disable dark_mode --scope=beta
 */
class FeatureDisableCommand extends Command
{
    /**
     * @return string
     */
    public static function defaultName(): string
    {
        return 'feature_flags disable';
    }

    /**
     * @param \Cake\Console\ConsoleOptionParser $parser
     * @return \Cake\Console\ConsoleOptionParser
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Disable a feature flag.')
            ->addArgument('name', [
                'help' => 'The feature name to disable.',
                'required' => true,
            ])
            ->addOption('scope', [
                'short' => 's',
                'help' => 'Scope string (default: global).',
                'default' => 'global',
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

        $service = (new FeatureService())->for($scope);
        $result = $service->disable($name);

        if ($result) {
            $io->success("Feature '{$name}' disabled for scope '{$scope}'.");

            return static::CODE_SUCCESS;
        }

        $io->error("Failed to disable feature '{$name}' for scope '{$scope}'.");

        return static::CODE_ERROR;
    }
}
