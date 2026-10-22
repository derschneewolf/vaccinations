<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Tests\Unit\Service;

use DateTimeImmutable;
use OCA\Vaccinations\Exception\ValidationException;
use OCA\Vaccinations\Service\InputValidator;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class InputValidatorTest extends TestCase {
	private InputValidator $validator;

	protected function setUp(): void {
		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('now')->willReturn(new DateTimeImmutable('2026-10-22 23:30:00'));
		$this->validator = new InputValidator($timeFactory);
	}

	public function testAcceptsPastDate(): void {
		$this->assertSame('2024-03-01', $this->validator->pastDate('d', '2024-03-01')->format('Y-m-d'));
	}

	public function testToleratesOneDayAheadForTimezones(): void {
		$this->assertSame('2026-10-23', $this->validator->pastDate('d', '2026-10-23')->format('Y-m-d'));
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function invalidDates(): array {
		return [
			'future' => ['2026-10-24'],
			'wrong format' => ['22.10.2026'],
			'impossible date' => ['2026-02-30'],
			'empty' => [''],
			'too old' => ['1899-12-31'],
		];
	}

	#[DataProvider('invalidDates')]
	public function testRejectsInvalidDates(string $value): void {
		try {
			$this->validator->pastDate('administeredOn', $value);
			$this->fail('Expected ValidationException');
		} catch (ValidationException $e) {
			$this->assertSame('administeredOn', $e->field);
		}
	}

	public function testOptionalTextTrimsAndNullsEmpty(): void {
		$this->assertSame('Praxis Dr. Muster', $this->validator->optionalText('p', '  Praxis Dr. Muster ', 255));
		$this->assertNull($this->validator->optionalText('p', '   ', 255));
		$this->assertNull($this->validator->optionalText('p', null, 255));
	}

	public function testOptionalTextEnforcesLength(): void {
		$this->expectException(ValidationException::class);
		$this->validator->optionalText('p', str_repeat('ä', 11), 10);
	}
}
