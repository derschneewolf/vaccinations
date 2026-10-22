<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Service;

use DateTimeImmutable;
use OCA\Vaccinations\Exception\ValidationException;
use OCP\AppFramework\Utility\ITimeFactory;

/**
 * Shared checks for user input. Messages are English and end up in the
 * API response; the frontend shows its own translated text per field.
 */
class InputValidator {
	public function __construct(
		private readonly ITimeFactory $timeFactory,
	) {
	}

	/**
	 * Parses a Y-m-d date that must not lie in the future.
	 */
	public function pastDate(string $field, string $value): DateTimeImmutable {
		$date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
		if ($date === false || $date->format('Y-m-d') !== $value) {
			throw new ValidationException($field, 'Expected a date in the format YYYY-MM-DD');
		}
		// one day of tolerance: the server clock is UTC, the user may be ahead of it
		if ($date > $this->today()->modify('+1 day')) {
			throw new ValidationException($field, 'Date must not be in the future');
		}
		if ($date < new DateTimeImmutable('1900-01-01')) {
			throw new ValidationException($field, 'Date is too far in the past');
		}
		return $date;
	}

	/**
	 * Trims the value; empty strings become null.
	 */
	public function optionalText(string $field, ?string $value, int $maxLength): ?string {
		$value = $value === null ? null : trim($value);
		if ($value === null || $value === '') {
			return null;
		}
		if (mb_strlen($value) > $maxLength) {
			throw new ValidationException($field, "At most $maxLength characters allowed");
		}
		return $value;
	}

	private function today(): DateTimeImmutable {
		return $this->timeFactory->now()->setTime(0, 0);
	}
}
