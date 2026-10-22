<?php

declare(strict_types=1);

namespace OCA\Vaccinations\AppInfo;

use OCA\Vaccinations\Catalog\Catalog;
use OCA\Vaccinations\Listener\UserDeletedListener;
use OCP\App\IAppManager;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\User\Events\UserDeletedEvent;
use Psr\Container\ContainerInterface;

class Application extends App implements IBootstrap {
	public const APP_ID = 'vaccinations';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerService(Catalog::class, static function (ContainerInterface $container): Catalog {
			$appPath = $container->get(IAppManager::class)->getAppPath(self::APP_ID);
			return new Catalog($appPath . '/data');
		});
		$context->registerEventListener(UserDeletedEvent::class, UserDeletedListener::class);
	}

	public function boot(IBootContext $context): void {
	}
}
