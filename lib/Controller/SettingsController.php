<?php

declare(strict_types=1);

namespace OCA\Dossier\Controller;

use OCA\Dossier\AppInfo\Application;
use OCA\Dossier\Service\IndexerService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IAppConfig;
use OCP\IRequest;

class SettingsController extends Controller {
	public function __construct(
		IRequest $request,
		private IAppConfig $appConfig,
		private IndexerService $indexer,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * @AdminRequired
	 */
	public function save(string $scopes = '[]'): JSONResponse {
		$parsed = [];
		$decoded = json_decode($scopes, true);
		foreach ((is_array($decoded) ? $decoded : []) as $s) {
			if (!is_array($s)) {
				continue;
			}
			$owner = trim((string)($s['owner'] ?? ''));
			$folder = IndexerService::normalizeFolder((string)($s['folder'] ?? ''));
			if ($owner === '' || $folder === '/') {
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
	public function reindex(): JSONResponse {
		return new JSONResponse(['status' => 'ok', 'stats' => $this->indexer->indexAll()]);
	}
}
