<?php
declare(strict_types=1);

namespace OCA\\Jugend\\Migration;

use Closure;
use OCP\\DB\\ISchemaWrapper;
use OCP\\DB\\Types;
use OCP\\Migration\\IOutput;
use OCP\\Migration\\SimpleMigrationStep;

/**
 * Stores durable per-group provisioning state so failed resources can be retried
 * without losing progress or creating duplicate resource records.
 */
class Version000000Date20261120140000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if ($schema->hasTable('jugend_provisioning')) {
            return null;
        }

        $table = $schema->createTable('jugend_provisioning');
        $table->addColumn('id', Types::BIGINT, [
            'autoincrement' => true,
            'notnull' => true,
            'length' => 20,
            'unsigned' => true,
        ]);
        $table->addColumn('period_id', Types::BIGINT, ['notnull' => true, 'length' => 20, 'unsigned' => true]);
        $table->addColumn('group_id', Types::BIGINT, ['notnull' => true, 'length' => 20, 'unsigned' => true]);
        $table->addColumn('resource_type', Types::STRING, ['length' => 16, 'notnull' => true]);
        $table->addColumn('status', Types::STRING, ['length' => 16, 'notnull' => true, 'default' => 'pending']);
        $table->addColumn('attempts', Types::INTEGER, ['notnull' => true, 'default' => 0]);
        $table->addColumn('last_error', Types::TEXT, ['notnull' => false]);
        $table->addColumn('created_at', Types::INTEGER, ['notnull' => true]);
        $table->addColumn('updated_at', Types::INTEGER, ['notnull' => true]);
        $table->addColumn('started_at', Types::INTEGER, ['notnull' => false]);
        $table->addColumn('finished_at', Types::INTEGER, ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['period_id', 'group_id', 'resource_type'], 'jugend_provisioning_target');
        $table->addIndex(['period_id', 'status'], 'jugend_provisioning_period');
        $table->addIndex(['status', 'updated_at'], 'jugend_provisioning_retry');
        return $schema;
    }
}
