<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Command;

use OCA\Vaccinations\Service\NotificationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Runs the daily check immediately – handy for testing.
 */
class Notify extends Command {
	public function __construct(
		private readonly NotificationService $notificationService,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this->setName('vaccinations:notify')
			->setDescription('Check all vaccination statuses now and send notifications for upcoming, due and overdue vaccinations')
			->addArgument('user', InputArgument::OPTIONAL, 'Only check this person');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$user = $input->getArgument('user');
		$sent = is_string($user) ? $this->notificationService->runForPerson($user) : $this->notificationService->run();
		$output->writeln("<info>$sent notification(s) sent</info>");
		return self::SUCCESS;
	}
}
