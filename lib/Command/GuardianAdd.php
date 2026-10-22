<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Command;

use OCA\Vaccinations\Db\Guardian;
use OCA\Vaccinations\Db\GuardianMapper;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GuardianAdd extends Command {
	public function __construct(
		private readonly GuardianMapper $guardianMapper,
		private readonly IUserManager $userManager,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this->setName('vaccinations:guardian:add')
			->setDescription('Allow a user to maintain the vaccination records of another user (e.g. parent → child)')
			->addArgument('guardian', InputArgument::REQUIRED, 'User id of the guardian')
			->addArgument('ward', InputArgument::REQUIRED, 'User id of the person being looked after');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$guardianUid = (string)$input->getArgument('guardian');
		$wardUid = (string)$input->getArgument('ward');

		foreach ([$guardianUid, $wardUid] as $uid) {
			if (!$this->userManager->userExists($uid)) {
				$output->writeln("<error>User \"$uid\" does not exist</error>");
				return self::FAILURE;
			}
		}
		if ($guardianUid === $wardUid) {
			$output->writeln('<error>Guardian and ward must be different users</error>');
			return self::FAILURE;
		}
		if ($this->guardianMapper->isGuardianOf($guardianUid, $wardUid)) {
			$output->writeln("\"$guardianUid\" already looks after \"$wardUid\"");
			return self::SUCCESS;
		}

		$guardian = new Guardian();
		$guardian->setGuardianUid($guardianUid);
		$guardian->setWardUid($wardUid);
		$this->guardianMapper->insert($guardian);

		$output->writeln("<info>\"$guardianUid\" now looks after \"$wardUid\"</info>");
		return self::SUCCESS;
	}
}
