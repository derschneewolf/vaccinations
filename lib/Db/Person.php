<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Db;

use DateTimeImmutable;
use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method DateTimeImmutable|null getBirthDate()
 * @method void setBirthDate(?DateTimeImmutable $birthDate)
 * @method string getScheduleId()
 * @method void setScheduleId(string $scheduleId)
 * @method DateTimeImmutable getUpdatedAt()
 * @method void setUpdatedAt(DateTimeImmutable $updatedAt)
 */
class Person extends Entity implements JsonSerializable {
	public const DEFAULT_SCHEDULE = 'de-stiko';

	protected string $userId = '';
	protected ?DateTimeImmutable $birthDate = null;
	protected string $scheduleId = self::DEFAULT_SCHEDULE;
	protected ?DateTimeImmutable $updatedAt = null;

	public function __construct() {
		$this->addType('userId', Types::STRING);
		$this->addType('birthDate', Types::DATE_IMMUTABLE);
		$this->addType('scheduleId', Types::STRING);
		$this->addType('updatedAt', Types::DATETIME_IMMUTABLE);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array {
		return [
			'userId' => $this->userId,
			'birthDate' => $this->birthDate?->format('Y-m-d'),
			'scheduleId' => $this->scheduleId,
		];
	}
}
