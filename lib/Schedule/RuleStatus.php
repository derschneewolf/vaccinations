<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Schedule;

use DateTimeImmutable;
use JsonSerializable;

/**
 * Result of evaluating one rule for one person.
 */
final readonly class RuleStatus implements JsonSerializable {
	/** all recommended doses documented, nothing due right now */
	public const COMPLETE = 'complete';
	/** next dose later than UPCOMING_DAYS from now */
	public const PLANNED = 'planned';
	/** next dose within UPCOMING_DAYS */
	public const UPCOMING = 'upcoming';
	public const DUE = 'due';
	public const OVERDUE = 'overdue';
	/** recommended, but no dose documented at all */
	public const NOT_DOCUMENTED = 'not_documented';
	/** no recommendation applies (any more), but doses exist */
	public const DOCUMENTED = 'documented';
	/** no recommendation applies and nothing documented – not shown */
	public const NOT_APPLICABLE = 'not_applicable';
	public const MISSING_BIRTH_DATE = 'missing_birth_date';

	/**
	 * @param list<string> $diseaseIds
	 */
	public function __construct(
		public string $ruleId,
		public array $diseaseIds,
		public string $status,
		public int $dosesCounted,
		public ?DateTimeImmutable $lastDose = null,
		public ?string $nextLabel = null,
		public ?DateTimeImmutable $nextDueFrom = null,
		public ?DateTimeImmutable $nextOverdueFrom = null,
		public string $reference = '',
		/** @var array<string, string> */
		public array $names = [],
		/** documented doses that do not count, e.g. given before the recommended age */
		public int $dosesIgnored = 0,
		/** @var list<DateTimeImmutable> dates of the doses that do not count */
		public array $ignoredDates = [],
		/** Rule::KIND_* */
		public string $ruleKind = '',
	) {
	}

	/**
	 * What the next dose is, for a readable text in the UI:
	 * dose (Gn, with number), booster (An or periodic), season, or named (e.g. Tdap, MenACWY).
	 *
	 * @return array{type: 'dose'|'booster'|'season'|'named', doseNumber: ?int}
	 */
	public function nextType(): array {
		if ($this->ruleKind === Rule::KIND_SEASONAL) {
			return ['type' => 'season', 'doseNumber' => null];
		}
		if ($this->nextLabel === null || preg_match('/^A\d+$/', $this->nextLabel) === 1) {
			return ['type' => 'booster', 'doseNumber' => null];
		}
		if (preg_match('/^G(\d+)$/', $this->nextLabel, $matches) === 1) {
			return ['type' => 'dose', 'doseNumber' => (int)$matches[1]];
		}
		return ['type' => 'named', 'doseNumber' => null];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array {
		return [
			'ruleId' => $this->ruleId,
			'diseaseIds' => $this->diseaseIds,
			'names' => $this->names === [] ? null : $this->names,
			'status' => $this->status,
			'dosesCounted' => $this->dosesCounted,
			'dosesIgnored' => $this->dosesIgnored,
			'ignoredDates' => array_map(static fn (DateTimeImmutable $date): string => $date->format('Y-m-d'), $this->ignoredDates),
			'lastDose' => $this->lastDose?->format('Y-m-d'),
			'next' => $this->nextDueFrom === null ? null : [
				...$this->nextType(),
				'label' => $this->nextLabel,
				'dueFrom' => $this->nextDueFrom->format('Y-m-d'),
				'overdueFrom' => $this->nextOverdueFrom?->format('Y-m-d'),
			],
			'reference' => $this->reference,
		];
	}
}
