<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Tests\Unit\Schedule;

use DateTimeImmutable;
use OCA\Vaccinations\Catalog\Catalog;
use OCA\Vaccinations\Schedule\Schedule;
use OCA\Vaccinations\Schedule\ScheduleEvaluator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Boosters every 10 years, from 60 every 5 years (Austrian Tdap rule).
 */
class LaterBoosterIntervalTest extends TestCase {
	/**
	 * @return array<string, array{string, string}> last booster => expected due date (born 1966-05-01, 60 on 2026-05-01)
	 */
	public static function cases(): array {
		return [
			'young: regular 10 years' => ['2001-03-01', '2011-03-01'],
			'next booster would fall after 60: due at 60' => ['2019-06-01', '2026-05-01'],
			'shortly before 60: 5 years later' => ['2024-01-01', '2029-01-01'],
			'after 60: every 5 years' => ['2027-01-01', '2032-01-01'],
		];
	}

	#[DataProvider('cases')]
	public function testDueDate(string $lastBooster, string $expectedDue): void {
		$schedule = Schedule::fromArray([
			'id' => 'test',
			'version' => '1',
			'rules' => [[
				'id' => 'td',
				'diseases' => ['tetanus', 'diphtheria'],
				'kind' => 'series',
				'reference' => 'test',
				'until' => 'P18Y',
				'variants' => [['fromAge' => 'P0D', 'doses' => [['label' => 'G1', 'age' => 'P2M']]]],
				'then' => ['every' => 'P10Y', 'later' => ['fromAge' => 'P60Y', 'every' => 'P5Y']],
			]],
		]);
		$evaluator = new ScheduleEvaluator(new Catalog(__DIR__ . '/../../../data'));

		$status = $evaluator->evaluate(
			$schedule,
			new DateTimeImmutable('1966-05-01'),
			[['vaccineId' => 'td-pur', 'date' => new DateTimeImmutable($lastBooster)]],
			new DateTimeImmutable('2030-01-01'),
		)[0];

		$this->assertSame($expectedDue, $status->nextDueFrom?->format('Y-m-d'));
	}
}
