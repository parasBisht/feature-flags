<?php
declare(strict_types=1);

namespace ParasBisht\FeatureFlags\Test\TestCase\Service;

use Cake\TestSuite\TestCase;
use ParasBisht\FeatureFlags\Service\FeatureService;

/**
 * @covers \ParasBisht\FeatureFlags\Service\FeatureService
 */
class FeatureServiceTest extends TestCase
{
    /**
     * @var array<string>
     */
    protected array $fixtures = ['plugin.FeatureFlags.Features'];

    /**
     * @var \ParasBisht\FeatureFlags\Service\FeatureService
     */
    protected FeatureService $service;

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        FeatureService::clearDefinitions();
        $this->service = new FeatureService();
    }

    /**
     * @return void
     */
    public function tearDown(): void
    {
        FeatureService::clearDefinitions();
        unset($this->service);
        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testIsEnabledReturnsFalseByDefault(): void
    {
        $this->assertFalse($this->service->isEnabled('nonexistent'));
    }

    /**
     * @return void
     */
    public function testIsDisabledReturnsTrueByDefault(): void
    {
        $this->assertTrue($this->service->isDisabled('nonexistent'));
    }

    /**
     * @return void
     */
    public function testEnableAndCheck(): void
    {
        $this->service->enable('dark_mode');
        $this->assertTrue($this->service->isEnabled('dark_mode'));
    }

    /**
     * @return void
     */
    public function testDisable(): void
    {
        $this->service->enable('dark_mode');
        $this->service->disable('dark_mode');
        $this->assertFalse($this->service->isEnabled('dark_mode'));
    }

    /**
     * @return void
     */
    public function testForReturnsScopedInstance(): void
    {
        $scoped = $this->service->for('beta');
        $this->assertNotSame($this->service, $scoped);
        $this->assertSame('beta', $scoped->getScope());
        $this->assertSame('global', $this->service->getScope());
    }

    /**
     * @return void
     */
    public function testScopedCheck(): void
    {
        // global enabled, beta disabled
        $this->service->enable('feature_x');
        $this->service->for('beta')->disable('feature_x');

        $this->assertTrue($this->service->isEnabled('feature_x'));
        $this->assertFalse($this->service->for('beta')->isEnabled('feature_x'));
    }

    /**
     * @return void
     */
    public function testIsEnabledAny(): void
    {
        $this->service->enable('feature_a');

        $this->assertTrue($this->service->isEnabledAny(['feature_a', 'feature_b']));
        $this->assertFalse($this->service->isEnabledAny(['feature_b', 'feature_c']));
    }

    /**
     * @return void
     */
    public function testIsEnabledAll(): void
    {
        $this->service->enable('feature_a');
        $this->service->enable('feature_b');

        $this->assertTrue($this->service->isEnabledAll(['feature_a', 'feature_b']));
        $this->assertFalse($this->service->isEnabledAll(['feature_a', 'feature_c']));
    }

    /**
     * @return void
     */
    public function testGetValue(): void
    {
        $this->service->enable('api_limit', 500);
        $this->assertSame(500, $this->service->getValue('api_limit'));
    }

    /**
     * @return void
     */
    public function testGetValueDefault(): void
    {
        $this->assertSame(42, $this->service->getValue('missing', 42));
    }

    /**
     * @return void
     */
    public function testRemove(): void
    {
        $this->service->enable('temp_feature');
        $this->service->remove('temp_feature');
        $this->assertFalse($this->service->isEnabled('temp_feature'));
    }

    /**
     * @return void
     */
    public function testWhenCallsCallbackWhenEnabled(): void
    {
        $this->service->enable('my_feature');

        $called = false;
        $this->service->when('my_feature', function () use (&$called) {
            $called = true;
        });

        $this->assertTrue($called);
    }

    /**
     * @return void
     */
    public function testWhenCallsFallbackWhenDisabled(): void
    {
        $this->service->disable('my_feature');

        $fallbackCalled = false;
        $this->service->when(
            'my_feature',
            fn() => null,
            function () use (&$fallbackCalled) {
                $fallbackCalled = true;
            }
        );

        $this->assertTrue($fallbackCalled);
    }

    /**
     * @return void
     */
    public function testComputedFeatureTakesPrecedence(): void
    {
        // DB says disabled
        $this->service->disable('computed_feature');

        // Computed definition overrides and returns true
        FeatureService::define('computed_feature', fn($scope) => true);

        $this->assertTrue($this->service->isEnabled('computed_feature'));
    }

    /**
     * @return void
     */
    public function testComputedFeatureReceivesScope(): void
    {
        $receivedScope = null;

        FeatureService::define('scope_test', function (string $scope) use (&$receivedScope) {
            $receivedScope = $scope;

            return true;
        });

        $this->service->for('plan:pro')->isEnabled('scope_test');

        $this->assertSame('plan:pro', $receivedScope);
    }

    /**
     * @return void
     */
    public function testUndefineRemovesComputedFeature(): void
    {
        FeatureService::define('temp_computed', fn($scope) => true);
        FeatureService::undefine('temp_computed');

        // Now falls back to DB (not defined there = false)
        $this->assertFalse($this->service->isEnabled('temp_computed'));
    }

    /**
     * @return void
     */
    public function testCopyTo(): void
    {
        $this->service->enable('feature_a');
        $this->service->enable('feature_b');

        $copied = $this->service->copyTo('beta');

        $this->assertSame(2, $copied);
        $this->assertTrue($this->service->for('beta')->isEnabled('feature_a'));
    }
}
