<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Notification;

use DateTime;
use OCA\Vaccinations\AppInfo\Application;
use OCA\Vaccinations\Catalog\Catalog;
use OCA\Vaccinations\Service\NotificationService;
use OCP\IDateTimeFormatter;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

/**
 * Renders the notifications of NotificationService in the recipient's language.
 * Names are resolved at display time, so renamed users or vaccines stay current.
 */
class Notifier implements INotifier {
	public function __construct(
		private readonly IFactory $l10nFactory,
		private readonly IURLGenerator $urlGenerator,
		private readonly IUserManager $userManager,
		private readonly IDateTimeFormatter $dateTimeFormatter,
		private readonly Catalog $catalog,
	) {
	}

	public function getID(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return $this->l10nFactory->get(Application::APP_ID)->t('Vaccinations');
	}

	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID || $notification->getSubject() !== NotificationService::SUBJECT) {
			throw new UnknownNotificationException();
		}
		$l = $this->l10nFactory->get(Application::APP_ID, $languageCode);
		$params = $notification->getSubjectParameters();

		$personUid = (string)($params['personUid'] ?? '');
		$person = $this->userManager->getDisplayName($personUid) ?? $personUid;
		$vaccination = $this->ruleName((string)($params['scheduleId'] ?? ''), (string)($params['ruleId'] ?? ''), $languageCode);
		$date = $this->dateTimeFormatter->formatDate(new DateTime((string)($params['dueFrom'] ?? 'now')), 'medium', null, $l);

		$subject = match ($params['level'] ?? '') {
			'overdue' => $l->t('%1$s: %2$s is overdue (due since %3$s)', [$person, $vaccination, $date]),
			'due' => $l->t('%1$s: %2$s is due (since %3$s)', [$person, $vaccination, $date]),
			default => $l->t('%1$s: %2$s is coming up (from %3$s)', [$person, $vaccination, $date]),
		};

		$notification->setParsedSubject($subject)
			->setLink($this->urlGenerator->linkToRouteAbsolute('vaccinations.page.index', ['person' => $personUid]))
			->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')));
		return $notification;
	}

	private function ruleName(string $scheduleId, string $ruleId, string $languageCode): string {
		$rule = $this->catalog->getSchedule($scheduleId)?->getRule($ruleId);
		if ($rule === null) {
			return $ruleId;
		}
		$language = explode('_', $languageCode)[0];
		if ($rule->names !== []) {
			return $rule->names[$languageCode] ?? $rule->names[$language] ?? $rule->names['en'] ?? $ruleId;
		}
		return implode(', ', array_map(
			fn (string $diseaseId): string => $this->catalog->getDisease($diseaseId)?->getName($languageCode) ?? $diseaseId,
			$rule->diseaseIds,
		));
	}
}
