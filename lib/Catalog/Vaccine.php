<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Catalog;

use JsonSerializable;

final readonly class Vaccine implements JsonSerializable {
	/**
	 * @param list<string> $diseaseIds
	 * @param list<string> $aliases
	 */
	public function __construct(
		public string $id,
		public string $name,
		public array $diseaseIds,
		public array $aliases = [],
		public ?string $manufacturer = null,
		public ?string $type = null,
		public string $variant = 'any',
		public bool $discontinued = false,
	) {
	}

	public function protectsAgainst(string $diseaseId): bool {
		return in_array($diseaseId, $this->diseaseIds, true);
	}

	/**
	 * Lower-cased terms used for search.
	 *
	 * @return list<string>
	 */
	public function getSearchTerms(): array {
		return array_map(
			mb_strtolower(...),
			[$this->name, ...$this->aliases, ...($this->manufacturer !== null ? [$this->manufacturer] : [])],
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'name' => $this->name,
			'aliases' => $this->aliases,
			'manufacturer' => $this->manufacturer,
			'diseaseIds' => $this->diseaseIds,
			'type' => $this->type,
			'variant' => $this->variant,
			'discontinued' => $this->discontinued,
		];
	}
}
