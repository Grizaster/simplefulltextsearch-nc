<?php

declare(strict_types=1);

namespace OCA\SimpleFullTextFileSearch\Controller;

use OCA\SimpleFullTextFileSearch\AppInfo\Application;
use OCA\SimpleFullTextFileSearch\Service\IndexerService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Group\IGroupManager;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;

class SettingsController extends Controller {
	public function __construct(
		IRequest $request,
		private IAppConfig $appConfig,
		private IndexerService $indexer,
		private IUserManager $userManager,
		private ?IGroupManager $groupManager = null,
		private ?IUserSession $userSession = null,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * @AdminRequired
	 */
	#[AdminRequired]
	public function save(string $scopes = '[]'): JSONResponse {
		if ($this->userSession !== null && $this->groupManager !== null) {
			$user = $this->userSession->getUser();
			if ($user === null || !$this->groupManager->isAdmin($user->getUID())) {
				return new JSONResponse(['status' => 'error', 'message' => 'Admin permissions required'], Http::STATUS_FORBIDDEN);
			}
		}

		$parsed = [];
		$decoded = json_decode($scopes, true);
		foreach ((is_array($decoded) ? $decoded : []) as $s) {
			if (!is_array($s)) {
				continue;
			}
			$owner = trim((string)($s['owner'] ?? ''));
			$folder = IndexerService::normalizeFolder((string)($s['folder'] ?? ''));
			if ($owner === '' || $folder === '/' || $this->userManager->get($owner) === null) {
				continue;
			}
			$parsed[] = ['owner' => $owner, 'folder' => $folder];
			if (count($parsed) >= 20) {
				break;
			}
		}
		if ($parsed === []) {
			return new JSONResponse(['status' => 'error', 'message' => 'Mindestens einen Suchbereich mit Nutzer und Ordner angeben'], Http::STATUS_BAD_REQUEST);
		}
		$this->appConfig->setValueString(Application::APP_ID, 'scopes', json_encode(array_values($parsed)));
		return new JSONResponse(['status' => 'ok', 'scopes' => $parsed]);
	}

	/**
	 * @AdminRequired
	 */
	#[AdminRequired]
	public function reindex(): JSONResponse {
		if ($this->userSession !== null && $this->groupManager !== null) {
			$user = $this->userSession->getUser();
			if ($user === null || !$this->groupManager->isAdmin($user->getUID())) {
				return new JSONResponse(['status' => 'error', 'message' => 'Admin permissions required'], Http::STATUS_FORBIDDEN);
			}
		}

		return new JSONResponse(['status' => 'ok', 'stats' => $this->indexer->indexAll()]);
	}
}
