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
	public function save(string $scopeOwner = '', string $scopeFolder = ''): JSONResponse {
		$owner = trim($scopeOwner);
		$folder = '/' . trim($scopeFolder, "/ \t\n\r\0\x0B");
		if ($owner === '' || $folder === '/') {
			return new JSONResponse(['status' => 'error', 'message' => 'Nutzer und Ordner angeben'], Http::STATUS_BAD_REQUEST);
		}
		$this->appConfig->setValueString(Application::APP_ID, 'scope_owner', $owner);
		$this->appConfig->setValueString(Application::APP_ID, 'scope_folder', $folder);
		return new JSONResponse(['status' => 'ok', 'owner' => $owner, 'folder' => $folder]);
	}

	/**
	 * @AdminRequired
	 */
	public function reindex(): JSONResponse {
		return new JSONResponse(['status' => 'ok', 'stats' => $this->indexer->indexUser()]);
	}
}
