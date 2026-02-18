<?php
declare(strict_types=1);

namespace ParasBisht\FeatureFlags\Test\TestCase\Model\Table;

use Cake\TestSuite\TestCase;
use ParasBisht\FeatureFlags\Model\Table\FeaturesTable;

/**
 * @covers \ParasBisht\FeatureFlags\Model\Table\FeaturesTable
 */
class FeaturesTableTest extends TestCase
{
    /**
     * @var array<string>
     */
    protected array $fixtures = ['plugin.FeatureFlags.Features'];

    /**
     * @var \ParasBisht\FeatureFlags\Model\Table\FeaturesTable
     */
    protected FeaturesTable $Features;

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->Features = $this->fetchTable('FeatureFlags.Features');
    }

    /**
     * @return void
     */
    public function tearDown(): void
    {
        unset($this->Features);
        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testEnableCreatesRecord(): void
    {
        $result = $this->Features->enable('dark_mode', 'global');
        $this->assertTrue($result);

        $this->assertTrue($this->Features->isEnabled('dark_mode', 'global'));
    }

    /**
     * @return void
     */
    public function testDisableCreatesDisabledRecord(): void
    {
        $this->Features->enable('dark_mode', 'global');
        $this->Features->disable('dark_mode', 'global');

        $this->assertFalse($this->Features->isEnabled('dark_mode', 'global'));
    }

    /**
     * @return void
     */
    public function testScopeFallsBackToGlobal(): void
    {
        $this->Features->enable('dark_mode', 'global');

        // Scope 'beta' has no record — should fall back to 'global'
        $this->assertTrue($this->Features->isEnabled('dark_mode', 'beta'));
    }

    /**
     * @return void
     */
    public function testScopeOverridesGlobal(): void
    {
        $this->Features->enable('dark_mode', 'global');
        $this->Features->disable('dark_mode', 'beta');

        // 'beta' override is disabled even though global is enabled
        $this->assertFalse($this->Features->isEnabled('dark_mode', 'beta'));
        // 'global' still enabled
        $this->assertTrue($this->Features->isEnabled('dark_mode', 'global'));
    }

    /**
     * @return void
     */
    public function testGetValueReturnsStoredValue(): void
    {
        $this->Features->enable('api_limit', 'global', 1000);

        $this->assertSame(1000, $this->Features->getValue('api_limit', 'global'));
    }

    /**
     * @return void
     */
    public function testGetValueReturnsDefaultWhenMissing(): void
    {
        $this->assertSame('default_val', $this->Features->getValue('missing', 'global', 'default_val'));
    }

    /**
     * @return void
     */
    public function testRemoveDeletesRecord(): void
    {
        $this->Features->enable('old_feature', 'global');
        $this->Features->remove('old_feature', 'global');

        $this->assertFalse($this->Features->isEnabled('old_feature', 'global'));
    }

    /**
     * @return void
     */
    public function testCopyScopeCopiesRecords(): void
    {
        $this->Features->enable('feature_a', 'global');
        $this->Features->enable('feature_b', 'global');

        $copied = $this->Features->copyScope('global', 'beta');

        $this->assertSame(2, $copied);
        $this->assertTrue($this->Features->isEnabled('feature_a', 'beta'));
        $this->assertTrue($this->Features->isEnabled('feature_b', 'beta'));
    }

    /**
     * @return void
     */
    public function testCopyScopeDoesNotOverwriteByDefault(): void
    {
        $this->Features->enable('feature_a', 'global');
        $this->Features->disable('feature_a', 'beta'); // already exists, disabled

        $copied = $this->Features->copyScope('global', 'beta');

        $this->assertSame(0, $copied);
        // 'beta' record untouched — still disabled
        $this->assertFalse($this->Features->isEnabled('feature_a', 'beta'));
    }

    /**
     * @return void
     */
    public function testCopyScopeOverwritesWhenRequested(): void
    {
        $this->Features->enable('feature_a', 'global');
        $this->Features->disable('feature_a', 'beta');

        $copied = $this->Features->copyScope('global', 'beta', true);

        $this->assertSame(1, $copied);
        // Now 'beta' inherits enabled from 'global'
        $this->assertTrue($this->Features->isEnabled('feature_a', 'beta'));
    }

    /**
     * @return void
     */
    public function testAllForScopeReturnsOnlyThatScope(): void
    {
        $this->Features->enable('feature_a', 'global');
        $this->Features->enable('feature_b', 'beta');

        $results = $this->Features->allForScope('global');
        $this->assertCount(1, $results);
        $this->assertSame('feature_a', $results[0]->name);
    }

    /**
     * @return void
     */
    public function testJsonValueRoundtrip(): void
    {
        $data = ['limit' => 100, 'tags' => ['a', 'b']];
        $this->Features->enable('complex_feature', 'global', $data);

        $retrieved = $this->Features->getValue('complex_feature', 'global');
        $this->assertSame($data, $retrieved);
    }
}
