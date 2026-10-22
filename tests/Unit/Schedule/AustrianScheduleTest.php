<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Tests\Unit\Schedule;

use DateTimeImmutable;
use OCA\Vaccinations\Catalog\Catalog;
use OCA\Vaccinations\Schedule\RuleStatus;
use OCA\Vaccinations\Schedule\Schedule;
use OCA\Vaccinations\Schedule\ScheduleEvaluator;
use PHPUnit\Framework\TestCase;

/**
 * Impfplan Österreich: the rules that differ from STIKO. "Today" is 2026-10-22.
 */
class AustrianScheduleTest extends TestCase {
	private Schedule $schedule;
	private ScheduleEvaluator $evaluator;

	protected function setUp(): void {
		$catalog = new Catalog(__DIR__ . '/../../../data');
		$this->schedule = $catalog->getSchedule('at-impfplan') ?? throw new \RuntimeException('schedule missing');
		$this->evaluator = new ScheduleEvaluator($catalog);
	}

	public function testAdultTdapEveryTenYearsIncludingPertussis(): void {
		$result = $this->evaluate('1980-02-29', [['boostrix', '2021-09-16']]);

		$this->assertStatus(RuleStatus::COMPLETE, $result['tdap']);
		$this->assertSame('2031-09-16', $this->due($result['tdap']));
	}

	public function testTdOnlyBoosterDoesNotCountInAustria(): void {
		// boosters are Tdap in Austria – a Td-only dose does not cover pertussis
		$result = $this->evaluate('1980-02-29', [['td-pur', '2021-09-16']]);

		$this->assertStatus(RuleStatus::NOT_DOCUMENTED, $result['tdap']);
	}

	public function testTdapEveryFiveYearsFrom60(): void {
		$result = $this->evaluate('1962-03-01', [['boostrix', '2023-05-01']]);

		$this->assertSame('2028-05-01', $this->due($result['tdap']));
	}

	public function testSchoolBoosterWithoutFiveYearGap(): void {
		// A1 in the 6th year of life even if G3 was less than 5 years ago
		$result = $this->evaluate('2020-06-01', [
			['hexyon', '2020-08-01'], ['hexyon', '2020-10-01'], ['hexyon', '2021-04-15'],
		]);

		$this->assertSame('A1', $result['tdap']->nextLabel);
		$this->assertSame('2025-06-01', $this->due($result['tdap']));
		$this->assertStatus(RuleStatus::DUE, $result['tdap']);
	}

	public function testPolioEndsAfterSchoolBoosters(): void {
		$result = $this->evaluate('1980-02-29', [['boostrix-polio', '2021-09-16']]);

		$this->assertStatus(RuleStatus::DOCUMENTED, $result['polio']);
	}

	public function testTbeSeriesBoosterAndLaterInterval(): void {
		$result = $this->evaluate('1980-02-29', [
			['fsme-immun', '2020-03-01'], ['fsme-immun', '2020-04-01'], ['fsme-immun', '2020-11-01'],
		]);
		// first booster 3 years after the series – by now more than a year late
		$this->assertStatus(RuleStatus::OVERDUE, $result['tbe']);
		$this->assertSame('A1', $result['tbe']->nextLabel);
		$this->assertSame('2023-11-01', $this->due($result['tbe']));

		$result = $this->evaluate('1980-02-29', [
			['fsme-immun', '2020-03-01'], ['fsme-immun', '2020-04-01'], ['fsme-immun', '2020-11-01'], ['encepur', '2023-11-10'],
		]);
		// then every 5 years
		$this->assertStatus(RuleStatus::COMPLETE, $result['tbe']);
		$this->assertSame('2028-11-10', $this->due($result['tbe']));

		$result = $this->evaluate('1962-01-01', [
			['fsme-immun', '2010-03-01'], ['fsme-immun', '2010-04-01'], ['fsme-immun', '2010-11-01'], ['fsme-immun', '2013-11-01'], ['fsme-immun', '2024-05-01'],
		]);
		// from 60 every 3 years
		$this->assertSame('2027-05-01', $this->due($result['tbe']));
	}

	public function testTbeWithoutDocumentationForEveryone(): void {
		$result = $this->evaluate('1980-02-29', []);

		$this->assertStatus(RuleStatus::NOT_DOCUMENTED, $result['tbe']);
	}

	public function testMenAcwyTeenFrom10(): void {
		// toddler dose does not count for the adolescent dose
		$result = $this->evaluate('2015-03-01', [['nimenrix', '2016-04-01']]);

		$this->assertStatus(RuleStatus::DOCUMENTED, $result['meningococcal-acwy-toddler']);
		$this->assertStatus(RuleStatus::DUE, $result['meningococcal-acwy-teen']);
		$this->assertSame('2025-03-01', $this->due($result['meningococcal-acwy-teen']));
	}

	public function testInfluenzaForChildrenToo(): void {
		$result = $this->evaluate('2018-01-01', [['fluenz', '2025-10-15']]);

		$this->assertStatus(RuleStatus::DUE, $result['influenza']);
		$this->assertSame('2026-10-01', $this->due($result['influenza']));
	}

	public function testShinglesFrom50(): void {
		$result = $this->evaluate('1975-01-01', []);

		$this->assertStatus(RuleStatus::NOT_DOCUMENTED, $result['herpes-zoster']);
		$this->assertStatus(RuleStatus::PLANNED, $result['rsv']);
	}

	public function testMmrCatchUpForAdults(): void {
		$result = $this->evaluate('1985-01-01', [['m-m-rvaxpro', '1986-01-10']]);

		$this->assertStatus(RuleStatus::OVERDUE, $result['mmr']);
		$this->assertSame('G2', $result['mmr']->nextLabel);
	}

	/**
	 * @param list<array{string, string}> $doses
	 * @return array<string, RuleStatus>
	 */
	private function evaluate(string $birthDate, array $doses): array {
		$statuses = $this->evaluator->evaluate(
			$this->schedule,
			new DateTimeImmutable($birthDate),
			array_map(static fn (array $dose): array => ['vaccineId' => $dose[0], 'date' => new DateTimeImmutable($dose[1])], $doses),
			new DateTimeImmutable('2026-10-22'),
		);
		$byId = [];
		foreach ($statuses as $status) {
			$byId[$status->ruleId] = $status;
		}
		return $byId;
	}

	private function due(RuleStatus $status): ?string {
		return $status->nextDueFrom?->format('Y-m-d');
	}

	private function assertStatus(string $expected, RuleStatus $status): void {
		$this->assertSame($expected, $status->status, json_encode($status->jsonSerialize()) ?: '');
	}
}
