<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Db;

use DateTimeImmutable;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method string getPersonUid()
 * @method void setPersonUid(string $personUid)
 * @method string getRuleId()
 * @method void setRuleId(string $ruleId)
 * @method string getDueFrom()
 * @method void setDueFrom(string $dueFrom)
 * @method string getLevel()
 * @method void setLevel(string $level)
 * @method DateTimeImmutable getNotifiedAt()
 * @method void setNotifiedAt(DateTimeImmutable $notifiedAt)
 */
class NotifiedState extends Entity {
	protected string $personUid = '';
	protected string $ruleId = '';
	protected string $dueFrom = '';
	protected string $level = '';
	protected ?DateTimeImmutable $notifiedAt = null;

	public function __construct() {
		$this->addType('personUid', Types::STRING);
		$this->addType('ruleId', Types::STRING);
		$this->addType('dueFrom', Types::STRING);
		$this->addType('level', Types::STRING);
		$this->addType('notifiedAt', Types::DATETIME_IMMUTABLE);
	}
}
