<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<NotifiedState>
 */
class NotifiedStateMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'vacc_notified', NotifiedState::class);
	}

	/**
	 * @return array<string, NotifiedState> keyed by rule id
	 */
	public function findByPerson(string $personUid): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('person_uid', $qb->createNamedParameter($personUid, IQueryBuilder::PARAM_STR)));
		$states = [];
		foreach ($this->findEntities($qb) as $state) {
			$states[$state->getRuleId()] = $state;
		}
		return $states;
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findById(int $id): NotifiedState {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	public function deleteByPerson(string $personUid): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('person_uid', $qb->createNamedParameter($personUid, IQueryBuilder::PARAM_STR)))
			->executeStatement();
	}
}
