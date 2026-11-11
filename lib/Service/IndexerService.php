<?php

declare(strict_types=1);

namespace OCA\SimpleFullTextFileSearch\Service;

use OCA\SimpleFullTextFileSearch\Db\IndexEntry;
use OCA\SimpleFullTextFileSearch\Db\IndexMapper;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

class IndexerService {
	public const APP_ID = 'simplefulltextsearch';

	public function __construct(
		private IndexMapper $mapper,
		private ExtractorService $extractor,
		private IRootFolder $rootFolder,
		private IAppConfig $appConfig,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Konfigurierte Suchbereiche [{owner,folder},...] aus 'scopes' (JSON);
	 * Alt-Config scope_owner/scope_folder bleibt als weicher Fallback.
	 * @return list<array{owner:string,folder:string}>
	 */
	public static function scopesFromConfig(IAppConfig $appConfig): array {
		$scopes = [];
		$raw = $appConfig->getValueString(self::APP_ID, 'scopes', '');
		if ($raw !== '') {
			$decoded = json_decode($raw, true);
			foreach ((is_array($decoded) ? $decoded : []) as $s) {
				if (is_array($s) && trim((string)($s['owner'] ?? '')) !== '' && trim((string)($s['folder'] ?? '')) !== '') {
					$scopes[] = ['owner' => trim((string)$s['owner']), 'folder' => self::normalizeFolder((string)$s['folder'])];
				}
			}
		}
		if ($scopes === []) {
			$owner = $appConfig->getValueString(self::APP_ID, 'scope_owner', '');
			if ($owner !== '') {
				$scopes[] = ['owner' => $owner, 'folder' => self::normalizeFolder($appConfig->getValueString(self::APP_ID, 'scope_folder', '/Documents'))];
			}
		}
		// Fallback auf Alt-Config dossier
		if ($scopes === []) {
			$rawLegacy = $appConfig->getValueString('dossier', 'scopes', '');
			if ($rawLegacy !== '') {
				$decoded = json_decode($rawLegacy, true);
				foreach ((is_array($decoded) ? $decoded : []) as $s) {
					if (is_array($s) && trim((string)($s['owner'] ?? '')) !== '' && trim((string)($s['folder'] ?? '')) !== '') {
						$scopes[] = ['owner' => trim((string)$s['owner']), 'folder' => self::normalizeFolder((string)$s['folder'])];
					}
				}
			}
			if ($scopes === []) {
				$owner = $appConfig->getValueString('dossier', 'scope_owner', '');
				if ($owner !== '') {
					$scopes[] = ['owner' => $owner, 'folder' => self::normalizeFolder($appConfig->getValueString('dossier', 'scope_folder', '/Documents'))];
				}
			}
		}
		return $scopes;
	}

	public static function normalizeFolder(string $folder): string {
		$folder = str_replace('\\', '/', $folder);
		$parts = [];
		foreach (explode('/', $folder) as $seg) {
			$seg = trim($seg);
			if ($seg === '' || $seg === '.') {
				continue;
			}
			if ($seg === '..') {
				// Traversierung ist in Suchbereichen nie erlaubt: auf Root abbilden,
				// save() verwirft '/' dann als ungueltigen Bereich.
				return '/';
			}
			$parts[] = $seg;
		}
		return '/' . implode('/', $parts);
	}

	/** Alle konfigurierten Suchbereiche indizieren. */
	public function indexAll(): array {
		$scopes = self::scopesFromConfig($this->appConfig);
		$totals = ['indexed' => 0, 'skipped' => 0, 'removed' => 0, 'unchanged' => 0, 'scopes' => 0, 'errors' => []];
		foreach ($scopes as $scope) {
			$stats = $this->indexUser($scope['owner'], $scope['folder']);
			$totals['scopes']++;
			foreach (['indexed', 'skipped', 'removed', 'unchanged'] as $k) {
				$totals[$k] += (int)($stats[$k] ?? 0);
			}
			if (isset($stats['error'])) {
				$totals['errors'][] = $stats['owner'] . ' ' . $stats['folder'] . ': ' . $stats['error'];
			}
		}
		$orphansRemoved = $this->mapper->deleteOrphanedScopes($scopes);
		$totals['removed'] += $orphansRemoved;
		return $totals;
	}

	/** @return array{owner:string,folder:string,indexed:int,skipped:int,removed:int,unchanged:int,error?:string} */
	public function indexUser(?string $owner = null, ?string $folder = null): array {
		if ($owner === null || $folder === null) {
			$first = self::scopesFromConfig($this->appConfig)[0] ?? ['owner' => '', 'folder' => '/'];
			$owner = $owner ?? $first['owner'];
			$folder = $folder ?? $first['folder'];
		}
		$folder = self::normalizeFolder($folder);
		$stats = ['owner' => $owner, 'folder' => $folder, 'indexed' => 0, 'skipped' => 0, 'removed' => 0, 'unchanged' => 0];
		if ($owner === '') {
			$stats['error'] = 'kein Suchbereich gesetzt';
			return $stats;
		}
		try {
			$userFolder = $this->rootFolder->getUserFolder($owner);
			$node = $userFolder->get($folder);
		} catch (NotFoundException) {
			$stats['error'] = "Ordner '$folder' nicht gefunden";
			return $stats;
		}
		if (!$node instanceof Folder) {
			$stats['error'] = "'$folder' ist kein Ordner";
			return $stats;
		}

		$seen = [];
		$seenFolders = [];
		$this->walk($node, $userFolder, $owner, $seen, $stats, $seenFolders, 0);
		$stats['removed'] = $this->mapper->deleteMissing($owner, $folder, array_keys($seen));
		return $stats;
	}

	private const MAX_DEPTH = 30; // Schutz gegen zyklische/gemountete Strukturen

	/** @param list<int> $seen */
	private function walk(Folder $folder, Folder $userFolder, string $owner, array &$seen, array &$stats, array &$seenFolders, int $depth = 0): void {
		if ($depth > self::MAX_DEPTH) {
			$this->logger->warning('simplefulltextsearch: Maximale Ordner-Tiefe in {path} erreicht — Rest uebersprungen', ['path' => $folder->getPath()]);
			return;
		}
		$folderId = (int)$folder->getId();
		if (isset($seenFolders[$folderId])) {
			return; // Zyklus (z. B. gemountete/symlinkte Struktur)
		}
		$seenFolders[$folderId] = true;
		foreach ($folder->getDirectoryListing() as $node) {
			if ($node instanceof Folder) {
				$this->walk($node, $userFolder, $owner, $seen, $stats, $seenFolders, $depth + 1);
				continue;
			}
			if (!$node instanceof File) {
				continue;
			}
			$fileId = (int)$node->getId();
			if (isset($seen[$fileId])) {
				continue; // gleiche fileid zweimal im Baum (Mount/Pfad-Doppelung)
			}
			$mtime = $node->getMTime();
			$size = $node->getSize();
			if ($size > ExtractorService::MAX_BYTES) {
				$stats['skipped']++;
				continue;
			}
			$existing = $this->mapper->findOne($owner, $fileId);
			if ($existing !== null && (int)$existing->getMtime() === $mtime && (int)$existing->getSize() === $size) {
				$seen[$fileId] = true;
				$stats['unchanged']++;
				continue;
			}
			try {
				$local = $node->getStorage()->getLocalFile($node->getInternalPath());
				if ($local === false) {
					$this->logger->warning('simplefulltextsearch: Kein lokaler Dateipfad fuer {path} verfuegbar (S3/Object Storage ohne lokalen Cache)', [
						'path' => $node->getPath(),
						'file' => $node->getName(),
					]);
					$text = '';
				} else {
					$text = $this->extractor->extract($local, $node->getName());
					if ($text === '') {
						$this->logger->warning('simplefulltextsearch: Extraktion lieferte leeren Text fuer {path}', [
							'path' => $node->getPath(),
							'file' => $node->getName(),
						]);
					}
				}
			} catch (\Throwable $e) {
				$this->logger->warning('simplefulltextsearch: Extraktion fehlgeschlagen für {f}: {e}', ['f' => $node->getName(), 'e' => $e->getMessage()]);
				$text = '';
			}
			$entry = $existing ?? new IndexEntry();
			$entry->setFileId($fileId);
			$entry->setOwner($owner);
			$relPath = $userFolder->getRelativePath($node->getPath());
			$entry->setPath($relPath !== null && $relPath !== '' ? '/' . ltrim($relPath, '/') : '/' . $node->getName());
			$entry->setName($node->getName());
			$entry->setMtime($mtime);
			$entry->setSize($size);
			$entry->setText($text);
			$entry->setIndexedAt(time());
			try {
				if ($existing === null) {
					$this->mapper->insert($entry);
				} else {
					$this->mapper->update($entry);
				}
			} catch (\Throwable $e) {
				$again = $this->mapper->findOne($owner, $fileId);
				if ($again === null) {
					throw $e;
				}
				$entry->setId((int)$again->getId());
				$this->mapper->update($entry);
			}
			$seen[$fileId] = true;
			$stats['indexed']++;
		}
	}
}