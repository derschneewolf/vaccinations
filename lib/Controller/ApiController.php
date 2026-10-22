<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Controller;

use OCA\Vaccinations\AppInfo\Application;
use OCA\Vaccinations\Catalog\Catalog;
use OCA\Vaccinations\Catalog\Disease;
use OCA\Vaccinations\Catalog\Vaccine;
use OCA\Vaccinations\Exception\ForbiddenException;
use OCA\Vaccinations\Exception\NotFoundException;
use OCA\Vaccinations\Exception\ValidationException;
use OCA\Vaccinations\Service\PersonService;
use OCA\Vaccinations\Service\RecordService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * OCS API, base path /ocs/v2.php/apps/vaccinations/api/v1
 *
 * Errors: 400 {field, message} for invalid input, 403 / 404 {message}.
 *
 * @phpstan-type ApiData array<array-key, mixed>|\JsonSerializable
 * @phpstan-type ApiResult DataResponse<Http::STATUS_OK, ApiData, array{}>|DataResponse<Http::STATUS_CREATED, ApiData, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{field: string, message: string}, array{}>|DataResponse<Http::STATUS_FORBIDDEN, array{message: string}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>
 */
class ApiController extends OCSController {
	public function __construct(
		IRequest $request,
		private readonly IUserSession $userSession,
		private readonly PersonService $personService,
		private readonly RecordService $recordService,
		private readonly Catalog $catalog,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Diseases and vaccines (the open data mapping).
	 * @return ApiResult
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/catalog')]
	public function catalog(): DataResponse {
		return new DataResponse([
			'diseases' => array_values(array_map(static fn (Disease $d): array => $d->jsonSerialize(), $this->catalog->getDiseases())),
			'vaccines' => array_values(array_map(static fn (Vaccine $v): array => $v->jsonSerialize(), $this->catalog->getVaccines())),
		]);
	}

	/**
	 * People the current user may manage: self first, then wards (admins: everyone).
	 * @return ApiResult
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/persons')]
	public function persons(): DataResponse {
		return new DataResponse($this->personService->listManageable($this->actorUid()));
	}

	/**
	 * @return ApiResult
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/persons/{userId}')]
	public function updatePerson(string $userId, ?string $birthDate = null): DataResponse {
		return $this->handle(fn (): array => $this->personService->updateBirthDate($this->actorUid(), $userId, $birthDate));
	}

	/**
	 * @return ApiResult
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/persons/{userId}/records')]
	public function records(string $userId): DataResponse {
		return $this->handle(fn (): array => $this->recordService->findForPerson($this->actorUid(), $userId));
	}

	/**
	 * @return ApiResult
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/persons/{userId}/records')]
	public function createRecord(
		string $userId,
		mixed $vaccineId = null,
		mixed $administeredOn = null,
		mixed $practice = null,
		mixed $batchNumber = null,
		mixed $note = null,
	): DataResponse {
		$input = compact('vaccineId', 'administeredOn', 'practice', 'batchNumber', 'note');
		return $this->handle(fn () => $this->recordService->create($this->actorUid(), $userId, $input), Http::STATUS_CREATED);
	}

	/**
	 * @return ApiResult
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/records/{id}')]
	public function updateRecord(
		int $id,
		mixed $vaccineId = null,
		mixed $administeredOn = null,
		mixed $practice = null,
		mixed $batchNumber = null,
		mixed $note = null,
	): DataResponse {
		$input = compact('vaccineId', 'administeredOn', 'practice', 'batchNumber', 'note');
		return $this->handle(fn () => $this->recordService->update($this->actorUid(), $id, $input));
	}

	/**
	 * @return ApiResult
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/v1/records/{id}')]
	public function deleteRecord(int $id): DataResponse {
		return $this->handle(function () use ($id): array {
			$this->recordService->delete($this->actorUid(), $id);
			return [];
		});
	}

	/**
	 * Maps domain exceptions to HTTP status codes.
	 *
	 * @param callable(): ApiData $action
	 * @param Http::STATUS_OK|Http::STATUS_CREATED $successStatus
	 * @return ApiResult
	 */
	private function handle(callable $action, int $successStatus = Http::STATUS_OK): DataResponse {
		try {
			$data = $action();
			return $successStatus === Http::STATUS_CREATED
				? new DataResponse($data, Http::STATUS_CREATED)
				: new DataResponse($data);
		} catch (ValidationException $e) {
			return new DataResponse(['field' => $e->field, 'message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (ForbiddenException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		} catch (NotFoundException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	private function actorUid(): string {
		// #[NoAdminRequired] without #[PublicPage] guarantees a logged-in user
		return $this->userSession->getUser()?->getUID() ?? '';
	}
}
