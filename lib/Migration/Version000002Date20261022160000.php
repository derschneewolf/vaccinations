<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * vacc_notified – the last notification sent per person and rule, so every
 * level (upcoming → due → overdue) is announced only once.
 */
class Version000002Date20261022160000 extends SimpleMigrationStep {
	/**
	 * @param array<array-key, mixed> $options
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('vacc_notified')) {
			$table = $schema->createTable('vacc_notified');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('person_uid', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('rule_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			// Y-m-d of the dose the notification was about
			$table->addColumn('due_from', Types::STRING, ['notnull' => true, 'length' => 10]);
			$table->addColumn('level', Types::STRING, ['notnull' => true, 'length' => 16]);
			$table->addColumn('notified_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['person_uid', 'rule_id'], 'vacc_notified_rule');
		}

		return $schema;
	}
}
