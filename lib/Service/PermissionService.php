<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Service;

use OCA\Vaccinations\Db\GuardianMapper;
use OCP\IGroupManager;

/**
 * Who may read/write whose vaccination records.
 *
 * A user may manage records of
 *  - themselves,
 *  - every user they are guardian of (vacc_guardian, e.g. parent → child),
 *  - everyone, if they are a Nextcloud admin.
 *
 * Read and write rights are intentionally identical for now – a guardian
 * who can see a child's records also needs to maintain them.
 */
class PermissionService {

	public function __construct(
		private readonly GuardianMapper $guardianMapper,
		private readonly IGroupManager $groupManager,
	) {
	}

	public function canManage(string $actorUid, string $personUid): bool {
		if ($actorUid === $personUid) {
			return true;
		}
		if ($this->groupManager->isAdmin($actorUid)) {
			return true;
		}
		return $this->guardianMapper->isGuardianOf($actorUid, $personUid);
	}

	/**
	 * Users shown in the person list of the actor: self first, then wards.
	 * Admins additionally get access to all users via the admin view.
	 *
	 * @return list<string>
	 */
	public function getManageableUserIds(string $actorUid): array {
		return array_values(array_unique([
			$actorUid,
			...$this->guardianMapper->findWardUids($actorUid),
		]));
	}
}
