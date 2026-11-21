<?php
declare(strict_types=1);

namespace OCA\Jugend\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000000Date20261121110000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if ($schema->hasTable('jugend_accounting')) {
            $table = $schema->getTable('jugend_accounting');
            if (!$table->hasColumn('entry_type')) {
                $table->addColumn('entry_type', Types::STRING, ['length' => 16, 'notnull' => true, 'default' => 'payment']);
            }
            if (!$table->hasColumn('event_name')) {
                $table->addColumn('event_name', Types::STRING, ['length' => 255, 'notnull' => false]);
            }
        }
        if (!$schema->hasTable('jugend_accounting_starts')) {
            $table = $schema->createTable('jugend_accounting_starts');
            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'length' => 20,
                'unsigned' => true,
            ]);
            $table->addColumn('user_id', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('start_month', Types::STRING, ['length' => 7, 'notnull' => true]);
            $table->addColumn('updated_by', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('updated_at', Types::INTEGER, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['user_id'], 'jugend_accounting_start_user');
        }
        return $schema;
    }
}
