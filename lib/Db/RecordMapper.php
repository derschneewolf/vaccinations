<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Record>
 */
class RecordMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'vacc_record', Record::class);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findById(int $id): Record {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * All doses of one person, oldest first.
	 *
	 * @return list<Record>
	 */
	public function findByUserId(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->orderBy('administered_on', 'ASC')
			->addOrderBy('id', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * Everyone with at least one documented dose.
	 *
	 * @return list<string>
	 */
	public function findUserIdsWithRecords(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('user_id')->from($this->getTableName());
		$result = $qb->executeQuery();
		try {
			return array_map('strval', $result->fetchFirstColumn());
		} finally {
			$result->closeCursor();
		}
	}

	public function deleteByUserId(string $userId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->executeStatement();
	}
}
