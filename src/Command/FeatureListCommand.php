<?php
declare(strict_types=1);

namespace ParasBisht\FeatureFlags\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use ParasBisht\FeatureFlags\Service\FeatureService;

/**
 * List feature flags for a given scope.
 *
 * Usage:
 *   bin/cake feature_flags list
 *   bin/cake feature_flags list --scope=beta
 */
class FeatureListCommand extends Command
{
    /**
     * @return string
     */
    public static function defaultName(): string
    {
        return 'feature_flags list';
    }

    /**
     * @param \Cake\Console\ConsoleOptionParser $parser
     * @return \Cake\Console\ConsoleOptionParser
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('List all feature flags for a scope.')
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
        $scope = (string)$args->getOption('scope');
        $service = (new FeatureService())->for($scope);
        $features = $service->all();

        if (empty($features)) {
            $io->info("No feature flags found for scope '{$scope}'.");

            return static::CODE_SUCCESS;
        }

        $io->info("Feature flags for scope: {$scope}");
        $io->hr();

        $rows = [['Name', 'Enabled', 'Value']];
        foreach ($features as $feature) {
            $rows[] = [
                $feature->name,
                $feature->enabled ? '<success>yes</success>' : '<error>no</error>',
                $feature->value !== null ? json_encode($feature->value) : '-',
            ];
        }

        $io->helper('Table')->output($rows);

        return static::CODE_SUCCESS;
    }
}
