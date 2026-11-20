<?php
declare(strict_types=1);

namespace OCA\\Jugend\\Migration;

use Closure;
use OCP\\DB\\Types;
use OCP\\IDBConnection;
use OCP\\Migration\\IOutput;
use OCP\\Migration\\SimpleMigrationStep;
use OCP\\DB\\ISchemaWrapper;

/**
 * Creates the persistent foundation for period-based organisation management.
 *
 * User IDs are Nextcloud UIDs. External resources are referenced by opaque IDs;
 * the migration deliberately does not create Talk, Files or Calendar resources.
 */
class Version000000Date20261120120000 extends SimpleMigrationStep {
    public function __construct(private IDBConnection $connection) {
    }

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $this->createPeriods($schema);
        $this->createGroups($schema);
        $this->createMemberships($schema);
        $this->createRolesAndPermissions($schema);
        $this->createResources($schema);
        $this->createAuditLog($schema);

        return $schema;
    }

    private function createPeriods(ISchemaWrapper $schema): void {
        if ($schema->hasTable('jugend_periods')) {
            return;
        }
        $table = $schema->createTable('jugend_periods');
        $this->addId($table);
        $table->addColumn('period_key', Types::STRING, ['length' => 32, 'notnull' => true]);
        $table->addColumn('title', Types::STRING, ['length' => 128, 'notnull' => true]);
        $table->addColumn('status', Types::STRING, ['length' => 16, 'notnull' => true, 'default' => 'draft']);
        $table->addColumn('starts_on', Types::DATE, ['notnull' => false]);
        $table->addColumn('ends_on', Types::DATE, ['notnull' => false]);
        $table->addColumn('activated_at', Types::INTEGER, ['notnull' => false]);
        $table->addColumn('archived_at', Types::INTEGER, ['notnull' => false]);
        $table->addColumn('created_by', Types::STRING, ['length' => 64, 'notnull' => true]);
        $table->addColumn('created_at', Types::INTEGER, ['notnull' => true]);
        $table->addColumn('updated_at', Types::INTEGER, ['notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['period_key'], 'jugend_period_key');
        $table->addIndex(['status'], 'jugend_period_status');
    }

    private function createGroups(ISchemaWrapper $schema): void {
        if ($schema->hasTable('jugend_groups')) {
            return;
        }
        $table = $schema->createTable('jugend_groups');
        $this->addId($table);
        $table->addColumn('period_id', Types::BIGINT, ['notnull' => true, 'length' => 20, 'unsigned' => true]);
        $table->addColumn('slug', Types::STRING, ['length' => 64, 'notnull' => true]);
        $table->addColumn('name', Types::STRING, ['length' => 128, 'notnull' => true]);
        $table->addColumn('description', Types::TEXT, ['notnull' => false]);
        $table->addColumn('sort_order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
        $table->addColumn('created_at', Types::INTEGER, ['notnull' => true]);
        $table->addColumn('updated_at', Types::INTEGER, ['notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['period_id', 'slug'], 'jugend_group_slug');
        $table->addIndex(['period_id'], 'jugend_group_period');
    }

    private function createMemberships(ISchemaWrapper $schema): void {
        if ($schema->hasTable('jugend_members')) {
            return;
        }
        $table = $schema->createTable('jugend_members');
        $this->addId($table);
        $table->addColumn('group_id', Types::BIGINT, ['notnull' => true, 'length' => 20, 'unsigned' => true]);
        $table->addColumn('user_id', Types::STRING, ['length' => 64, 'notnull' => true]);
        $table->addColumn('is_leader', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
        $table->addColumn('joined_at', Types::INTEGER, ['notnull' => true]);
        $table->addColumn('left_at', Types::INTEGER, ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['group_id', 'user_id'], 'jugend_member_group_user');
        $table->addIndex(['user_id'], 'jugend_member_user');
        $table->addIndex(['group_id', 'is_leader'], 'jugend_member_leaders');
    }

    private function createRolesAndPermissions(ISchemaWrapper $schema): void {
        if (!$schema->hasTable('jugend_roles')) {
            $table = $schema->createTable('jugend_roles');
            $this->addId($table);
            $table->addColumn('role_key', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('name', Types::STRING, ['length' => 128, 'notnull' => true]);
            $table->addColumn('description', Types::TEXT, ['notnull' => false]);
            $table->addColumn('is_system', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
            $table->addColumn('created_at', Types::INTEGER, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['role_key'], 'jugend_role_key');
        }

        if (!$schema->hasTable('jugend_perms')) {
            $table = $schema->createTable('jugend_perms');
            $this->addId($table);
            $table->addColumn('permission_key', Types::STRING, ['length' => 96, 'notnull' => true]);
            $table->addColumn('name', Types::STRING, ['length' => 128, 'notnull' => true]);
            $table->addColumn('description', Types::TEXT, ['notnull' => false]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['permission_key'], 'jugend_perm_key');
        }

        if (!$schema->hasTable('jugend_role_perm')) {
            $table = $schema->createTable('jugend_role_perm');
            $this->addId($table);
            $table->addColumn('role_id', Types::BIGINT, ['notnull' => true, 'length' => 20, 'unsigned' => true]);
            $table->addColumn('permission_id', Types::BIGINT, ['notnull' => true, 'length' => 20, 'unsigned' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['role_id', 'permission_id'], 'jugend_role_perm_pair');
            $table->addIndex(['permission_id'], 'jugend_role_perm_perm');
        }

        if (!$schema->hasTable('jugend_user_roles')) {
            $table = $schema->createTable('jugend_user_roles');
            $this->addId($table);
            $table->addColumn('user_id', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('role_id', Types::BIGINT, ['notnull' => true, 'length' => 20, 'unsigned' => true]);
            $table->addColumn('scope_type', Types::STRING, ['length' => 16, 'notnull' => true, 'default' => 'global']);
            $table->addColumn('scope_id', Types::BIGINT, ['notnull' => false, 'length' => 20, 'unsigned' => true]);
            $table->addColumn('granted_by', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('granted_at', Types::INTEGER, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['user_id', 'scope_type', 'scope_id'], 'jugend_user_role_scope');
            $table->addIndex(['role_id'], 'jugend_user_role_role');
        }
    }

    private function createResources(ISchemaWrapper $schema): void {
        if ($schema->hasTable('jugend_resources')) {
            return;
        }
        $table = $schema->createTable('jugend_resources');
        $this->addId($table);
        $table->addColumn('group_id', Types::BIGINT, ['notnull' => true, 'length' => 20, 'unsigned' => true]);
        $table->addColumn('resource_type', Types::STRING, ['length' => 16, 'notnull' => true]);
        $table->addColumn('resource_id', Types::STRING, ['length' => 128, 'notnull' => true]);
        $table->addColumn('resource_url', Types::STRING, ['length' => 512, 'notnull' => false]);
        $table->addColumn('created_at', Types::INTEGER, ['notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['group_id', 'resource_type'], 'jugend_resource_group_type');
        $table->addIndex(['resource_type', 'resource_id'], 'jugend_resource_lookup');
    }

    private function createAuditLog(ISchemaWrapper $schema): void {
        if ($schema->hasTable('jugend_audit')) {
            return;
        }
        $table = $schema->createTable('jugend_audit');
        $this->addId($table);
        $table->addColumn('actor_uid', Types::STRING, ['length' => 64, 'notnull' => true]);
        $table->addColumn('action', Types::STRING, ['length' => 96, 'notnull' => true]);
        $table->addColumn('subject_type', Types::STRING, ['length' => 32, 'notnull' => true]);
        $table->addColumn('subject_id', Types::STRING, ['length' => 64, 'notnull' => true]);
        $table->addColumn('details', Types::TEXT, ['notnull' => false]);
        $table->addColumn('created_at', Types::INTEGER, ['notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['subject_type', 'subject_id'], 'jugend_audit_subject');
        $table->addIndex(['created_at'], 'jugend_audit_created');
    }

    private function addId($table): void {
        $table->addColumn('id', Types::BIGINT, [
            'autoincrement' => true,
            'notnull' => true,
            'length' => 20,
            'unsigned' => true,
        ]);
    }
}
