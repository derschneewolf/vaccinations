<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Tests\Unit\Catalog;

use OCA\Vaccinations\Catalog\Catalog;
use OCA\Vaccinations\Catalog\CatalogException;
use OCA\Vaccinations\Catalog\Vaccine;
use PHPUnit\Framework\TestCase;

class CatalogTest extends TestCase {
	private Catalog $catalog;

	protected function setUp(): void {
		$this->catalog = new Catalog(__DIR__ . '/../../fixtures/catalog');
	}

	public function testLoadsDiseasesKeyedById(): void {
		$diseases = $this->catalog->getDiseases();

		$this->assertSame(['hepatitis-a', 'hepatitis-b', 'measles'], array_keys($diseases));
		$this->assertSame('Masern', $diseases['measles']->getName('de'));
		$this->assertSame('Masern', $diseases['measles']->getName('de_DE'));
		$this->assertSame('Measles', $diseases['measles']->getName('fr'));
	}

	public function testLoadsVaccinesSortedByName(): void {
		$names = array_map(static fn (Vaccine $v): string => $v->name, array_values($this->catalog->getVaccines()));

		$this->assertSame(['Altrix', 'Havrix 1440', 'M-M-RvaxPro', 'Twinrix Erwachsene'], $names);
	}

	public function testCombinationVaccineMapsToAllDiseases(): void {
		$twinrix = $this->catalog->getVaccine('twinrix-adult');

		$this->assertNotNull($twinrix);
		$this->assertSame(['hepatitis-a', 'hepatitis-b'], $twinrix->diseaseIds);
		$this->assertTrue($twinrix->protectsAgainst('hepatitis-b'));
		$this->assertFalse($twinrix->protectsAgainst('measles'));
	}

	public function testUnknownIdsReturnNull(): void {
		$this->assertNull($this->catalog->getVaccine('does-not-exist'));
		$this->assertNull($this->catalog->getDisease('does-not-exist'));
	}

	public function testVaccinesForDisease(): void {
		$ids = array_map(static fn (Vaccine $v): string => $v->id, $this->catalog->getVaccinesForDisease('hepatitis-a'));

		$this->assertSame(['old-hep-a', 'havrix', 'twinrix-adult'], $ids);
	}

	public function testSearchRanksPrefixBeforeSubstring(): void {
		// "rix": no prefix match; all substring matches, alphabetical
		$this->assertSame(['havrix', 'twinrix-adult', 'old-hep-a'], $this->searchIds('rix'));
		// "hav" is a prefix of Havrix
		$this->assertSame(['havrix'], $this->searchIds('HAV'));
	}

	public function testSearchMatchesAliasesAndManufacturer(): void {
		$this->assertSame(['old-hep-a'], $this->searchIds('hep-a alt'));
		$this->assertSame(['mmr-vaxpro'], $this->searchIds('msd'));
	}

	public function testSearchRanksDiscontinuedLast(): void {
		$this->assertSame('old-hep-a', array_slice($this->searchIds('r'), -1)[0]);
	}

	public function testSearchRespectsLimitAndEmptyQuery(): void {
		$this->assertCount(2, $this->catalog->search('', 2));
		$this->assertSame([], $this->catalog->search('xyz'));
	}

	public function testBrokenJsonThrows(): void {
		$catalog = new Catalog(__DIR__ . '/../../fixtures/broken');

		$this->expectException(CatalogException::class);
		$catalog->getDiseases();
	}

	public function testShippedDataIsLoadable(): void {
		$catalog = new Catalog(__DIR__ . '/../../../data');

		$this->assertNotEmpty($catalog->getDiseases());
		foreach ($catalog->getVaccines() as $vaccine) {
			foreach ($vaccine->diseaseIds as $diseaseId) {
				$this->assertNotNull($catalog->getDisease($diseaseId), "$vaccine->id → $diseaseId");
			}
		}
	}

	/**
	 * @return list<string>
	 */
	private function searchIds(string $query): array {
		return array_map(static fn (Vaccine $v): string => $v->id, $this->catalog->search($query));
	}
}
