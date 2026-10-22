<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * Grants $guardianUid the right to maintain the records of $wardUid.
 *
 * @method string getGuardianUid()
 * @method void setGuardianUid(string $guardianUid)
 * @method string getWardUid()
 * @method void setWardUid(string $wardUid)
 */
class Guardian extends Entity implements JsonSerializable {
	protected string $guardianUid = '';
	protected string $wardUid = '';

	public function __construct() {
		$this->addType('guardianUid', Types::STRING);
		$this->addType('wardUid', Types::STRING);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'guardianUid' => $this->guardianUid,
			'wardUid' => $this->wardUid,
		];
	}
}
