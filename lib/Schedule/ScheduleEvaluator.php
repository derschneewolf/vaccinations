<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Schedule;

use DateInterval;
use DateTimeImmutable;
use OCA\Vaccinations\Catalog\Catalog;

/**
 * Evaluates a schedule against the documented doses of one person.
 *
 * Missing documentation is reported as NOT_DOCUMENTED instead of overdue:
 * an empty history usually means "vaccination card not entered", not
 * "never vaccinated". A partially documented series however is treated as
 * a real gap and gets due/overdue.
 */
class ScheduleEvaluator {
	public const UPCOMING_DAYS = 60;
	private const DEFAULT_GRACE = 'P3M';

	public function __construct(
		private readonly Catalog $catalog,
	) {
	}

	/**
	 * @param list<array{vaccineId: string, date: DateTimeImmutable}> $doses
	 * @return list<RuleStatus> in schedule order
	 */
	public function evaluate(Schedule $schedule, ?DateTimeImmutable $birthDate, array $doses, DateTimeImmutable $today): array {
		$today = $today->setTime(0, 0);
		$birthDate = $birthDate?->setTime(0, 0);

		// rules that align with another rule need that rule's result first
		$results = [];
		foreach ($schedule->rules as $rule) {
			if ($rule->alignWith === null) {
				$results[$rule->id] = $this->evaluateRule($rule, $birthDate, $doses, $today, null);
			}
		}
		foreach ($schedule->rules as $rule) {
			if ($rule->alignWith !== null) {
				$results[$rule->id] = $this->evaluateRule($rule, $birthDate, $doses, $today, $results[$rule->alignWith] ?? null);
			}
		}

		return array_map(static fn (Rule $rule): RuleStatus => $results[$rule->id], $schedule->rules);
	}

	/**
	 * @param list<array{vaccineId: string, date: DateTimeImmutable}> $doses
	 */
	private function evaluateRule(Rule $rule, ?DateTimeImmutable $birthDate, array $doses, DateTimeImmutable $today, ?RuleStatus $aligned): RuleStatus {
		$dates = $this->doseDates($rule, $doses);
		if ($rule->onlyIfDocumented && $dates === []) {
			return $this->status($rule, RuleStatus::NOT_APPLICABLE, $dates);
		}
		if ($birthDate === null) {
			return $this->status($rule, RuleStatus::MISSING_BIRTH_DATE, $dates);
		}
		return match ($rule->kind) {
			Rule::KIND_SERIES => $this->evaluateSeries($rule, $birthDate, $dates, $today),
			Rule::KIND_ONCE => $this->evaluateOnce($rule, $birthDate, $dates, $today, $aligned),
			Rule::KIND_SEASONAL => $this->evaluateSeasonal($rule, $birthDate, $dates, $today),
			default => $this->status($rule, RuleStatus::NOT_APPLICABLE, $dates),
		};
	}

	/**
	 * @param list<DateTimeImmutable> $dates
	 */
	private function evaluateSeries(Rule $rule, DateTimeImmutable $birthDate, array $dates, DateTimeImmutable $today): RuleStatus {
		$ignored = 0;
		if ($rule->countDosesFromAge !== null) {
			$countFrom = $birthDate->add($rule->countDosesFromAge);
			$counted = array_values(array_filter($dates, static fn (DateTimeImmutable $date): bool => $date >= $countFrom));
			$ignored = count($dates) - count($counted);
			$dates = $counted;
		}

		$seriesEnd = $rule->until === null ? null : $birthDate->add($rule->until);
		if ($seriesEnd !== null && $today >= $seriesEnd) {
			if ($rule->boosterEvery !== null) {
				return $this->evaluateBooster($rule, $birthDate, $dates, $today);
			}
			return $this->status($rule, $dates === [] ? RuleStatus::NOT_APPLICABLE : RuleStatus::DOCUMENTED, $dates, ignored: $ignored);
		}

		$variant = $this->selectVariant($rule, $birthDate, $dates[0] ?? $today);
		$count = count($dates);
		if ($variant === null) {
			return $this->status($rule, RuleStatus::NOT_APPLICABLE, $dates);
		}
		if ($count >= count($variant->doses)) {
			return $rule->boosterEvery !== null
				? $this->evaluateBooster($rule, $birthDate, $dates, $today)
				: $this->status($rule, RuleStatus::COMPLETE, $dates, ignored: $ignored);
		}

		$slot = $variant->doses[$count];
		$last = $dates[$count - 1] ?? null;
		$dueFrom = $birthDate->add($slot->age ?? new DateInterval('P0D'));
		if ($last !== null && $slot->minInterval !== null) {
			$dueFrom = max($dueFrom, $last->add($slot->minInterval));
		}
		$overdueFrom = match (true) {
			$last !== null && $slot->maxInterval !== null => $last->add($slot->maxInterval),
			$slot->dueUntil !== null => $birthDate->add($slot->dueUntil),
			default => $dueFrom->add(new DateInterval(self::DEFAULT_GRACE)),
		};

		return $this->scheduled($rule, $dates, $slot->label, $dueFrom, max($overdueFrom, $dueFrom), $today, $ignored);
	}

	/**
	 * Periodic booster after a completed series, e.g. Td every 10 years.
	 *
	 * With a later interval (e.g. every 5 years from 60) the booster is due
	 * after the regular interval, but no later than the later interval once
	 * that age is reached:  min(last + every, max(last + laterEvery, laterFromAge)).
	 *
	 * @param list<DateTimeImmutable> $dates
	 */
	private function evaluateBooster(Rule $rule, DateTimeImmutable $birthDate, array $dates, DateTimeImmutable $today): RuleStatus {
		$last = end($dates);
		if ($last === false || $rule->boosterEvery === null) {
			return $this->status($rule, RuleStatus::NOT_DOCUMENTED, $dates);
		}
		$dueFrom = $last->add($rule->boosterEvery);
		if ($rule->laterBoosterFromAge !== null && $rule->laterBoosterEvery !== null) {
			$dueFrom = min($dueFrom, max($last->add($rule->laterBoosterEvery), $birthDate->add($rule->laterBoosterFromAge)));
		}
		$overdueFrom = $dueFrom->add($rule->boosterGrace ?? new DateInterval(self::DEFAULT_GRACE));
		return $this->scheduled($rule, $dates, null, $dueFrom, $overdueFrom, $today);
	}

	/**
	 * @param list<DateTimeImmutable> $dates
	 */
	private function evaluateOnce(Rule $rule, DateTimeImmutable $birthDate, array $dates, DateTimeImmutable $today, ?RuleStatus $aligned): RuleStatus {
		if ($rule->bornAfter !== null && $birthDate <= $rule->bornAfter) {
			return $this->status($rule, $dates === [] ? RuleStatus::NOT_APPLICABLE : RuleStatus::DOCUMENTED, $dates);
		}
		$start = $birthDate->add($rule->fromAge ?? new DateInterval('P0D'));
		if ($rule->satisfiedByTotalDoses !== null && count($dates) >= $rule->satisfiedByTotalDoses) {
			// before fromAge the childhood series covers the same doses
			return $this->status($rule, $today < $start ? RuleStatus::NOT_APPLICABLE : RuleStatus::COMPLETE, $dates);
		}
		$counted = array_values(array_filter($dates, static fn (DateTimeImmutable $date): bool => $date >= $start));
		$ignored = count($dates) - count($counted);
		$count = count($counted);
		if ($count >= count($rule->doses)) {
			return $this->status($rule, RuleStatus::COMPLETE, $counted, ignored: $ignored);
		}

		$slot = $rule->doses[$count];
		$last = $counted[$count - 1] ?? null;
		$dueFrom = $slot->age !== null ? max($start, $birthDate->add($slot->age)) : $start;
		if ($last !== null && $slot->minInterval !== null) {
			$dueFrom = max($dueFrom, $last->add($slot->minInterval));
		}
		// e.g. Tdap together with the next Td booster
		if ($aligned?->nextDueFrom !== null) {
			$dueFrom = max($dueFrom, $aligned->nextDueFrom);
		}
		// one-time recommendations without a deadline are "due", never "overdue"
		$overdueFrom = $last !== null && $slot->maxInterval !== null ? $last->add($slot->maxInterval) : null;

		return $this->scheduled($rule, $counted, $slot->label, $dueFrom, $overdueFrom, $today, $ignored);
	}

	/**
	 * One dose per season (seasonStart..seasonEnd, crossing the new year).
	 *
	 * @param list<DateTimeImmutable> $dates
	 */
	private function evaluateSeasonal(Rule $rule, DateTimeImmutable $birthDate, array $dates, DateTimeImmutable $today): RuleStatus {
		$start = $birthDate->add($rule->fromAge ?? new DateInterval('P0D'));
		[$seasonStart, $seasonEnd] = $this->currentSeason($rule, $today);
		$nextSeasonStart = $seasonStart->modify('+1 year');

		if ($today < $start) {
			$firstSeason = $this->seasonContaining($rule, $start);
			$dueFrom = $start <= $firstSeason[1] ? max($start, $firstSeason[0]) : $firstSeason[0]->modify('+1 year');
			return $this->scheduled($rule, $dates, null, $dueFrom, null, $today);
		}

		// early doses in the weeks before the official start count for the season
		$countFrom = $seasonStart->modify('-1 month');
		$dosedThisSeason = array_filter($dates, static fn (DateTimeImmutable $date): bool => $date >= $countFrom);

		if ($today > $seasonEnd || $dosedThisSeason !== []) {
			// between seasons, or done for this season
			return $this->scheduled($rule, $dates, null, $nextSeasonStart, null, $today);
		}
		if ($dates === []) {
			return $this->status($rule, RuleStatus::NOT_DOCUMENTED, $dates, null, $seasonStart);
		}
		return $this->scheduled($rule, $dates, null, max($seasonStart, $start), null, $today);
	}

	/**
	 * The season whose start is the latest one not after $date.
	 *
	 * @return array{DateTimeImmutable, DateTimeImmutable} start and end
	 */
	private function currentSeason(Rule $rule, DateTimeImmutable $date): array {
		return $this->seasonContaining($rule, $date);
	}

	/**
	 * @return array{DateTimeImmutable, DateTimeImmutable}
	 */
	private function seasonContaining(Rule $rule, DateTimeImmutable $date): array {
		$year = (int)$date->format('Y');
		$start = new DateTimeImmutable($year . '-' . $rule->seasonStart);
		if ($start > $date) {
			$start = $start->modify('-1 year');
		}
		$end = new DateTimeImmutable($start->format('Y') . '-' . $rule->seasonEnd);
		if ($end < $start) {
			$end = $end->modify('+1 year');
		}
		return [$start, $end];
	}

	private function selectVariant(Rule $rule, DateTimeImmutable $birthDate, DateTimeImmutable $reference): ?Variant {
		$selected = null;
		foreach ($rule->variants as $variant) {
			if ($birthDate->add($variant->fromAge) <= $reference) {
				$selected = $variant;
			}
		}
		return $selected;
	}

	/**
	 * Dates of doses with a vaccine that covers every disease of the rule;
	 * several entries on the same day count once.
	 *
	 * @param list<array{vaccineId: string, date: DateTimeImmutable}> $doses
	 * @return list<DateTimeImmutable> ascending
	 */
	private function doseDates(Rule $rule, array $doses): array {
		$dates = [];
		foreach ($doses as $dose) {
			$vaccine = $this->catalog->getVaccine($dose['vaccineId']);
			if ($vaccine === null) {
				continue;
			}
			foreach ($rule->diseaseIds as $diseaseId) {
				if (!$vaccine->protectsAgainst($diseaseId)) {
					continue 2;
				}
			}
			$day = $dose['date']->setTime(0, 0);
			$dates[$day->format('Y-m-d')] = $day;
		}
		ksort($dates);
		return array_values($dates);
	}

	/**
	 * @param list<DateTimeImmutable> $dates
	 */
	private function scheduled(Rule $rule, array $dates, ?string $label, DateTimeImmutable $dueFrom, ?DateTimeImmutable $overdueFrom, DateTimeImmutable $today, int $ignored = 0): RuleStatus {
		if ($today < $dueFrom) {
			$days = (int)$today->diff($dueFrom)->days;
			$status = $days <= self::UPCOMING_DAYS ? RuleStatus::UPCOMING : RuleStatus::PLANNED;
			// a future dose does not make an otherwise empty history "complete"
			if ($dates !== [] && $status === RuleStatus::PLANNED && $label === null) {
				$status = RuleStatus::COMPLETE;
			}
		} elseif ($dates === [] && $ignored === 0) {
			// nothing at all documented; doses that do not count still prove a real history
			$status = RuleStatus::NOT_DOCUMENTED;
		} elseif ($overdueFrom !== null && $today > $overdueFrom) {
			$status = RuleStatus::OVERDUE;
		} else {
			$status = RuleStatus::DUE;
		}
		return $this->status($rule, $status, $dates, $label, $dueFrom, $overdueFrom, $ignored);
	}

	/**
	 * @param list<DateTimeImmutable> $dates
	 */
	private function status(
		Rule $rule,
		string $status,
		array $dates,
		?string $label = null,
		?DateTimeImmutable $dueFrom = null,
		?DateTimeImmutable $overdueFrom = null,
		int $ignored = 0,
	): RuleStatus {
		$last = end($dates);
		return new RuleStatus(
			ruleId: $rule->id,
			diseaseIds: $rule->diseaseIds,
			status: $status,
			dosesCounted: count($dates),
			lastDose: $last === false ? null : $last,
			nextLabel: $label,
			nextDueFrom: $dueFrom,
			nextOverdueFrom: $overdueFrom,
			reference: $rule->reference,
			names: $rule->names,
			dosesIgnored: $ignored,
			ruleKind: $rule->kind,
		);
	}
}
