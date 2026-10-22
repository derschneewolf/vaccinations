<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Catalog;

use JsonException;
use OCA\Vaccinations\Schedule\Schedule;

/**
 * Read-only access to the open data in data/ (diseases and vaccines).
 *
 * The files are small and validated in CI, so they are parsed lazily once
 * per request; no persistent cache is needed. Structural checks here only
 * guard against a broken installation, not against content errors.
 */
class Catalog {
	/** @var array<string, Disease>|null */
	private ?array $diseases = null;
	/** @var array<string, Vaccine>|null */
	private ?array $vaccines = null;
	/** @var array<string, Schedule>|null */
	private ?array $schedules = null;

	public function __construct(
		private readonly string $dataDir,
	) {
	}

	/**
	 * @return array<string, Disease> keyed by id
	 */
	public function getDiseases(): array {
		return $this->diseases ??= $this->loadDiseases();
	}

	public function getDisease(string $id): ?Disease {
		return $this->getDiseases()[$id] ?? null;
	}

	/**
	 * @return array<string, Vaccine> keyed by id, sorted by name
	 */
	public function getVaccines(): array {
		return $this->vaccines ??= $this->loadVaccines();
	}

	public function getVaccine(string $id): ?Vaccine {
		return $this->getVaccines()[$id] ?? null;
	}

	/**
	 * @return array<string, Schedule> keyed by id
	 */
	public function getSchedules(): array {
		if ($this->schedules === null) {
			$this->schedules = [];
			foreach (glob($this->dataDir . '/schedules/*.json') ?: [] as $file) {
				$schedule = Schedule::fromArray($this->readJson($file));
				$this->schedules[$schedule->id] = $schedule;
			}
		}
		return $this->schedules;
	}

	public function getSchedule(string $id): ?Schedule {
		return $this->getSchedules()[$id] ?? null;
	}

	/**
	 * Vaccines that protect against the given disease.
	 *
	 * @return list<Vaccine>
	 */
	public function getVaccinesForDisease(string $diseaseId): array {
		return array_values(array_filter(
			$this->getVaccines(),
			static fn (Vaccine $vaccine): bool => $vaccine->protectsAgainst($diseaseId),
		));
	}

	/**
	 * Autocomplete search over name, aliases and manufacturer.
	 * Prefix matches rank before substring matches, discontinued products last.
	 *
	 * @return list<Vaccine>
	 */
	public function search(string $query, int $limit = 20): array {
		$query = mb_strtolower(trim($query));
		if ($query === '') {
			return array_slice(array_values($this->getVaccines()), 0, $limit);
		}

		$scored = [];
		foreach ($this->getVaccines() as $vaccine) {
			$score = $this->matchScore($vaccine, $query);
			if ($score !== null) {
				$scored[] = ['vaccine' => $vaccine, 'score' => $score + ($vaccine->discontinued ? 10 : 0)];
			}
		}
		usort($scored, static fn (array $a, array $b): int => [$a['score'], $a['vaccine']->name] <=> [$b['score'], $b['vaccine']->name]);

		return array_slice(array_column($scored, 'vaccine'), 0, $limit);
	}

	private function matchScore(Vaccine $vaccine, string $query): ?int {
		$best = null;
		foreach ($vaccine->getSearchTerms() as $term) {
			$position = mb_strpos($term, $query);
			if ($position === false) {
				continue;
			}
			$score = $position === 0 ? 0 : 1;
			$best = $best === null ? $score : min($best, $score);
		}
		return $best;
	}

	/**
	 * @return array<string, Disease>
	 */
	private function loadDiseases(): array {
		$data = $this->readJson($this->dataDir . '/diseases.json');
		$diseases = [];
		foreach ($data['diseases'] ?? [] as $entry) {
			if (!is_array($entry) || !is_string($entry['id'] ?? null) || !is_array($entry['name'] ?? null)) {
				throw new CatalogException('Invalid entry in diseases.json');
			}
			$diseases[$entry['id']] = new Disease($entry['id'], array_map('strval', $entry['name']));
		}
		return $diseases;
	}

	/**
	 * @return array<string, Vaccine>
	 */
	private function loadVaccines(): array {
		$files = glob($this->dataDir . '/vaccines/*.json') ?: [];
		$vaccines = [];
		foreach ($files as $file) {
			$data = $this->readJson($file);
			if (!is_string($data['id'] ?? null) || !is_string($data['name'] ?? null) || !is_array($data['diseases'] ?? null)) {
				throw new CatalogException('Invalid vaccine file ' . basename($file));
			}
			$vaccines[$data['id']] = new Vaccine(
				id: $data['id'],
				name: $data['name'],
				diseaseIds: array_values(array_map('strval', $data['diseases'])),
				aliases: array_values(array_map('strval', $data['aliases'] ?? [])),
				manufacturer: isset($data['manufacturer']) ? (string)$data['manufacturer'] : null,
				type: isset($data['type']) ? (string)$data['type'] : null,
				variant: (string)($data['variant'] ?? 'any'),
				discontinued: (bool)($data['discontinued'] ?? false),
			);
		}
		uasort($vaccines, static fn (Vaccine $a, Vaccine $b): int => strcasecmp($a->name, $b->name));
		return $vaccines;
	}

	/**
	 * @return array<array-key, mixed>
	 */
	private function readJson(string $path): array {
		$content = @file_get_contents($path);
		if ($content === false) {
			throw new CatalogException('Cannot read ' . $path);
		}
		try {
			$data = json_decode($content, true, 64, JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			throw new CatalogException('Invalid JSON in ' . basename($path), 0, $e);
		}
		if (!is_array($data)) {
			throw new CatalogException('Unexpected content in ' . basename($path));
		}
		return $data;
	}
}
