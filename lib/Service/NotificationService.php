<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Service;

use OCA\Vaccinations\AppInfo\Application;
use OCA\Vaccinations\Db\GuardianMapper;
use OCA\Vaccinations\Db\NotifiedState;
use OCA\Vaccinations\Db\NotifiedStateMapper;
use OCA\Vaccinations\Db\RecordMapper;
use OCA\Vaccinations\Schedule\RuleStatus;
use OCP\AppFramework\Db\TTransactional;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use Psr\Log\LoggerInterface;

/**
 * Sends Nextcloud notifications for upcoming, due and overdue vaccinations.
 *
 * - every level is announced once per dose (state in vacc_notified)
 * - recipients: the person's guardians, or the person if nobody looks after them
 * - "not documented" is never announced – it would flood new users
 * - once a vaccination is no longer pending, its notification disappears
 */
class NotificationService {
	use TTransactional;

	public const OBJECT_TYPE = 'vaccination';
	public const SUBJECT = 'vaccination';

	/** levels in ascending urgency */
	private const LEVELS = [RuleStatus::UPCOMING => 1, RuleStatus::DUE => 2, RuleStatus::OVERDUE => 3];

	public function __construct(
		private readonly StatusService $statusService,
		private readonly RecordMapper $recordMapper,
		private readonly GuardianMapper $guardianMapper,
		private readonly NotifiedStateMapper $stateMapper,
		private readonly IManager $notificationManager,
		private readonly ITimeFactory $timeFactory,
		private readonly LoggerInterface $logger,
		private readonly IDBConnection $db,
	) {
	}

	/**
	 * @return int number of notifications sent
	 */
	public function run(): int {
		$sent = 0;
		foreach ($this->recordMapper->findUserIdsWithRecords() as $personUid) {
			try {
				$sent += $this->runForPerson($personUid);
			} catch (\Throwable $e) {
				// one broken person must not stop the others
				$this->logger->error('Vaccination check failed for ' . $personUid, ['exception' => $e, 'app' => Application::APP_ID]);
			}
		}
		return $sent;
	}

	/**
	 * State and notifications are written in one transaction: if delivery
	 * fails, nothing is marked as announced and the next run tries again.
	 */
	public function runForPerson(string $personUid): int {
		return $this->atomic(fn (): int => $this->checkPerson($personUid), $this->db);
	}

	private function checkPerson(string $personUid): int {
		$result = $this->statusService->evaluate($personUid);
		if ($result['schedule'] === null) {
			return 0;
		}
		$states = $this->stateMapper->findByPerson($personUid);
		$recipients = $this->guardianMapper->findGuardianUids($personUid) ?: [$personUid];
		$sent = 0;

		foreach ($result['rules'] as $status) {
			$state = $states[$status->ruleId] ?? null;
			$level = self::LEVELS[$status->status] ?? null;
			$dueFrom = $status->nextDueFrom?->format('Y-m-d');

			if ($level === null || $dueFrom === null) {
				// nothing pending (any more): remove an old notification
				if ($state !== null) {
					$this->retract($state);
					$this->stateMapper->delete($state);
				}
				continue;
			}

			$alreadyAnnounced = $state !== null
				&& $state->getDueFrom() === $dueFrom
				&& (self::LEVELS[$state->getLevel()] ?? 0) >= $level;
			if ($alreadyAnnounced) {
				continue;
			}

			$isNew = $state === null;
			if ($state !== null) {
				$this->retract($state);
			} else {
				$state = new NotifiedState();
				$state->setPersonUid($personUid);
				$state->setRuleId($status->ruleId);
			}
			$state->setDueFrom($dueFrom);
			$state->setLevel($status->status);
			$state->setNotifiedAt($this->timeFactory->now());
			$state = $isNew ? $this->stateMapper->insert($state) : $this->stateMapper->update($state);

			foreach ($recipients as $recipient) {
				$this->notificationManager->notify($this->createNotification($state, $result['schedule']->id)->setUser($recipient));
				$sent++;
			}
		}

		// rules that vanished from the evaluation (e.g. schedule changed)
		$evaluated = array_map(static fn (RuleStatus $status): string => $status->ruleId, $result['rules']);
		foreach (array_diff_key($states, array_flip($evaluated)) as $state) {
			$this->retract($state);
			$this->stateMapper->delete($state);
		}

		return $sent;
	}

	/**
	 * Removes all notifications of a person, e.g. when the user is deleted.
	 */
	public function retractAll(string $personUid): void {
		foreach ($this->stateMapper->findByPerson($personUid) as $state) {
			$this->retract($state);
		}
		$this->stateMapper->deleteByPerson($personUid);
	}

	private function retract(NotifiedState $state): void {
		$notification = $this->notificationManager->createNotification();
		$notification->setApp(Application::APP_ID)
			->setObject(self::OBJECT_TYPE, (string)$state->getId());
		$this->notificationManager->markProcessed($notification);
	}

	private function createNotification(NotifiedState $state, string $scheduleId): INotification {
		$notification = $this->notificationManager->createNotification();
		$notification->setApp(Application::APP_ID)
			->setDateTime(\DateTime::createFromImmutable($this->timeFactory->now()))
			->setObject(self::OBJECT_TYPE, (string)$state->getId())
			->setSubject(self::SUBJECT, [
				'personUid' => $state->getPersonUid(),
				'scheduleId' => $scheduleId,
				'ruleId' => $state->getRuleId(),
				'level' => $state->getLevel(),
				'dueFrom' => $state->getDueFrom(),
			]);
		return $notification;
	}
}
