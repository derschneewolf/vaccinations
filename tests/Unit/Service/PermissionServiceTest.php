<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Tests\Unit\Service;

use OCA\Vaccinations\Db\GuardianMapper;
use OCA\Vaccinations\Service\PermissionService;
use OCP\IGroupManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PermissionServiceTest extends TestCase {
	private GuardianMapper&MockObject $guardianMapper;
	private IGroupManager&MockObject $groupManager;
	private PermissionService $service;

	protected function setUp(): void {
		$this->guardianMapper = $this->createMock(GuardianMapper::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->groupManager->method('isAdmin')->willReturnCallback(static fn (string $uid): bool => $uid === 'admin');
		$this->guardianMapper->method('isGuardianOf')->willReturnCallback(
			static fn (string $guardian, string $ward): bool => $guardian === 'frank' && $ward === 'kind',
		);
		$this->guardianMapper->method('findWardUids')->willReturnCallback(
			static fn (string $guardian): array => $guardian === 'frank' ? ['frank', 'kind'] : [],
		);
		$this->service = new PermissionService($this->guardianMapper, $this->groupManager);
	}

	public function testUserManagesThemselves(): void {
		$this->assertTrue($this->service->canManage('kind', 'kind'));
	}

	public function testGuardianManagesWard(): void {
		$this->assertTrue($this->service->canManage('frank', 'kind'));
	}

	public function testWardDoesNotManageGuardian(): void {
		$this->assertFalse($this->service->canManage('kind', 'frank'));
	}

	public function testAdminManagesEveryone(): void {
		$this->assertTrue($this->service->canManage('admin', 'kind'));
	}

	public function testManageableUsersStartWithSelfWithoutDuplicates(): void {
		$this->assertSame(['frank', 'kind'], $this->service->getManageableUserIds('frank'));
		$this->assertSame(['kind'], $this->service->getManageableUserIds('kind'));
	}
}
