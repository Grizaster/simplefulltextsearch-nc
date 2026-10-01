<?php

declare(strict_types=1);

namespace OCA\SimpleFullTextFileSearch\Search;

use OCA\SimpleFullTextFileSearch\Db\IndexMapper;
use OCA\SimpleFullTextFileSearch\Service\IndexerService;
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
		// setzt: occ config:app:set simplefulltextsearch provider_id --value=fulltextsearch
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
		$scopes = IndexerService::scopesFromConfig($this->appConfig);
		$rows = $scopes === [] ? [] : $this->mapper->search($scopes, $term, $query->getLimit());
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
		// Deep-Link '/f/<fileid>' = kanonischer Datei-Link ("interne Verknuepfung",
		// Route files.View.showFile): der Server leitet auf die Files-Ansicht weiter
		// und oeffnet die Datei DIREKT im Viewer (redirectToFile setzt openfile=true).
		// Der Alt-Parameter scrollto wird von der NC34-Web-App ignoriert (Legacy der
		// Alt-Files-App) — ohne /f/<fileid> landet man nur im Ordner.
		// App-35.0.0-Vertrag (case "fulltextsearch"): die App liest AUSCHLIESSLICH die
		// Query-Parameter dir (MIT trailing slash) + scrollto (Dateiname), konkateniert
		// beide zum DAV-Pfad und oeffnet nativ — Pfad und zusaetzliche Query-Parameter
		// der resourceUrl sind der App egal. Jeder Eintrag MUSS beide Parameter tragen
		// (guard reisst sonst die ganze Sektion ab).
		$dir = dirname($row['path']);
		$dir = $dir === '/' ? '/' : $dir . '/';
		$link = $this->fileDeepLink((int)$row['file_id'])
			. '?dir=' . rawurlencode($dir) . '&scrollto=' . rawurlencode($node->getName()) . '&openfile=true';
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
	 * Kanonischer Datei-Deep-Link '/f/<fileid>' — Route 'View#showFile' (root ''),
	 * identisch in NC 33/34/35. Der Server leitet auf files.view.indexViewFileid
	 * weiter (inkl. dir + openfile=true), die Datei oeffnet direkt im Viewer.
	 * Je nach Version heisst die Route 'files.View.showFile' oder
	 * 'files.view.showFile'; als letzter Rettungsanker der stabile Pfad selbst
	 * ('/f/{fileid}' ist serverseitig hardcodiert).
	 */
	private function fileDeepLink(int $fileId): string {
		foreach (['files.View.showFile', 'files.view.showFile'] as $route) {
			try {
				return $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute($route, ['fileid' => $fileId]));
			} catch (\Throwable) {
			}
		}
		return $this->urlGenerator->getAbsoluteURL('/index.php/f/' . $fileId);
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
