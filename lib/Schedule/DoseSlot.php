<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Schedule;

use DateInterval;

/**
 * One expected dose within a rule.
 */
final readonly class DoseSlot {
	public function __construct(
		public string $label,
		/** recommended from this age (series) – null: as soon as the rule applies */
		public ?DateInterval $age = null,
		/** age after which the dose is overdue (series) */
		public ?DateInterval $dueUntil = null,
		public ?DateInterval $minInterval = null,
		/** interval to the previous dose after which the dose is overdue */
		public ?DateInterval $maxInterval = null,
		/** a dose given before this age does not count for this slot, e.g. a 4th infant dose is no school-age booster */
		public ?DateInterval $earliestAge = null,
	) {
	}
}
