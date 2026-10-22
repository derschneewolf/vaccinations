<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Exception;

use RuntimeException;

/**
 * Invalid input; carries the offending field so the UI can highlight it.
 */
class ValidationException extends RuntimeException {
	public function __construct(
		public readonly string $field,
		string $message,
	) {
		parent::__construct($message);
	}
}
