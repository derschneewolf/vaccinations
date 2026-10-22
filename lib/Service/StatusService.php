<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Service;

use OCA\Vaccinations\Catalog\Catalog;
use OCA\Vaccinations\Db\PersonMapper;
use OCA\Vaccinations\Db\Record;
use OCA\Vaccinations\Db\RecordMapper;
use OCA\Vaccinations\Exception\ForbiddenException;
use OCA\Vaccinations\Exception\NotFoundException;
use OCA\Vaccinations\Schedule\RuleStatus;
use OCA\Vaccinations\Schedule\ScheduleEvaluator;
use OCP\AppFramework\Utility\ITimeFactory;

/**
 * Vaccination status of one person against their selected schedule.
 */
class StatusService {
	public function __construct(
		private readonly PersonService $personService,
		private readonly PersonMapper $personMapper,
		private readonly RecordMapper $recordMapper,
		private readonly Catalog $catalog,
		private readonly ScheduleEvaluator $evaluator,
		private readonly ITimeFactory $timeFactory,
	) {
	}

	/**
	 * @return array{schedule: ?\OCA\Vaccinations\Schedule\Schedule, rules: list<RuleStatus>}
	 * @throws ForbiddenException|NotFoundException
	 */
	public function forPerson(string $actorUid, string $userId): array {
		$this->personService->requireManageableUser($actorUid, $userId);

		$person = $this->personMapper->findOrNew($userId);
		$schedule = $this->catalog->getSchedule($person->getScheduleId());
		if ($schedule === null) {
			return ['schedule' => null, 'rules' => []];
		}

		$doses = array_map(
			static fn (Record $record): array => ['vaccineId' => $record->getVaccineId(), 'date' => $record->getAdministeredOn()],
			$this->recordMapper->findByUserId($userId),
		);
		$statuses = $this->evaluator->evaluate($schedule, $person->getBirthDate(), $doses, $this->timeFactory->now());

		return [
			'schedule' => $schedule,
			// recommendations that do not apply are of no interest in the UI
			'rules' => array_values(array_filter(
				$statuses,
				static fn (RuleStatus $status): bool => $status->status !== RuleStatus::NOT_APPLICABLE,
			)),
		];
	}
}
