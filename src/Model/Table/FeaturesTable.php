<?php
declare(strict_types=1);

namespace ParasBisht\FeatureFlags\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;
use ParasBisht\FeatureFlags\Model\Entity\Feature;

/**
 * Features Table
 *
 * Stores feature flags as (name, scope) pairs.
 *
 * The `scope` column is a plain string. It has no built-in meaning.
 * You choose what scopes make sense for your app:
 *
 *   'global'       → a catch-all default everyone gets
 *   'beta'         → a named group
 *   'plan:pro'     → a subscription tier
 *   'tenant:abc'   → a multi-tenant identifier
 *
 * Resolution order (most specific wins):
 *   1. Exact scope match
 *   2. 'global' fallback
 *
 * @method \ParasBisht\FeatureFlags\Model\Entity\Feature newEmptyEntity()
 * @method \ParasBisht\FeatureFlags\Model\Entity\Feature newEntity(array $data, array $options = [])
 * @method \ParasBisht\FeatureFlags\Model\Entity\Feature saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \Cake\ORM\ResultSet<\ParasBisht\FeatureFlags\Model\Entity\Feature> findAll()
 */
class FeaturesTable extends Table
{
    /**
     * @param array<string, mixed> $config
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('features');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
    }

    /**
     * @param \Cake\Validation\Validator $validator
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('name')
            ->maxLength('name', 100)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        $validator
            ->scalar('scope')
            ->maxLength('scope', 100)
            ->requirePresence('scope', 'create')
            ->notEmptyString('scope');

        $validator
            ->boolean('enabled')
            ->notEmptyString('enabled');

        $validator
            ->scalar('value')
            ->allowEmptyString('value');

        return $validator;
    }

    /**
     * Enable a feature for a given scope.
     *
     * @param string $name  Feature name
     * @param string $scope Scope string (default: 'global')
     * @param mixed $value  Optional value to store alongside the flag
     * @return bool
     */
    public function enable(string $name, string $scope = 'global', mixed $value = null): bool
    {
        return $this->upsert($name, $scope, true, $value);
    }

    /**
     * Disable a feature for a given scope.
     *
     * @param string $name
     * @param string $scope
     * @return bool
     */
    public function disable(string $name, string $scope = 'global'): bool
    {
        return $this->upsert($name, $scope, false, null);
    }

    /**
     * Remove a feature record for a given scope entirely.
     *
     * After removal, the 'global' scope record (if present) acts as the fallback.
     *
     * @param string $name
     * @param string $scope
     * @return bool
     */
    public function remove(string $name, string $scope = 'global'): bool
    {
        return (bool)$this->deleteAll(['name' => $name, 'scope' => $scope]);
    }

    /**
     * Check whether a feature is enabled.
     *
     * Checks the given scope first, then falls back to 'global'.
     *
     * @param string $name
     * @param string $scope
     * @return bool
     */
    public function isEnabled(string $name, string $scope = 'global'): bool
    {
        // Exact scope lookup (skip if already 'global')
        if ($scope !== 'global') {
            $record = $this->find()
                ->select(['enabled'])
                ->where(['name' => $name, 'scope' => $scope])
                ->first();

            if ($record !== null) {
                return (bool)$record->enabled;
            }
        }

        // Global fallback
        $global = $this->find()
            ->select(['enabled'])
            ->where(['name' => $name, 'scope' => 'global'])
            ->first();

        return $global !== null && (bool)$global->enabled;
    }

    /**
     * Get the stored value for a feature, with optional default.
     *
     * Checks the given scope first, then falls back to 'global'.
     *
     * @param string $name
     * @param string $scope
     * @param mixed $default
     * @return mixed
     */
    public function getValue(string $name, string $scope = 'global', mixed $default = null): mixed
    {
        if ($scope !== 'global') {
            $record = $this->find()
                ->select(['value'])
                ->where(['name' => $name, 'scope' => $scope])
                ->first();

            if ($record !== null) {
                return $record->value ?? $default;
            }
        }

        $global = $this->find()
            ->select(['value'])
            ->where(['name' => $name, 'scope' => 'global'])
            ->first();

        return $global !== null ? ($global->value ?? $default) : $default;
    }

    /**
     * Return all Feature records for a specific scope.
     *
     * @param string $scope
     * @return array<\ParasBisht\FeatureFlags\Model\Entity\Feature>
     */
    public function allForScope(string $scope): array
    {
        return $this->find()
            ->where(['scope' => $scope])
            ->orderBy(['name' => 'ASC'])
            ->all()
            ->toArray();
    }

    /**
     * Copy all feature records from one scope to another.
     *
     * @param string $from      Source scope
     * @param string $to        Target scope
     * @param bool $overwrite   Whether to overwrite existing records in the target scope
     * @return int Number of records actually copied
     */
    public function copyScope(string $from, string $to, bool $overwrite = false): int
    {
        $records = $this->find()->where(['scope' => $from])->all();
        $copied = 0;

        foreach ($records as $record) {
            $exists = $this->exists(['name' => $record->name, 'scope' => $to]);

            if ($exists && !$overwrite) {
                continue;
            }

            $this->upsert($record->name, $to, (bool)$record->enabled, $record->value);
            $copied++;
        }

        return $copied;
    }

    /**
     * Insert or update a (name, scope) record.
     *
     * @param string $name
     * @param string $scope
     * @param bool $enabled
     * @param mixed $value
     * @return bool
     */
    protected function upsert(string $name, string $scope, bool $enabled, mixed $value): bool
    {
        /** @var \ParasBisht\FeatureFlags\Model\Entity\Feature|null $existing */
        $existing = $this->find()
            ->where(['name' => $name, 'scope' => $scope])
            ->first();

        if ($existing !== null) {
            $existing->enabled = $enabled;
            $existing->value = $value;

            return (bool)$this->save($existing);
        }

        $entity = $this->newEntity([
            'name' => $name,
            'scope' => $scope,
            'enabled' => $enabled,
            'value' => $value,
        ]);

        return (bool)$this->save($entity);
    }
}
