<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Service;

use OCA\Vaccinations\Catalog\Catalog;
use OCA\Vaccinations\Db\Record;
use OCA\Vaccinations\Db\RecordMapper;
use OCA\Vaccinations\Exception\ForbiddenException;
use OCA\Vaccinations\Exception\NotFoundException;
use OCA\Vaccinations\Exception\ValidationException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;

/**
 * Create, read, update and delete administered doses – every call checks
 * that the acting user may manage the person the record belongs to.
 */
class RecordService {
	public const MAX_PRACTICE = 255;
	public const MAX_BATCH_NUMBER = 64;
	public const MAX_NOTE = 2000;

	public function __construct(
		private readonly RecordMapper $recordMapper,
		private readonly PersonService $personService,
		private readonly PermissionService $permissionService,
		private readonly Catalog $catalog,
		private readonly InputValidator $validator,
		private readonly ITimeFactory $timeFactory,
	) {
	}

	/**
	 * @return list<Record>
	 * @throws ForbiddenException|NotFoundException
	 */
	public function findForPerson(string $actorUid, string $userId): array {
		$this->personService->requireManageableUser($actorUid, $userId);
		return $this->recordMapper->findByUserId($userId);
	}

	/**
	 * @param array{vaccineId?: mixed, administeredOn?: mixed, practice?: mixed, batchNumber?: mixed, note?: mixed} $input
	 * @throws ForbiddenException|NotFoundException|ValidationException
	 */
	public function create(string $actorUid, string $userId, array $input): Record {
		$this->personService->requireManageableUser($actorUid, $userId);

		$record = new Record();
		$record->setUserId($userId);
		$record->setCreatedBy($actorUid);
		$record->setCreatedAt($this->timeFactory->now());
		$this->apply($record, $input);

		return $this->recordMapper->insert($record);
	}

	/**
	 * @param array{vaccineId?: mixed, administeredOn?: mixed, practice?: mixed, batchNumber?: mixed, note?: mixed} $input
	 * @throws ForbiddenException|NotFoundException|ValidationException
	 */
	public function update(string $actorUid, int $id, array $input): Record {
		$record = $this->findManageable($actorUid, $id);
		$this->apply($record, $input);
		return $this->recordMapper->update($record);
	}

	/**
	 * @throws ForbiddenException|NotFoundException
	 */
	public function delete(string $actorUid, int $id): void {
		$this->recordMapper->delete($this->findManageable($actorUid, $id));
	}

	/**
	 * @throws ForbiddenException|NotFoundException
	 */
	private function findManageable(string $actorUid, int $id): Record {
		try {
			$record = $this->recordMapper->findById($id);
		} catch (DoesNotExistException) {
			throw new NotFoundException('Unknown vaccination record');
		}
		if (!$this->permissionService->canManage($actorUid, $record->getUserId())) {
			// do not reveal that the record exists
			throw new NotFoundException('Unknown vaccination record');
		}
		return $record;
	}

	/**
	 * Validates the complete input and only then touches the entity.
	 *
	 * @param array{vaccineId?: mixed, administeredOn?: mixed, practice?: mixed, batchNumber?: mixed, note?: mixed} $input
	 * @throws ValidationException
	 */
	private function apply(Record $record, array $input): void {
		$vaccineId = $this->stringOrNull($input['vaccineId'] ?? null);
		if ($vaccineId === null || $this->catalog->getVaccine($vaccineId) === null) {
			throw new ValidationException('vaccineId', 'Unknown vaccine');
		}
		$administeredOn = $this->validator->pastDate('administeredOn', $this->stringOrNull($input['administeredOn'] ?? null) ?? '');
		$practice = $this->validator->optionalText('practice', $this->stringOrNull($input['practice'] ?? null), self::MAX_PRACTICE);
		$batchNumber = $this->validator->optionalText('batchNumber', $this->stringOrNull($input['batchNumber'] ?? null), self::MAX_BATCH_NUMBER);
		$note = $this->validator->optionalText('note', $this->stringOrNull($input['note'] ?? null), self::MAX_NOTE);

		$record->setVaccineId($vaccineId);
		$record->setAdministeredOn($administeredOn);
		$record->setPractice($practice);
		$record->setBatchNumber($batchNumber);
		$record->setNote($note);
		$record->setUpdatedAt($this->timeFactory->now());
	}

	private function stringOrNull(mixed $value): ?string {
		return is_string($value) ? $value : null;
	}
}
