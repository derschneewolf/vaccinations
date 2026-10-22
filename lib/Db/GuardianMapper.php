<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Guardian>
 */
class GuardianMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'vacc_guardian', Guardian::class);
	}

	public function isGuardianOf(string $guardianUid, string $wardUid): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from($this->getTableName())
			->where($qb->expr()->eq('guardian_uid', $qb->createNamedParameter($guardianUid, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('ward_uid', $qb->createNamedParameter($wardUid, IQueryBuilder::PARAM_STR)))
			->setMaxResults(1);
		$result = $qb->executeQuery();
		try {
			return $result->fetchOne() !== false;
		} finally {
			$result->closeCursor();
		}
	}

	/**
	 * @return list<string>
	 */
	public function findWardUids(string $guardianUid): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('ward_uid')
			->from($this->getTableName())
			->where($qb->expr()->eq('guardian_uid', $qb->createNamedParameter($guardianUid, IQueryBuilder::PARAM_STR)))
			->orderBy('ward_uid', 'ASC');
		$result = $qb->executeQuery();
		try {
			return array_map('strval', $result->fetchFirstColumn());
		} finally {
			$result->closeCursor();
		}
	}

	/**
	 * @return list<Guardian>
	 */
	public function findAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->orderBy('guardian_uid', 'ASC')
			->addOrderBy('ward_uid', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * @return int number of deleted relations (0 or 1)
	 */
	public function deletePair(string $guardianUid, string $wardUid): int {
		$qb = $this->db->getQueryBuilder();
		return $qb->delete($this->getTableName())
			->where($qb->expr()->eq('guardian_uid', $qb->createNamedParameter($guardianUid, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('ward_uid', $qb->createNamedParameter($wardUid, IQueryBuilder::PARAM_STR)))
			->executeStatement();
	}

	/**
	 * Removes all relations a user takes part in, as guardian or as ward.
	 */
	public function deleteByUserId(string $userId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('guardian_uid', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->orWhere($qb->expr()->eq('ward_uid', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->executeStatement();
	}
}
