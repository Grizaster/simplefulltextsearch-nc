<?php

declare(strict_types=1);

namespace OCA\Dossier\Settings;

use OCA\Dossier\AppInfo\Application;
use OCA\Dossier\Service\IndexerService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Settings\ISettings;

class AdminSettings implements ISettings {
	public function __construct(
		private IAppConfig $appConfig,
		private IURLGenerator $urlGenerator,
		private IL10N $l10n,
		private IUserManager $userManager,
	) {
	}

	public function getSection(): string {
		return 'dossier';
	}

	public function getPriority(): int {
		return 50;
	}

	public function getForm(): TemplateResponse {
		return new TemplateResponse(Application::APP_ID, 'settings/admin', [
			'titleText' => $this->l10n->t('Document search'),
			'hintText' => $this->l10n->t('Full-text search for your documents: the chosen folder becomes your digital file. Every PDF, every office document and even scanned pages (via OCR) are extracted and made searchable. Results appear directly in the Nextcloud search bar (web and app) with a text snippet from the document; tapping opens the file. New and changed files are indexed quietly in the background every 30 minutes. Technical name: SimpleFullTextFileSearch.'),
			'scopes' => IndexerService::scopesFromConfig($this->appConfig),
			'users' => $this->userIds(),
			'saveUrl' => $this->urlGenerator->linkToRoute('dossier.settings#save'),
			'reindexUrl' => $this->urlGenerator->linkToRoute('dossier.settings#reindex'),
		]);
	}

	/** @return string[] all user IDs, sorted */
	private function userIds(): array {
		$ids = array_map(static fn ($u) => $u->getUID(), $this->userManager->search(''));
		sort($ids);
		return $ids;
	}
}
