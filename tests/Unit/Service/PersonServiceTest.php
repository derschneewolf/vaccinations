<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Tests\Unit\Service;

use DateTimeImmutable;
use OCA\Vaccinations\Catalog\Catalog;
use OCA\Vaccinations\Db\Person;
use OCA\Vaccinations\Db\PersonMapper;
use OCA\Vaccinations\Exception\ValidationException;
use OCA\Vaccinations\Service\InputValidator;
use OCA\Vaccinations\Service\PermissionService;
use OCA\Vaccinations\Service\PersonService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PersonServiceTest extends TestCase {
	private PersonMapper&MockObject $personMapper;
	private PersonService $service;
	private Person $stored;

	protected function setUp(): void {
		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('now')->willReturn(new DateTimeImmutable('2026-10-22 12:00'));

		$this->stored = new Person();
		$this->stored->setId(1);
		$this->stored->setUserId('kind');
		$this->stored->setBirthDate(new DateTimeImmutable('2014-06-01'));
		$this->personMapper = $this->createMock(PersonMapper::class);
		$this->personMapper->method('findByUserId')->willReturn($this->stored);
		$this->personMapper->method('update')->willReturnArgument(0);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('kind');
		$user->method('getDisplayName')->willReturn('Kind');
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->willReturn($user);
		$permissions = $this->createMock(PermissionService::class);
		$permissions->method('canManage')->willReturn(true);

		$this->service = new PersonService(
			$this->personMapper,
			$permissions,
			$userManager,
			$this->createMock(IGroupManager::class),
			new InputValidator($timeFactory),
			$timeFactory,
			new Catalog(__DIR__ . '/../../../data'),
		);
	}

	public function testChangingTheScheduleKeepsTheBirthDate(): void {
		$view = $this->service->updatePerson('frank', 'kind', ['scheduleId' => 'de-stiko']);

		$this->assertSame('2014-06-01', $view['birthDate']);
		$this->assertSame('de-stiko', $view['scheduleId']);
	}

	public function testChangingTheBirthDateKeepsTheSchedule(): void {
		$this->stored->setScheduleId('de-stiko');

		$view = $this->service->updatePerson('frank', 'kind', ['birthDate' => '2014-07-01']);

		$this->assertSame('2014-07-01', $view['birthDate']);
		$this->assertSame('de-stiko', $view['scheduleId']);
	}

	public function testNullClearsTheBirthDate(): void {
		$view = $this->service->updatePerson('frank', 'kind', ['birthDate' => null]);

		$this->assertNull($view['birthDate']);
	}

	public function testUnknownScheduleIsRejectedWithoutSaving(): void {
		$this->personMapper->expects($this->never())->method('update');

		try {
			$this->service->updatePerson('frank', 'kind', ['scheduleId' => 'xx-unknown', 'birthDate' => '2014-07-01']);
			$this->fail('Expected ValidationException');
		} catch (ValidationException $e) {
			$this->assertSame('scheduleId', $e->field);
		}
		$this->assertSame('2014-06-01', $this->stored->getBirthDate()?->format('Y-m-d'));
	}
}
