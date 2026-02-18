<?php
declare(strict_types=1);

namespace ParasBisht\FeatureFlags\Model\Entity;

use Cake\ORM\Entity;

/**
 * Feature Entity
 *
 * @property int $id
 * @property string $name   Feature name, e.g. 'dark_mode', 'checkout_v2'
 * @property string $scope  Free-form scope string, e.g. 'global', 'beta', 'tenant:abc'
 * @property bool $enabled
 * @property mixed $value   Optional extra value (stored as JSON in the DB)
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 */
class Feature extends Entity
{
    /**
     * Fields that can be mass assigned.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'name' => true,
        'scope' => true,
        'enabled' => true,
        'value' => true,
        'created' => true,
        'modified' => true,
    ];

    /**
     * Decode the stored JSON value when reading.
     *
     * @param string|null $value Raw DB value
     * @return mixed
     */
    protected function _getValue(?string $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    /**
     * Encode the value to JSON when setting.
     *
     * @param mixed $value
     * @return string|null
     */
    protected function _setValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode($value) ?: null;
    }
}
