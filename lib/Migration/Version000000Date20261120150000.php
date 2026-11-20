<?php
declare(strict_types=1);

namespace OCA\Jugend\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Adds a fencing token for provisioning workers.
 *
 * The token is a microsecond timestamp and therefore must use BIGINT rather
 * than the 32-bit INTEGER used for ordinary Unix timestamps.
 */
class Version000000Date20261120150000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('jugend_provisioning')) {
            return null;
        }

        $table = $schema->getTable('jugend_provisioning');
        if (!$table->hasColumn('lease_token')) {
            $table->addColumn('lease_token', Types::BIGINT, [
                'notnull' => false,
                'length' => 20,
                'unsigned' => true,
            ]);
        }
        return $schema;
    }
}
