<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Schedule;

use DateInterval;
use DateTimeImmutable;

/**
 * One recommendation of a schedule for one disease or a group of diseases
 * that is vaccinated together (e.g. tetanus + diphtheria).
 *
 * - series:   doses at given ages, optionally followed by a periodic booster
 * - once:     doses from a certain age (e.g. PCV20 from 60)
 * - seasonal: one dose per season from a certain age (e.g. influenza)
 */
final readonly class Rule {
	public const KIND_SERIES = 'series';
	public const KIND_ONCE = 'once';
	public const KIND_SEASONAL = 'seasonal';

	/**
	 * @param list<string> $diseaseIds
	 * @param list<Variant> $variants series only
	 * @param list<DoseSlot> $doses once only
	 */
	public function __construct(
		public string $id,
		public array $diseaseIds,
		public string $kind,
		public string $reference,
		/** @var array<string, string> display name per language; empty: disease names */
		public array $names = [],
		// series
		public ?DateInterval $until = null,
		public ?DateInterval $countDosesFromAge = null,
		public array $variants = [],
		public ?DateInterval $boosterEvery = null,
		public ?DateInterval $boosterGrace = null,
		// once + seasonal
		public ?DateInterval $fromAge = null,
		// once
		public ?DateTimeImmutable $bornAfter = null,
		public ?int $satisfiedByTotalDoses = null,
		public ?string $alignWith = null,
		public array $doses = [],
		// seasonal, "MM-DD"
		public ?string $seasonStart = null,
		public ?string $seasonEnd = null,
	) {
	}
}
