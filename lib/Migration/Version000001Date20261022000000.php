<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Initial schema.
 *
 * vacc_person   – per-user settings (birth date, selected schedule)
 * vacc_record   – one administered dose
 * vacc_guardian – who may maintain whose records (e.g. parent → child)
 *
 * Vaccines, diseases and schedules are NOT stored in the DB; they ship as
 * versioned JSON files in /data and are referenced by their string ids.
 */
class Version000001Date20261022000000 extends SimpleMigrationStep {

	/**
	 * @param array<array-key, mixed> $options
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('vacc_person')) {
			$table = $schema->createTable('vacc_person');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('birth_date', Types::DATE_IMMUTABLE, ['notnull' => false]);
			$table->addColumn('schedule_id', Types::STRING, ['notnull' => true, 'length' => 64, 'default' => 'de-stiko']);
			$table->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['user_id'], 'vacc_person_uid');
		}

		if (!$schema->hasTable('vacc_record')) {
			$table = $schema->createTable('vacc_record');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			// id from data/vaccines/*.json, e.g. "twinrix-adult"
			$table->addColumn('vaccine_id', Types::STRING, ['notnull' => true, 'length' => 128]);
			$table->addColumn('administered_on', Types::DATE_IMMUTABLE, ['notnull' => true]);
			$table->addColumn('practice', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('batch_number', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('note', Types::TEXT, ['notnull' => false]);
			$table->addColumn('created_by', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
			$table->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id', 'administered_on'], 'vacc_record_uid_date');
		}

		if (!$schema->hasTable('vacc_guardian')) {
			$table = $schema->createTable('vacc_guardian');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('guardian_uid', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('ward_uid', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['guardian_uid', 'ward_uid'], 'vacc_guardian_pair');
			$table->addIndex(['ward_uid'], 'vacc_guardian_ward');
		}

		return $schema;
	}
}
