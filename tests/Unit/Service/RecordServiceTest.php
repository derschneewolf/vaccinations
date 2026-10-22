<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Tests\Unit\Service;

use DateTimeImmutable;
use OCA\Vaccinations\Catalog\Catalog;
use OCA\Vaccinations\Db\Record;
use OCA\Vaccinations\Db\RecordMapper;
use OCA\Vaccinations\Exception\ForbiddenException;
use OCA\Vaccinations\Exception\NotFoundException;
use OCA\Vaccinations\Exception\ValidationException;
use OCA\Vaccinations\Service\InputValidator;
use OCA\Vaccinations\Service\PermissionService;
use OCA\Vaccinations\Service\PersonService;
use OCA\Vaccinations\Service\RecordService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RecordServiceTest extends TestCase {
	private RecordMapper&MockObject $recordMapper;
	private PersonService&MockObject $personService;
	private RecordService $service;

	protected function setUp(): void {
		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('now')->willReturn(new DateTimeImmutable('2026-10-22 12:00:00'));

		$this->recordMapper = $this->createMock(RecordMapper::class);
		$this->recordMapper->method('insert')->willReturnArgument(0);
		$this->recordMapper->method('update')->willReturnArgument(0);

		// frank looks after kind; nobody else is related
		$permissionService = $this->createMock(PermissionService::class);
		$permissionService->method('canManage')->willReturnCallback(
			static fn (string $actor, string $person): bool => $actor === $person || ($actor === 'frank' && $person === 'kind'),
		);
		$this->personService = $this->createMock(PersonService::class);
		$this->personService->method('requireManageableUser')->willReturnCallback(
			function (string $actor, string $person): IUser {
				if (!($actor === $person || ($actor === 'frank' && $person === 'kind'))) {
					throw new ForbiddenException();
				}
				return $this->createMock(IUser::class);
			},
		);

		$this->service = new RecordService(
			$this->recordMapper,
			$this->personService,
			$permissionService,
			new Catalog(__DIR__ . '/../../fixtures/catalog'),
			new InputValidator($timeFactory),
			$timeFactory,
		);
	}

	public function testGuardianCreatesRecordForWard(): void {
		$record = $this->service->create('frank', 'kind', [
			'vaccineId' => 'twinrix-adult',
			'administeredOn' => '2025-05-12',
			'practice' => ' Praxis Dr. Muster ',
			'batchNumber' => '',
		]);

		$this->assertSame('kind', $record->getUserId());
		$this->assertSame('frank', $record->getCreatedBy());
		$this->assertSame('twinrix-adult', $record->getVaccineId());
		$this->assertSame('2025-05-12', $record->getAdministeredOn()->format('Y-m-d'));
		$this->assertSame('Praxis Dr. Muster', $record->getPractice());
		$this->assertNull($record->getBatchNumber());
	}

	public function testWardCannotCreateRecordForGuardian(): void {
		$this->recordMapper->expects($this->never())->method('insert');
		$this->expectException(ForbiddenException::class);

		$this->service->create('kind', 'frank', ['vaccineId' => 'havrix', 'administeredOn' => '2025-05-12']);
	}

	public function testUnknownVaccineIsRejected(): void {
		$this->recordMapper->expects($this->never())->method('insert');

		try {
			$this->service->create('frank', 'frank', ['vaccineId' => 'made-up', 'administeredOn' => '2025-05-12']);
			$this->fail('Expected ValidationException');
		} catch (ValidationException $e) {
			$this->assertSame('vaccineId', $e->field);
		}
	}

	public function testNonStringInputIsRejected(): void {
		$this->expectException(ValidationException::class);
		$this->service->create('frank', 'frank', ['vaccineId' => ['havrix'], 'administeredOn' => '2025-05-12']);
	}

	public function testInvalidInputLeavesExistingRecordUntouched(): void {
		$record = $this->existingRecord('kind');
		$this->recordMapper->expects($this->never())->method('update');

		try {
			$this->service->update('frank', 7, ['vaccineId' => 'havrix', 'administeredOn' => '2099-01-01']);
		} catch (ValidationException) {
		}
		$this->assertSame('twinrix-adult', $record->getVaccineId());
	}

	public function testGuardianUpdatesWardRecord(): void {
		$this->existingRecord('kind');

		$record = $this->service->update('frank', 7, ['vaccineId' => 'havrix', 'administeredOn' => '2024-01-02', 'note' => 'Auffrischung']);

		$this->assertSame('havrix', $record->getVaccineId());
		$this->assertSame('Auffrischung', $record->getNote());
	}

	public function testForeignRecordLooksLikeItDoesNotExist(): void {
		$this->existingRecord('frank');
		$this->recordMapper->expects($this->never())->method('delete');
		$this->expectException(NotFoundException::class);

		$this->service->delete('kind', 7);
	}

	public function testMissingRecordIsNotFound(): void {
		$this->recordMapper->method('findById')->willThrowException(new DoesNotExistException(''));
		$this->expectException(NotFoundException::class);

		$this->service->delete('frank', 99);
	}

	private function existingRecord(string $owner): Record {
		$record = new Record();
		$record->setId(7);
		$record->setUserId($owner);
		$record->setVaccineId('twinrix-adult');
		$record->setAdministeredOn(new DateTimeImmutable('2020-01-01'));
		$this->recordMapper->method('findById')->with(7)->willReturn($record);
		return $record;
	}
}
