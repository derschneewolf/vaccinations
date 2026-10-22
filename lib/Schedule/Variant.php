<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Schedule;

use DateInterval;

/**
 * Dose plan of a series, chosen by the age at the first dose.
 */
final readonly class Variant {
	/**
	 * @param list<DoseSlot> $doses
	 */
	public function __construct(
		public DateInterval $fromAge,
		public array $doses,
	) {
	}
}
