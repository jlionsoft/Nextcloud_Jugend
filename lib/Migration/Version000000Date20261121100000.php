<?php
declare(strict_types=1);

namespace OCA\Jugend\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000000Date20261121100000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('jugend_accounting')) {
            $table = $schema->createTable('jugend_accounting');
            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'length' => 20,
                'unsigned' => true,
            ]);
            $table->addColumn('user_id', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('amount_cents', Types::BIGINT, ['notnull' => true, 'length' => 20]);
            $table->addColumn('description', Types::STRING, ['length' => 255, 'notnull' => false]);
            $table->addColumn('booked_at', Types::INTEGER, ['notnull' => true]);
            $table->addColumn('created_by', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('created_at', Types::INTEGER, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['user_id', 'booked_at'], 'jugend_accounting_user');
            $table->addIndex(['created_at'], 'jugend_accounting_created');
        }
        return $schema;
    }
}
