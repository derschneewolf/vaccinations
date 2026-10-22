<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Db;

use DateTimeImmutable;
use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * One administered vaccine dose.
 *
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getVaccineId()
 * @method void setVaccineId(string $vaccineId)
 * @method DateTimeImmutable getAdministeredOn()
 * @method void setAdministeredOn(DateTimeImmutable $administeredOn)
 * @method string|null getPractice()
 * @method void setPractice(?string $practice)
 * @method string|null getBatchNumber()
 * @method void setBatchNumber(?string $batchNumber)
 * @method string|null getNote()
 * @method void setNote(?string $note)
 * @method string getCreatedBy()
 * @method void setCreatedBy(string $createdBy)
 * @method DateTimeImmutable getCreatedAt()
 * @method void setCreatedAt(DateTimeImmutable $createdAt)
 * @method DateTimeImmutable getUpdatedAt()
 * @method void setUpdatedAt(DateTimeImmutable $updatedAt)
 */
class Record extends Entity implements JsonSerializable {
	protected string $userId = '';
	protected string $vaccineId = '';
	protected ?DateTimeImmutable $administeredOn = null;
	protected ?string $practice = null;
	protected ?string $batchNumber = null;
	protected ?string $note = null;
	protected string $createdBy = '';
	protected ?DateTimeImmutable $createdAt = null;
	protected ?DateTimeImmutable $updatedAt = null;

	public function __construct() {
		$this->addType('userId', Types::STRING);
		$this->addType('vaccineId', Types::STRING);
		$this->addType('administeredOn', Types::DATE_IMMUTABLE);
		$this->addType('practice', Types::STRING);
		$this->addType('batchNumber', Types::STRING);
		$this->addType('note', Types::TEXT);
		$this->addType('createdBy', Types::STRING);
		$this->addType('createdAt', Types::DATETIME_IMMUTABLE);
		$this->addType('updatedAt', Types::DATETIME_IMMUTABLE);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'userId' => $this->userId,
			'vaccineId' => $this->vaccineId,
			'administeredOn' => $this->administeredOn?->format('Y-m-d'),
			'practice' => $this->practice,
			'batchNumber' => $this->batchNumber,
			'note' => $this->note,
			'createdBy' => $this->createdBy,
		];
	}
}
