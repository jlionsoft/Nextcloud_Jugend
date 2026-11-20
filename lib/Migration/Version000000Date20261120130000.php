<?php
declare(strict_types=1);

namespace OCA\Jugend\Migration;

use Closure;
use OCP\DB\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Allows multiple membership intervals for the same user and work group.
 * This is required to preserve the full membership history.
 */
class Version000000Date20261120130000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('jugend_members')) {
            return null;
        }

        $table = $schema->getTable('jugend_members');
        if ($table->hasIndex('jugend_member_group_user')) {
            $table->dropIndex('jugend_member_group_user');
        }
        if (!$table->hasIndex('jugend_member_history_lookup')) {
            $table->addIndex(['group_id', 'user_id', 'left_at'], 'jugend_member_history_lookup');
        }
        return $schema;
    }
}
