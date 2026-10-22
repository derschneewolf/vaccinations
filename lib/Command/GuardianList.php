<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Command;

use OCA\Vaccinations\Db\GuardianMapper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GuardianList extends Command {
	public function __construct(
		private readonly GuardianMapper $guardianMapper,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this->setName('vaccinations:guardian:list')
			->setDescription('List all guardian relations');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$rows = [];
		foreach ($this->guardianMapper->findAll() as $guardian) {
			$rows[] = [$guardian->getGuardianUid(), $guardian->getWardUid()];
		}
		if ($rows === []) {
			$output->writeln('No guardian relations');
			return self::SUCCESS;
		}
		(new Table($output))->setHeaders(['Guardian', 'Ward'])->setRows($rows)->render();
		return self::SUCCESS;
	}
}
