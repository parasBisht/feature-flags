<?php
declare(strict_types=1);

namespace ParasBisht\FeatureFlags\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * FeaturesFixture
 */
class FeaturesFixture extends TestFixture
{
    /**
     * @var string
     */
    public string $connection = 'test';

    /**
     * @var array<string, mixed>
     */
    public array $fields = [
        'id' => ['type' => 'integer', 'unsigned' => true, 'autoIncrement' => true],
        'name' => ['type' => 'string', 'length' => 100, 'null' => false],
        'scope' => ['type' => 'string', 'length' => 100, 'null' => false, 'default' => 'global'],
        'enabled' => ['type' => 'boolean', 'null' => false, 'default' => false],
        'value' => ['type' => 'text', 'null' => true, 'default' => null],
        'created' => ['type' => 'datetime', 'null' => false],
        'modified' => ['type' => 'datetime', 'null' => false],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
            'UNIQUE_NAME_SCOPE' => ['type' => 'unique', 'columns' => ['name', 'scope']],
        ],
    ];

    /**
     * Empty — tests populate their own data.
     *
     * @var array<mixed>
     */
    public array $records = [];
}
