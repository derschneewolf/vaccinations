<?php

declare(strict_types=1);

namespace OCA\Vaccinations\BackgroundJob;

use OCA\Vaccinations\Service\NotificationService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;

/**
 * Checks all vaccination statuses once a day and notifies about pending vaccinations.
 */
class DailyCheck extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private readonly NotificationService $notificationService,
	) {
		parent::__construct($time);
		$this->setInterval(24 * 60 * 60);
		$this->setTimeSensitivity(IJob::TIME_INSENSITIVE);
	}

	/**
	 * @param mixed $argument
	 */
	protected function run($argument): void {
		$this->notificationService->run();
	}
}
