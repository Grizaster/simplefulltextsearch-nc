<?php

declare(strict_types=1);

namespace OCA\SimpleFullTextFileSearch\Settings;

use OCA\SimpleFullTextFileSearch\AppInfo\Application;
use OCA\SimpleFullTextFileSearch\Service\IndexerService;
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
		return 'simplefulltextsearch';
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
			'saveUrl' => $this->urlGenerator->linkToRoute('simplefulltextsearch.settings#save'),
			'reindexUrl' => $this->urlGenerator->linkToRoute('simplefulltextsearch.settings#reindex'),
			'scopesLabel' => $this->l10n->t('Search scopes — each entry is a folder that is searched in full'),
			'ownerEmptyLabel' => $this->l10n->t('— Select user —'),
			'notExistingLabel' => $this->l10n->t('(no longer available)'),
			'folderPlaceholder' => $this->l10n->t('Folder, e.g. /Documents'),
			'addLabel' => $this->l10n->t('+ Add search scope'),
			'saveLabel' => $this->l10n->t('Save'),
			'reindexLabel' => $this->l10n->t('Reindex now'),
			// JS-Texte serverseitig uebersetzt ueber data-strings uebergeben
			// (kein l10n-JS-Pipeline noetig, funktioniert in jeder NC-Version).
			'jsStrings' => [
				'saving' => $this->l10n->t('Saving …'),
				'saved' => $this->l10n->t('Saved: {n} search scope(s)'),
				'error' => $this->l10n->t('Error: '),
				'indexing' => $this->l10n->t('Indexing … (this may take a while for many documents)'),
				'scheduled' => $this->l10n->t('Indexing scheduled — it runs with the next cron (usually within minutes).'),
			],
		]);
	}

	/** @return string[] all user IDs, sorted */
	private function userIds(): array {
		$ids = array_map(static fn ($u) => $u->getUID(), $this->userManager->search(''));
		sort($ids);
		return $ids;
	}
}
