<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Controller;

use OCA\Vaccinations\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\Util;

#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
class PageController extends Controller {
	public function __construct(IRequest $request) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * @return TemplateResponse<Http::STATUS_OK, array{}>
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/')]
	public function index(): TemplateResponse {
		Util::addScript(Application::APP_ID, Application::APP_ID . '-main');
		Util::addStyle(Application::APP_ID, Application::APP_ID . '-main');
		return new TemplateResponse(Application::APP_ID, 'main');
	}
}
