<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Listener;

use OCA\Vaccinations\Db\GuardianMapper;
use OCA\Vaccinations\Db\PersonMapper;
use OCA\Vaccinations\Db\RecordMapper;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserDeletedEvent;

/**
 * Removes all vaccination data of a user when the Nextcloud account is deleted.
 *
 * @template-implements IEventListener<UserDeletedEvent>
 */
class UserDeletedListener implements IEventListener {
	public function __construct(
		private readonly PersonMapper $personMapper,
		private readonly RecordMapper $recordMapper,
		private readonly GuardianMapper $guardianMapper,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof UserDeletedEvent) {
			return;
		}
		$userId = $event->getUser()->getUID();
		$this->recordMapper->deleteByUserId($userId);
		$this->personMapper->deleteByUserId($userId);
		$this->guardianMapper->deleteByUserId($userId);
	}
}
