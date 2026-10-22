<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Tests\Unit\Service;

use DateTimeImmutable;
use OCA\Vaccinations\Db\GuardianMapper;
use OCA\Vaccinations\Db\NotifiedState;
use OCA\Vaccinations\Db\NotifiedStateMapper;
use OCA\Vaccinations\Db\RecordMapper;
use OCA\Vaccinations\Schedule\RuleStatus;
use OCA\Vaccinations\Schedule\Schedule;
use OCA\Vaccinations\Service\NotificationService;
use OCA\Vaccinations\Service\StatusService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class NotificationServiceTest extends TestCase {
	private StatusService&MockObject $statusService;
	private GuardianMapper&MockObject $guardianMapper;
	private NotifiedStateMapper&MockObject $stateMapper;
	private IManager&MockObject $manager;
	private NotificationService $service;

	/** @var list<string> users that received a notification */
	private array $notifiedUsers = [];
	/** @var list<string> object ids that were retracted */
	private array $retracted = [];
	/** @var array<string, NotifiedState> */
	private array $states = [];
	/** @var list<RuleStatus> what StatusService::evaluate returns */
	private array $currentRules = [];

	protected function setUp(): void {
		$this->statusService = $this->createMock(StatusService::class);
		$this->guardianMapper = $this->createMock(GuardianMapper::class);
		$this->stateMapper = $this->createMock(NotifiedStateMapper::class);
		$this->manager = $this->createMock(IManager::class);
		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('now')->willReturn(new DateTimeImmutable('2026-10-22 06:00'));

		$schedule = new Schedule('de-stiko', [], '2026-09', '', [], [], []);
		$this->statusService->method('evaluate')->willReturnCallback(fn (): array => ['schedule' => $schedule, 'rules' => $this->currentRules]);

		$this->stateMapper->method('findByPerson')->willReturnCallback(fn (): array => $this->states);
		$this->stateMapper->method('insert')->willReturnCallback(function (NotifiedState $state): NotifiedState {
			$state->setId(100 + count($this->states));
			$this->states[$state->getRuleId()] = $state;
			return $state;
		});
		$this->stateMapper->method('update')->willReturnArgument(0);
		$this->stateMapper->method('delete')->willReturnCallback(function (NotifiedState $state): NotifiedState {
			unset($this->states[$state->getRuleId()]);
			return $state;
		});

		$this->manager->method('createNotification')->willReturnCallback(fn (): INotification => $this->notification());
		$this->manager->method('notify')->willReturnCallback(function (INotification $n): void {
			$this->notifiedUsers[] = $n->getUser();
		});
		$this->manager->method('markProcessed')->willReturnCallback(function (INotification $n): void {
			$this->retracted[] = $n->getObjectId();
		});

		$this->service = new NotificationService(
			$this->statusService,
			$this->createMock(RecordMapper::class),
			$this->guardianMapper,
			$this->stateMapper,
			$this->manager,
			$timeFactory,
			$this->createMock(LoggerInterface::class),
			$this->createMock(IDBConnection::class),
		);
	}

	public function testDueVaccinationNotifiesAllGuardians(): void {
		$this->guardians(['frank', 'partner']);
		$this->statuses([$this->rule('td', RuleStatus::DUE, '2025-07-01')]);

		$this->assertSame(2, $this->service->runForPerson('kind'));
		$this->assertSame(['frank', 'partner'], $this->notifiedUsers);
		$this->assertSame('due', $this->states['td']->getLevel());
	}

	public function testPersonWithoutGuardianIsNotifiedThemselves(): void {
		$this->guardians([]);
		$this->statuses([$this->rule('td', RuleStatus::OVERDUE, '2024-05-01')]);

		$this->service->runForPerson('frank');

		$this->assertSame(['frank'], $this->notifiedUsers);
	}

	public function testSameLevelIsAnnouncedOnlyOnce(): void {
		$this->guardians(['frank']);
		$this->statuses([$this->rule('td', RuleStatus::DUE, '2025-07-01')]);

		$this->service->runForPerson('kind');
		$this->service->runForPerson('kind');

		$this->assertSame(['frank'], $this->notifiedUsers);
	}

	public function testEscalationReplacesOldNotification(): void {
		$this->guardians(['frank']);
		$this->statuses([$this->rule('td', RuleStatus::UPCOMING, '2026-12-01')]);
		$this->service->runForPerson('kind');
		$firstId = (string)$this->states['td']->getId();

		$this->statuses([$this->rule('td', RuleStatus::DUE, '2026-12-01')]);
		$this->service->runForPerson('kind');

		$this->assertSame(['frank', 'frank'], $this->notifiedUsers);
		$this->assertSame([$firstId], $this->retracted);
		$this->assertSame('due', $this->states['td']->getLevel());
	}

	public function testLowerLevelForSameDoseIsNotAnnounced(): void {
		$this->guardians(['frank']);
		$this->statuses([$this->rule('td', RuleStatus::OVERDUE, '2025-07-01')]);
		$this->service->runForPerson('kind');

		$this->statuses([$this->rule('td', RuleStatus::DUE, '2025-07-01')]);
		$this->service->runForPerson('kind');

		$this->assertCount(1, $this->notifiedUsers);
	}

	public function testCompletedVaccinationRemovesNotification(): void {
		$this->guardians(['frank']);
		$this->statuses([$this->rule('td', RuleStatus::DUE, '2025-07-01')]);
		$this->service->runForPerson('kind');
		$id = (string)$this->states['td']->getId();

		$this->statuses([$this->rule('td', RuleStatus::COMPLETE, '2035-07-01')]);
		$this->service->runForPerson('kind');

		$this->assertSame([$id], $this->retracted);
		$this->assertArrayNotHasKey('td', $this->states);
		$this->assertCount(1, $this->notifiedUsers);
	}

	public function testNotDocumentedIsNeverAnnounced(): void {
		$this->guardians(['frank']);
		$this->statuses([$this->rule('hpv', RuleStatus::NOT_DOCUMENTED, '2023-06-01')]);

		$this->assertSame(0, $this->service->runForPerson('kind'));
		$this->assertSame([], $this->states);
	}

	public function testFailedDeliveryRollsBackAndPropagates(): void {
		$db = $this->createMock(IDBConnection::class);
		$db->expects($this->once())->method('beginTransaction');
		$db->expects($this->once())->method('rollBack');
		$db->expects($this->never())->method('commit');
		$manager = $this->createMock(IManager::class);
		$manager->method('createNotification')->willReturnCallback(fn (): INotification => $this->notification());
		$manager->method('notify')->willThrowException(new \RuntimeException('push failed'));
		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('now')->willReturn(new DateTimeImmutable('2026-10-22 06:00'));
		$service = new NotificationService(
			$this->statusService,
			$this->createMock(RecordMapper::class),
			$this->guardianMapper,
			$this->stateMapper,
			$manager,
			$timeFactory,
			$this->createMock(LoggerInterface::class),
			$db,
		);
		$this->guardians(['frank']);
		$this->statuses([$this->rule('td', RuleStatus::DUE, '2025-07-01')]);

		$this->expectException(\RuntimeException::class);
		$service->runForPerson('kind');
	}

	public function testNewSeasonIsAnnouncedAgain(): void {
		$this->guardians([]);
		$this->statuses([$this->rule('influenza', RuleStatus::DUE, '2026-09-01')]);
		$this->service->runForPerson('oma');

		$this->statuses([$this->rule('influenza', RuleStatus::DUE, '2027-09-01')]);
		$this->service->runForPerson('oma');

		$this->assertCount(2, $this->notifiedUsers);
	}

	/**
	 * @param list<string> $uids
	 */
	private function guardians(array $uids): void {
		$this->guardianMapper->method('findGuardianUids')->willReturn($uids);
	}

	/**
	 * @param list<RuleStatus> $rules
	 */
	private function statuses(array $rules): void {
		$this->currentRules = $rules;
	}

	private function rule(string $ruleId, string $status, string $dueFrom): RuleStatus {
		return new RuleStatus($ruleId, ['tetanus'], $status, 1, null, null, new DateTimeImmutable($dueFrom));
	}

	/**
	 * A notification double that remembers user and object id.
	 */
	private function notification(): INotification {
		$data = ['user' => '', 'objectId' => ''];
		$notification = $this->createMock(INotification::class);
		foreach (['setApp', 'setDateTime', 'setSubject'] as $method) {
			$notification->method($method)->willReturnSelf();
		}
		$notification->method('setUser')->willReturnCallback(function (string $user) use (&$data, $notification) {
			$data['user'] = $user;
			return $notification;
		});
		$notification->method('setObject')->willReturnCallback(function (string $type, string $id) use (&$data, $notification) {
			$data['objectId'] = $id;
			return $notification;
		});
		$notification->method('getUser')->willReturnCallback(function () use (&$data): string {
			return $data['user'];
		});
		$notification->method('getObjectId')->willReturnCallback(function () use (&$data): string {
			return $data['objectId'];
		});
		return $notification;
	}
}
