<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Creates the `features` table for the FeatureFlags plugin.
 */
class CreateFeaturesTable extends AbstractMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('features');

        $table
            ->addColumn('name', 'string', [
                'limit' => 100,
                'null' => false,
                'comment' => 'Feature name, e.g. dark_mode, new_checkout',
            ])
            ->addColumn('scope', 'string', [
                'limit' => 100,
                'null' => false,
                'default' => 'global',
                'comment' => 'Scope string, e.g. global, beta, plan:pro',
            ])
            ->addColumn('enabled', 'boolean', [
                'null' => false,
                'default' => false,
            ])
            ->addColumn('value', 'text', [
                'null' => true,
                'default' => null,
                'comment' => 'Optional JSON-encoded value stored alongside the flag',
            ])
            ->addColumn('created', 'datetime', [
                'null' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'null' => false,
            ])
            ->addIndex(['name', 'scope'], [
                'unique' => true,
                'name' => 'UNIQUE_NAME_SCOPE',
            ])
            ->addIndex(['scope'], [
                'name' => 'IDX_SCOPE',
            ])
            ->create();
    }
}
