<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Schedule;

use DateInterval;
use DateTimeImmutable;
use JsonSerializable;
use OCA\Vaccinations\Catalog\CatalogException;

/**
 * A vaccination schedule from data/schedules/*.json (validated in CI).
 */
final readonly class Schedule implements JsonSerializable {
	/**
	 * @param array<string, string> $names
	 * @param list<string> $simplifications
	 * @param list<string> $sources
	 * @param list<Rule> $rules
	 */
	public function __construct(
		public string $id,
		public array $names,
		public string $version,
		public string $scope,
		public array $simplifications,
		public array $sources,
		public array $rules,
	) {
	}

	/**
	 * @param array<array-key, mixed> $data
	 * @throws CatalogException
	 */
	public static function fromArray(array $data): self {
		try {
			$rules = array_map(self::ruleFromArray(...), array_values((array)($data['rules'] ?? [])));
		} catch (\Exception $e) {
			throw new CatalogException('Invalid schedule ' . (string)($data['id'] ?? '?') . ': ' . $e->getMessage(), 0, $e);
		}
		return new self(
			id: (string)($data['id'] ?? ''),
			names: array_map('strval', (array)($data['name'] ?? [])),
			version: (string)($data['version'] ?? ''),
			scope: (string)($data['scope'] ?? ''),
			simplifications: array_values(array_map('strval', (array)($data['simplifications'] ?? []))),
			sources: array_values(array_map('strval', (array)($data['sources'] ?? []))),
			rules: $rules,
		);
	}

	public function getRule(string $id): ?Rule {
		foreach ($this->rules as $rule) {
			if ($rule->id === $id) {
				return $rule;
			}
		}
		return null;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'names' => $this->names,
			'version' => $this->version,
			'scope' => $this->scope,
			'simplifications' => $this->simplifications,
			'sources' => $this->sources,
		];
	}

	/**
	 * @param mixed $data
	 */
	private static function ruleFromArray($data): Rule {
		$data = (array)$data;
		$then = isset($data['then']) ? (array)$data['then'] : null;
		return new Rule(
			id: (string)$data['id'],
			diseaseIds: array_values(array_map('strval', (array)$data['diseases'])),
			kind: (string)$data['kind'],
			reference: (string)($data['reference'] ?? ''),
			names: array_map('strval', (array)($data['name'] ?? [])),
			until: self::interval($data['until'] ?? null),
			countDosesFromAge: self::interval($data['countDosesFromAge'] ?? null),
			variants: array_map(
				static fn ($variant): Variant => new Variant(
					self::interval(((array)$variant)['fromAge'] ?? 'P0D') ?? new DateInterval('P0D'),
					self::dosesFromArray(((array)$variant)['doses'] ?? []),
				),
				array_values((array)($data['variants'] ?? [])),
			),
			boosterEvery: self::interval($then['every'] ?? null),
			boosterGrace: self::interval($then['grace'] ?? null),
			laterBoosterFromAge: self::interval(((array)($then['later'] ?? []))['fromAge'] ?? null),
			laterBoosterEvery: self::interval(((array)($then['later'] ?? []))['every'] ?? null),
			fromAge: self::interval($data['fromAge'] ?? null),
			bornAfter: isset($data['bornAfter']) ? new DateTimeImmutable((string)$data['bornAfter']) : null,
			satisfiedByTotalDoses: isset($data['satisfiedByTotalDoses']) ? (int)$data['satisfiedByTotalDoses'] : null,
			alignWith: isset($data['alignWith']) ? (string)$data['alignWith'] : null,
			doses: self::dosesFromArray($data['doses'] ?? []),
			seasonStart: isset($data['seasonStart']) ? (string)$data['seasonStart'] : null,
			seasonEnd: isset($data['seasonEnd']) ? (string)$data['seasonEnd'] : null,
		);
	}

	/**
	 * @param mixed $doses
	 * @return list<DoseSlot>
	 */
	private static function dosesFromArray($doses): array {
		return array_map(static function ($dose): DoseSlot {
			$dose = (array)$dose;
			return new DoseSlot(
				label: (string)($dose['label'] ?? ''),
				age: self::interval($dose['age'] ?? null),
				dueUntil: self::interval($dose['dueUntil'] ?? null),
				minInterval: self::interval($dose['minInterval'] ?? null),
				maxInterval: self::interval($dose['maxInterval'] ?? null),
			);
		}, array_values((array)$doses));
	}

	private static function interval(mixed $value): ?DateInterval {
		return is_string($value) && $value !== '' ? new DateInterval($value) : null;
	}
}
