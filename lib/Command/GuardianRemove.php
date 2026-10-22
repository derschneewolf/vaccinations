<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Command;

use OCA\Vaccinations\Db\GuardianMapper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GuardianRemove extends Command {
	public function __construct(
		private readonly GuardianMapper $guardianMapper,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this->setName('vaccinations:guardian:remove')
			->setDescription('Revoke a guardian relation')
			->addArgument('guardian', InputArgument::REQUIRED, 'User id of the guardian')
			->addArgument('ward', InputArgument::REQUIRED, 'User id of the person being looked after');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$guardianUid = (string)$input->getArgument('guardian');
		$wardUid = (string)$input->getArgument('ward');

		if ($this->guardianMapper->deletePair($guardianUid, $wardUid) === 0) {
			$output->writeln("\"$guardianUid\" does not look after \"$wardUid\"");
			return self::SUCCESS;
		}
		$output->writeln("<info>Removed: \"$guardianUid\" → \"$wardUid\"</info>");
		return self::SUCCESS;
	}
}
