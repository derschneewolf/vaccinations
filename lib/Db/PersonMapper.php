<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Person>
 */
class PersonMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'vacc_person', Person::class);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findByUserId(string $userId): Person {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));
		return $this->findEntity($qb);
	}

	/**
	 * Returns the stored settings or a fresh, unsaved entity with defaults.
	 */
	public function findOrNew(string $userId): Person {
		try {
			return $this->findByUserId($userId);
		} catch (DoesNotExistException) {
			$person = new Person();
			$person->setUserId($userId);
			return $person;
		}
	}

	public function deleteByUserId(string $userId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->executeStatement();
	}
}
