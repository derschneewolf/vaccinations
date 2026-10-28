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
 * Runs the shipped STIKO schedule and vaccine catalogue against typical
 * vaccination histories. "Today" is fixed to 2026-10-22 unless stated.
 */
class ScheduleEvaluatorTest extends TestCase {
	private const TODAY = '2026-10-22';

	private Schedule $schedule;
	private ScheduleEvaluator $evaluator;

	protected function setUp(): void {
		$dataDir = __DIR__ . '/../../../data';
		$catalog = new Catalog($dataDir);
		$this->schedule = $catalog->getSchedule('de-stiko') ?? throw new \RuntimeException('schedule missing');
		$this->evaluator = new ScheduleEvaluator($catalog);
	}

	// ---- infants -------------------------------------------------------

	public function testInfantWithoutDocumentationIsNotDocumentedNotOverdue(): void {
		$result = $this->evaluate('2026-07-01', []);

		$this->assertStatus(RuleStatus::NOT_DOCUMENTED, $result['td']);
		$this->assertSame('2026-09-01', $this->due($result['td']));
		$this->assertStatus(RuleStatus::NOT_DOCUMENTED, $result['rotavirus']);
		// MMR only from 11 months on
		$this->assertStatus(RuleStatus::PLANNED, $result['mmr']);
		$this->assertSame('2027-06-01', $this->due($result['mmr']));
	}

	public function testInfantSecondHexavalentDoseIsUpcoming(): void {
		$result = $this->evaluate('2026-07-01', [['infanrix-hexa', '2026-09-01']]);

		foreach (['td', 'pertussis', 'polio', 'hib', 'hepatitis-b'] as $ruleId) {
			$this->assertStatus(RuleStatus::UPCOMING, $result[$ruleId], $ruleId);
			$this->assertSame('2026-11-01', $this->due($result[$ruleId]), $ruleId);
			$this->assertSame('G2', $result[$ruleId]->nextLabel);
		}
		// the hexavalent vaccine does not cover pneumococci
		$this->assertStatus(RuleStatus::NOT_DOCUMENTED, $result['pneumococcal-child']);
	}

	public function testMinimumIntervalPushesThirdDoseBack(): void {
		// G2 late at 6 months: G3 not before G2 + 6 months, although 11 months are reached earlier
		$result = $this->evaluate('2026-01-01', [['infanrix-hexa', '2026-03-01'], ['infanrix-hexa', '2026-07-01']]);

		$this->assertSame('2027-01-01', $this->due($result['td']));
		// 71 days ahead: outside the 60-day window
		$this->assertStatus(RuleStatus::PLANNED, $result['td']);
	}

	public function testPartialSeriesIsOverdue(): void {
		// 5-year-old with only the first dose documented: a real gap
		$result = $this->evaluate('2021-06-01', [['infanrix-hexa', '2021-08-01']]);

		$this->assertStatus(RuleStatus::OVERDUE, $result['td']);
		$this->assertSame('G2', $result['td']->nextLabel);
	}

	public function testRotavirusWindowClosesAfter32Weeks(): void {
		$result = $this->evaluate('2025-10-01', []);

		$this->assertStatus(RuleStatus::NOT_APPLICABLE, $result['rotavirus']);
	}

	public function testLateHibStartNeedsOnlyOneDose(): void {
		$result = $this->evaluate('2024-01-01', [['infanrix-hexa', '2025-03-01']]);

		$this->assertStatus(RuleStatus::COMPLETE, $result['hib']);
	}

	public function testHibEndsAtFive(): void {
		$result = $this->evaluate('2020-01-01', []);

		$this->assertStatus(RuleStatus::NOT_APPLICABLE, $result['hib']);
		$this->assertStatus(RuleStatus::NOT_APPLICABLE, $result['meningococcal-b']);
		$this->assertStatus(RuleStatus::NOT_APPLICABLE, $result['pneumococcal-child']);
	}

	// ---- school children and adolescents ------------------------------

	public function testMissingSecondBoosterIsDueAt14(): void {
		$result = $this->evaluate('2012-05-10', [
			['infanrix-hexa', '2012-07-10'],
			['infanrix-hexa', '2012-09-10'],
			['infanrix-hexa', '2013-04-10'],
			['boostrix', '2018-06-01'],
			['priorix', '2013-04-20'],
			['priorix', '2013-08-20'],
		]);

		// A2: from 9 years, but not before A1 + 5 years; overdue only after 17
		$this->assertStatus(RuleStatus::DUE, $result['td']);
		$this->assertSame('A2', $result['td']->nextLabel);
		$this->assertSame('2023-06-01', $this->due($result['td']));
		// polio booster from 9 years (Boostrix has no IPV)
		$this->assertStatus(RuleStatus::DUE, $result['polio']);
		$this->assertSame('A1', $result['polio']->nextLabel);
		$this->assertStatus(RuleStatus::COMPLETE, $result['mmr']);
		$this->assertStatus(RuleStatus::NOT_DOCUMENTED, $result['hpv']);
		$this->assertStatus(RuleStatus::NOT_DOCUMENTED, $result['meningococcal-acwy']);
	}

	public function testCompleteChildIsPlannedNotDue(): void {
		$result = $this->evaluate('2018-03-01', [
			['infanrix-hexa', '2018-05-01'],
			['infanrix-hexa', '2018-07-01'],
			['infanrix-hexa', '2019-02-01'],
			['boostrix', '2023-06-01'],
		]);

		$this->assertStatus(RuleStatus::PLANNED, $result['td']);
		$this->assertSame('A2', $result['td']->nextLabel);
		$this->assertSame('2028-06-01', $this->due($result['td']));
	}

	public function testHpvSecondDoseAfterFiveMonths(): void {
		$result = $this->evaluate('2012-05-10', [['gardasil-9', '2025-03-01']]);

		$this->assertStatus(RuleStatus::DUE, $result['hpv']);
		$this->assertSame('2025-08-01', $this->due($result['hpv']));
	}

	public function testHpvStartedAt15NeedsThreeDoses(): void {
		$result = $this->evaluate('2010-01-01', [['gardasil-9', '2026-02-01'], ['gardasil-9', '2026-03-05']]);

		$this->assertSame('G3', $result['hpv']->nextLabel);
		$this->assertSame('2026-08-05', $this->due($result['hpv']));
	}

	public function testFourthInfantDoseIsNoSchoolAgeBooster(): void {
		// old 3+1 schedule: 4th hexavalent dose at 22 months – the polio booster at 9–16 is still due
		$result = $this->evaluate('2014-03-28', [
			['infanrix-hexa', '2014-06-04'],
			['infanrix-hexa', '2014-07-11'],
			['infanrix-hexa', '2014-09-19'],
			['infanrix-hexa', '2016-02-10'],
		]);

		$polio = $result['polio'];
		$this->assertStatus(RuleStatus::DUE, $polio);
		$this->assertSame('A1', $polio->nextLabel);
		$this->assertSame('2023-03-28', $this->due($polio));
		$this->assertSame(3, $polio->dosesCounted);
		$this->assertSame(['2016-02-10'], array_map(static fn (DateTimeImmutable $date): string => $date->format('Y-m-d'), $polio->ignoredDates));
		// neither is it the Td booster at 5–6 years
		$this->assertStatus(RuleStatus::OVERDUE, $result['td']);
		$this->assertSame('A1', $result['td']->nextLabel);
		$this->assertSame(1, $result['td']->dosesIgnored);
		// Hib and hepatitis B have no booster: the 4th dose is simply one more
		$this->assertStatus(RuleStatus::COMPLETE, $result['hepatitis-b']);
	}

	public function testBoosterAtSchoolAgeCounts(): void {
		$result = $this->evaluate('2014-03-28', [
			['infanrix-hexa', '2014-06-04'],
			['infanrix-hexa', '2014-07-11'],
			['infanrix-hexa', '2015-02-10'],
			['repevax', '2019-05-02'],
			['repevax', '2023-06-01'],
		]);

		// Td/Pertussis: boosters at 5 and 9 years
		$this->assertStatus(RuleStatus::COMPLETE, $result['td']);
		$this->assertSame(0, $result['td']->dosesIgnored);
		// polio has no booster at 5: that dose is an additional one, the dose at 9 is the booster
		$this->assertStatus(RuleStatus::COMPLETE, $result['polio']);
		$this->assertSame(4, $result['polio']->dosesCounted);
		$this->assertSame(1, $result['polio']->dosesIgnored);
	}

	public function testMenAcwyBefore12DoesNotCountButIsNoLongerUndocumented(): void {
		// Nimenrix at 10: STIKO recommends the dose at 12–14 regardless of earlier vaccinations
		$result = $this->evaluate('2014-03-28', [['nimenrix', '2024-08-15']]);

		$this->assertStatus(RuleStatus::DUE, $result['meningococcal-acwy']);
		$this->assertSame(0, $result['meningococcal-acwy']->dosesCounted);
		$this->assertSame(1, $result['meningococcal-acwy']->dosesIgnored);
		$this->assertSame('2026-03-28', $this->due($result['meningococcal-acwy']));
		$this->assertSame('2029-03-28', $result['meningococcal-acwy']->nextOverdueFrom?->format('Y-m-d'));
	}

	public function testMenAcwyIgnoresMenCFromInfancy(): void {
		$result = $this->evaluate('2013-01-15', [['neisvac-c', '2014-01-20']]);

		$this->assertStatus(RuleStatus::NOT_DOCUMENTED, $result['meningococcal-acwy']);

		$result = $this->evaluate('2013-01-15', [['nimenrix', '2026-02-01']]);
		$this->assertStatus(RuleStatus::COMPLETE, $result['meningococcal-acwy']);
	}

	// ---- adults ---------------------------------------------------------

	public function testAdultWithRecentTdapIsComplete(): void {
		$result = $this->evaluate('1980-02-29', [['boostrix', '2021-09-16']]);

		$this->assertStatus(RuleStatus::COMPLETE, $result['td']);
		$this->assertSame('2031-09-16', $this->due($result['td']));
		$this->assertStatus(RuleStatus::COMPLETE, $result['pertussis-adult']);
		// childhood series are over and nothing is documented: hidden
		$this->assertStatus(RuleStatus::NOT_APPLICABLE, $result['mmr']);
		$this->assertStatus(RuleStatus::NOT_APPLICABLE, $result['polio']);
		// born after 1970 without any measles vaccination documented
		$this->assertStatus(RuleStatus::NOT_DOCUMENTED, $result['measles-adult']);
		// 60+ recommendations lie ahead
		$this->assertStatus(RuleStatus::PLANNED, $result['influenza']);
		$this->assertSame('2040-02-29', $this->due($result['influenza']));
		$this->assertStatus(RuleStatus::PLANNED, $result['herpes-zoster']);
	}

	public function testOldTdBoosterIsOverdue(): void {
		$result = $this->evaluate('1975-05-05', [['td-pur', '2010-04-01']]);

		$this->assertStatus(RuleStatus::OVERDUE, $result['td']);
		$this->assertSame('2020-04-01', $this->due($result['td']));
		// Tdap is due together with the next Td
		$this->assertStatus(RuleStatus::NOT_DOCUMENTED, $result['pertussis-adult']);
	}

	public function testTdapAlignsWithNextTdBooster(): void {
		$result = $this->evaluate('1975-05-05', [['td-pur', '2020-04-01']]);

		$this->assertStatus(RuleStatus::COMPLETE, $result['td']);
		$this->assertStatus(RuleStatus::PLANNED, $result['pertussis-adult']);
		$this->assertSame('2030-04-01', $this->due($result['pertussis-adult']));
	}

	public function testAdultWithoutAnyTdDocumentation(): void {
		$result = $this->evaluate('1975-05-05', []);

		$this->assertStatus(RuleStatus::NOT_DOCUMENTED, $result['td']);
	}

	public function testTwoChildhoodMmrDosesSatisfyAdultMeasles(): void {
		$result = $this->evaluate('1985-01-01', [['m-m-rvaxpro', '1986-01-10'], ['m-m-rvaxpro', '1990-06-01']]);

		$this->assertStatus(RuleStatus::COMPLETE, $result['measles-adult']);
		$this->assertStatus(RuleStatus::DOCUMENTED, $result['mmr']);
	}

	public function testAdultMeaslesRuleDoesNotApplyToChildren(): void {
		$result = $this->evaluate('2014-06-01', [['priorix', '2015-05-15'], ['priorix', '2015-09-15']]);

		$this->assertStatus(RuleStatus::COMPLETE, $result['mmr']);
		$this->assertStatus(RuleStatus::NOT_APPLICABLE, $result['measles-adult']);
	}

	public function testOneChildhoodMmrDoseMakesAdultMeaslesDue(): void {
		$result = $this->evaluate('1985-01-01', [['m-m-rvaxpro', '1986-01-10']]);

		$this->assertStatus(RuleStatus::DUE, $result['measles-adult']);
		$this->assertSame(1, $result['measles-adult']->dosesIgnored);
	}

	public function testMeaslesRuleDoesNotApplyBefore1971(): void {
		$result = $this->evaluate('1970-12-31', []);

		$this->assertStatus(RuleStatus::NOT_APPLICABLE, $result['measles-adult']);
	}

	// ---- seniors ----------------------------------------------------------

	public function testSeniorWithoutDocumentation(): void {
		$result = $this->evaluate('1950-01-01', []);

		foreach (['pneumococcal-senior', 'herpes-zoster', 'influenza', 'covid-19', 'rsv'] as $ruleId) {
			$this->assertStatus(RuleStatus::NOT_DOCUMENTED, $result[$ruleId], $ruleId);
		}
	}

	public function testInfluenzaFromLastSeasonIsDue(): void {
		$result = $this->evaluate('1950-01-01', [['efluelda', '2025-10-10']]);

		$this->assertStatus(RuleStatus::DUE, $result['influenza']);
		$this->assertSame('2026-09-01', $this->due($result['influenza']));
	}

	public function testEarlyInfluenzaDoseCountsForSeason(): void {
		$result = $this->evaluate('1950-01-01', [['efluelda', '2026-08-20']]);

		$this->assertStatus(RuleStatus::COMPLETE, $result['influenza']);
		$this->assertSame('2027-09-01', $this->due($result['influenza']));
	}

	public function testInfluenzaIsUpcomingBeforeSeason(): void {
		$result = $this->evaluate('1950-01-01', [['efluelda', '2025-10-01']], '2026-07-15');

		$this->assertStatus(RuleStatus::UPCOMING, $result['influenza']);
		$this->assertSame('2026-09-01', $this->due($result['influenza']));
	}

	public function testCovidOnlyFrom75(): void {
		$result = $this->evaluate('1960-01-01', []);

		$this->assertStatus(RuleStatus::PLANNED, $result['covid-19']);
		$this->assertStatus(RuleStatus::NOT_DOCUMENTED, $result['influenza']);
	}

	public function testShinglesSecondDoseWindow(): void {
		$result = $this->evaluate('1955-01-01', [['shingrix', '2026-07-01']]);

		$this->assertStatus(RuleStatus::DUE, $result['herpes-zoster']);
		$this->assertSame('2026-09-01', $this->due($result['herpes-zoster']));
		$this->assertSame('2027-01-01', $result['herpes-zoster']->nextOverdueFrom?->format('Y-m-d'));

		$result = $this->evaluate('1955-01-01', [['shingrix', '2026-01-10']]);
		$this->assertStatus(RuleStatus::OVERDUE, $result['herpes-zoster']);
	}

	public function testChildhoodPneumococcalDosesDoNotCountForSeniors(): void {
		$result = $this->evaluate('1960-01-01', [['prevenar-13', '2015-01-01']]);

		// the earlier dose does not count, but the history is documented: due, not "not documented"
		$this->assertStatus(RuleStatus::DUE, $result['pneumococcal-senior']);
		$this->assertSame(0, $result['pneumococcal-senior']->dosesCounted);
		$this->assertSame(1, $result['pneumococcal-senior']->dosesIgnored);
	}

	// ---- TBE (indication, only once started) -----------------------------

	public function testTbeIsHiddenWithoutDocumentation(): void {
		$result = $this->evaluate('1980-02-29', []);

		$this->assertStatus(RuleStatus::NOT_APPLICABLE, $result['tbe']);
	}

	public function testStartedTbeSeriesIsChecked(): void {
		$result = $this->evaluate('1980-02-29', [['fsme-immun', '2026-08-01']]);

		$this->assertStatus(RuleStatus::DUE, $result['tbe']);
		$this->assertSame('G2', $result['tbe']->nextLabel);
		$this->assertSame('2026-09-01', $this->due($result['tbe']));
		$this->assertSame('2026-11-01', $result['tbe']->nextOverdueFrom?->format('Y-m-d'));
	}

	public function testTbeBoosterAfterThreeThenFiveYears(): void {
		$series = [['fsme-immun', '2019-03-01'], ['fsme-immun', '2019-04-01'], ['fsme-immun', '2019-11-01']];
		$result = $this->evaluate('1980-02-29', $series);
		$this->assertStatus(RuleStatus::OVERDUE, $result['tbe']);
		$this->assertSame('2022-11-01', $this->due($result['tbe']));

		$result = $this->evaluate('1980-02-29', [...$series, ['encepur', '2022-10-15']]);
		$this->assertStatus(RuleStatus::COMPLETE, $result['tbe']);
		$this->assertSame('2027-10-15', $this->due($result['tbe']));
	}

	public function testTbeEveryThreeYearsFrom50(): void {
		$result = $this->evaluate('1972-01-01', [
			['encepur', '2010-03-01'], ['encepur', '2010-04-01'], ['encepur', '2011-02-01'], ['encepur', '2014-02-01'], ['encepur', '2024-05-01'],
		]);

		$this->assertSame('2027-05-01', $this->due($result['tbe']));
	}

	// ---- next dose description --------------------------------------------

	public function testNextTypeForUi(): void {
		$result = $this->evaluate('2026-07-01', [['infanrix-hexa', '2026-09-01']]);
		$this->assertSame(['type' => 'dose', 'doseNumber' => 2], $result['td']->nextType());

		$result = $this->evaluate('2018-03-01', [['infanrix-hexa', '2018-05-01'], ['infanrix-hexa', '2018-07-01'], ['infanrix-hexa', '2019-02-01'], ['boostrix', '2023-06-01']]);
		$this->assertSame('booster', $result['td']->nextType()['type'], 'A2');

		$result = $this->evaluate('1980-02-29', [['boostrix', '2021-09-16']]);
		$this->assertSame('booster', $result['td']->nextType()['type'], 'periodic');

		$result = $this->evaluate('1950-01-01', [['efluelda', '2025-10-10']]);
		$this->assertSame('season', $result['influenza']->nextType()['type']);

		$result = $this->evaluate('1975-05-05', [['td-pur', '2020-04-01']]);
		$this->assertSame('named', $result['pertussis-adult']->nextType()['type'], 'Tdap');
		$this->assertSame('Tdap', $result['pertussis-adult']->jsonSerialize()['next']['label']);
	}

	// ---- general ----------------------------------------------------------

	public function testMissingBirthDate(): void {
		$result = $this->evaluate(null, [['boostrix', '2021-09-16']]);

		$this->assertStatus(RuleStatus::MISSING_BIRTH_DATE, $result['td']);
		$this->assertSame(1, $result['td']->dosesCounted);
	}

	public function testSameDayEntriesCountOnce(): void {
		$result = $this->evaluate('2026-01-01', [['infanrix-hexa', '2026-03-01'], ['infanrix-hexa', '2026-03-01']]);

		$this->assertSame(1, $result['td']->dosesCounted);
	}

	public function testUnknownVaccinesAreIgnored(): void {
		$result = $this->evaluate('1980-01-01', [['gibts-nicht', '2025-01-01']]);

		$this->assertStatus(RuleStatus::NOT_DOCUMENTED, $result['td']);
	}

	/**
	 * @param list<array{string, string}> $doses [vaccineId, date]
	 * @return array<string, RuleStatus>
	 */
	private function evaluate(?string $birthDate, array $doses, string $today = self::TODAY): array {
		$statuses = $this->evaluator->evaluate(
			$this->schedule,
			$birthDate === null ? null : new DateTimeImmutable($birthDate),
			array_map(static fn (array $dose): array => ['vaccineId' => $dose[0], 'date' => new DateTimeImmutable($dose[1])], $doses),
			new DateTimeImmutable($today),
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

	private function assertStatus(string $expected, RuleStatus $status, string $message = ''): void {
		$this->assertSame($expected, $status->status, trim($message . ' ' . json_encode($status->jsonSerialize())));
	}
}
