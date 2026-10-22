<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Catalog;

use JsonSerializable;

final readonly class Disease implements JsonSerializable {
	/**
	 * @param array<string, string> $names language code => name
	 */
	public function __construct(
		public string $id,
		public array $names,
	) {
	}

	/**
	 * Name in the requested language, falling back to English, then the id.
	 */
	public function getName(string $language): string {
		$baseLanguage = explode('_', $language)[0];
		return $this->names[$language] ?? $this->names[$baseLanguage] ?? $this->names['en'] ?? $this->id;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array {
		return ['id' => $this->id, 'names' => $this->names];
	}
}
