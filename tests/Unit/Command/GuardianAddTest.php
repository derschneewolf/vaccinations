<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Tests\Unit\Command;

use OCA\Vaccinations\Command\GuardianAdd;
use OCA\Vaccinations\Db\Guardian;
use OCA\Vaccinations\Db\GuardianMapper;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class GuardianAddTest extends TestCase {
	private GuardianMapper&MockObject $guardianMapper;
	private CommandTester $tester;

	protected function setUp(): void {
		$this->guardianMapper = $this->createMock(GuardianMapper::class);
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('userExists')->willReturnCallback(
			static fn (string $uid): bool => in_array($uid, ['frank', 'kind'], true),
		);
		$this->tester = new CommandTester(new GuardianAdd($this->guardianMapper, $userManager));
	}

	public function testCreatesRelation(): void {
		$this->guardianMapper->method('isGuardianOf')->willReturn(false);
		$this->guardianMapper->expects($this->once())
			->method('insert')
			->with($this->callback(static fn (Guardian $g): bool => $g->getGuardianUid() === 'frank' && $g->getWardUid() === 'kind'))
			->willReturnArgument(0);

		$this->assertSame(Command::SUCCESS, $this->tester->execute(['guardian' => 'frank', 'ward' => 'kind']));
	}

	public function testExistingRelationIsNotInsertedTwice(): void {
		$this->guardianMapper->method('isGuardianOf')->willReturn(true);
		$this->guardianMapper->expects($this->never())->method('insert');

		$this->assertSame(Command::SUCCESS, $this->tester->execute(['guardian' => 'frank', 'ward' => 'kind']));
	}

	public function testUnknownUserFails(): void {
		$this->guardianMapper->expects($this->never())->method('insert');

		$this->assertSame(Command::FAILURE, $this->tester->execute(['guardian' => 'frank', 'ward' => 'nobody']));
		$this->assertStringContainsString('"nobody" does not exist', $this->tester->getDisplay());
	}

	public function testSelfRelationFails(): void {
		$this->guardianMapper->expects($this->never())->method('insert');

		$this->assertSame(Command::FAILURE, $this->tester->execute(['guardian' => 'kind', 'ward' => 'kind']));
	}
}
