<?php

declare(strict_types=1);

namespace OCA\Dossier\Search;

use OCA\Dossier\Db\IndexMapper;
use OCA\Dossier\Service\IndexerService;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IPreview;
use OCP\IURLGenerator;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;
use OCP\IUser;

class Provider implements IProvider {
	public function __construct(
		private IndexMapper $mapper,
		private IRootFolder $rootFolder,
		private IURLGenerator $urlGenerator,
		private IPreview $previewManager,
		private IMimeTypeDetector $mimeTypeDetector,
		private IAppConfig $appConfig,
		private IL10N $l10n,
	) {
	}

	public function getId(): string {
		// Konfigurierbar wegen Nextcloud-iOS 35.0.0: die App oeffnet nur Treffer der
		// Provider "files"/"fulltextsearch" nativ (Rest = Webview-Fallback). Default ist
		// die eigene, kollisionsfreie ID. Wer natives Oeffnen unter iOS 35.0.0 moechte,
		// setzt: occ config:app:set dossier provider_id --value=fulltextsearch
		// NIE 'files' waehlen (Kollision mit dem Core-Provider!). Details: README.
		return $this->appConfig->getValueString(IndexerService::APP_ID, 'provider_id', 'simplefulltextsearch');
	}

	public function getName(): string {
		return $this->l10n->t('Document search');
	}

	public function getOrder(string $route, array $routeParameters): ?int {
		return 5;
	}

	public function search(IUser $user, ISearchQuery $query): SearchResult {
		$term = $query->getTerm();
		$owner = $this->appConfig->getValueString(IndexerService::APP_ID, 'scope_owner', '');
		$rows = $owner === '' ? [] : $this->mapper->search($owner, $term, $query->getLimit());
		$userFolder = $this->rootFolder->getUserFolder($user->getUID());
		$entries = [];
		foreach ($rows as $row) {
			$node = $userFolder->getFirstNodeById($row['file_id']);
			if (!$node instanceof Node) {
				continue; // kein Zugriff fuer diese(n) Nutzer
			}
			$entries[] = $this->buildEntry($node, $row, $term);
		}
		return SearchResult::complete($this->l10n->t('Document search'), $entries);
	}

	/** @param array{file_id:int,name:string,path:string,text:string} $row */
	private function buildEntry(Node $node, array $row, string $term): SearchResultEntry {
		$mime = $node->getMimetype();
		$thumbnailUrl = $this->previewManager->isMimeSupported($mime)
			? $this->urlGenerator->linkToRouteAbsolute('core.Preview.getPreviewByFileId', ['x' => 32, 'y' => 32, 'fileId' => $row['file_id']])
			: '';
		// App-35.0.0-Vertrag (case "fulltextsearch"): resourceUrl = Files-Deep-Link
		// ?dir=<Ordner MIT trailing slash>&scrollto=<Dateiname>. Die App konkateniert
		// dir+filename zum DAV-Pfad und oeffnet nativ. Jeder Eintrag MUSS beide
		// Parameter tragen (guard reisst sonst das ganze Ergebnis ab).
		$dir = dirname($row['path']);
		$dir = $dir === '/' ? '/' : $dir . '/';
		$link = $this->urlGenerator->getAbsoluteURL($this->filesBaseUrl())
			. '?dir=' . rawurlencode($dir) . '&scrollto=' . rawurlencode($node->getName());
		$searchResultEntry = new SearchResultEntry(
			$thumbnailUrl,
			$node->getName(),
			$this->snippet($row, $term),
			$link,
			$this->mimeTypeDetector->mimeTypeIcon($mime),
		);
		// Apps oeffnen Datei-Treffer NUR nativ, wenn fileId+path als Attribute haengen
		// (Bauplan 1:1 von FilesSearchProvider uebernommen).
		$searchResultEntry->addAttribute('fileId', (string)$row['file_id']);
		$searchResultEntry->addAttribute('path', $row['path']);
		return $searchResultEntry;
	}

	/**
	 * Basis-URL der Files-App — versionssicher fuer NC 33/34/35: die Route heisst
	 * je nach Version 'files.View.index' (34) oder 'files.view.index' (33/35).
	 * Als letzter Rettungsanker der stabile Pfad selbst.
	 */
	private function filesBaseUrl(): string {
		foreach (['files.View.index', 'files.view.index'] as $route) {
			try {
				return $this->urlGenerator->linkToRoute($route);
			} catch (\Throwable) {
			}
		}
		return '/index.php/apps/files/';
	}

	/** @param array{file_id:int,name:string,path:string,text:string} $row */
	private function snippet(array $row, string $term): string {
		$text = $row['text'];
		$pos = -1;
		foreach (preg_split('/\s+/u', $term) ?: [] as $w) {
			if (mb_strlen($w) < 2) {
				continue;
			}
			$p = mb_stripos($text, $w);
			if ($p !== false && ($pos === -1 || $p < $pos)) {
				$pos = $p;
			}
		}
		if ($pos === -1) {
			$pos = 0;
		}
		$start = max(0, $pos - 50);
		$snip = mb_substr($text, $start, 140);
		return ($start > 0 ? '…' : '') . $snip . (mb_strlen($text) > $start + 140 ? '…' : '') . ' · ' . $row['path'];
	}
}
