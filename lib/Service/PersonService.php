<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Service;

use OCA\Vaccinations\Db\Person;
use OCA\Vaccinations\Db\PersonMapper;
use OCA\Vaccinations\Exception\ForbiddenException;
use OCA\Vaccinations\Exception\NotFoundException;
use OCA\Vaccinations\Exception\ValidationException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;

/**
 * The people a user can see in the app (self, wards; admins: everyone)
 * together with their per-person settings.
 */
class PersonService {
	public function __construct(
		private readonly PersonMapper $personMapper,
		private readonly PermissionService $permissionService,
		private readonly IUserManager $userManager,
		private readonly IGroupManager $groupManager,
		private readonly InputValidator $validator,
		private readonly ITimeFactory $timeFactory,
	) {
	}

	/**
	 * @return list<array{userId: string, displayName: string, birthDate: ?string, scheduleId: string, isSelf: bool}>
	 */
	public function listManageable(string $actorUid): array {
		$userIds = $this->permissionService->getManageableUserIds($actorUid);
		if ($this->groupManager->isAdmin($actorUid)) {
			$userIds = array_values(array_unique([
				...$userIds,
				...array_map(static fn (IUser $user): string => $user->getUID(), $this->userManager->search('')),
			]));
		}

		$persons = [];
		foreach ($userIds as $userId) {
			$user = $this->userManager->get($userId);
			if ($user !== null) {
				$persons[] = $this->toView($user, $this->personMapper->findOrNew($userId), $actorUid);
			}
		}

		// self first, then alphabetically
		usort($persons, static fn (array $a, array $b): int
			=> [!$a['isSelf'], mb_strtolower($a['displayName'])] <=> [!$b['isSelf'], mb_strtolower($b['displayName'])]);
		return $persons;
	}

	/**
	 * @return array{userId: string, displayName: string, birthDate: ?string, scheduleId: string, isSelf: bool}
	 * @throws ForbiddenException|NotFoundException|ValidationException
	 */
	public function updateBirthDate(string $actorUid, string $userId, ?string $birthDate): array {
		$user = $this->requireManageableUser($actorUid, $userId);

		$parsedBirthDate = $birthDate === null || $birthDate === '' ? null : $this->validator->pastDate('birthDate', $birthDate);
		try {
			$person = $this->personMapper->findByUserId($userId);
			$isNew = false;
		} catch (DoesNotExistException) {
			$person = new Person();
			$person->setUserId($userId);
			$isNew = true;
		}
		$person->setBirthDate($parsedBirthDate);
		$person->setUpdatedAt($this->timeFactory->now());
		$person = $isNew ? $this->personMapper->insert($person) : $this->personMapper->update($person);

		return $this->toView($user, $person, $actorUid);
	}

	/**
	 * @throws ForbiddenException|NotFoundException
	 */
	public function requireManageableUser(string $actorUid, string $userId): IUser {
		$user = $this->userManager->get($userId);
		if ($user === null) {
			throw new NotFoundException('Unknown user');
		}
		if (!$this->permissionService->canManage($actorUid, $userId)) {
			throw new ForbiddenException('Not allowed to manage this person');
		}
		return $user;
	}

	/**
	 * @return array{userId: string, displayName: string, birthDate: ?string, scheduleId: string, isSelf: bool}
	 */
	private function toView(IUser $user, Person $person, string $actorUid): array {
		return [
			'userId' => $user->getUID(),
			'displayName' => $user->getDisplayName(),
			'birthDate' => $person->getBirthDate()?->format('Y-m-d'),
			'scheduleId' => $person->getScheduleId(),
			'isSelf' => $user->getUID() === $actorUid,
		];
	}
}
