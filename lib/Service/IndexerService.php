<?php

declare(strict_types=1);

namespace OCA\Dossier\Service;

use OCA\Dossier\Db\IndexEntry;
use OCA\Dossier\Db\IndexMapper;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

class IndexerService {
	public const APP_ID = 'dossier';

	public function __construct(
		private IndexMapper $mapper,
		private ExtractorService $extractor,
		private IRootFolder $rootFolder,
		private IAppConfig $appConfig,
		private LoggerInterface $logger,
	) {
	}

	/** @return array{owner:string,folder:string,indexed:int,skipped:int,removed:int,unchanged:int,error?:string} */
	public function indexUser(?string $owner = null, ?string $folder = null): array {
		$owner = $owner ?? $this->appConfig->getValueString(self::APP_ID, 'scope_owner', '');
		$folder = $folder ?? $this->appConfig->getValueString(self::APP_ID, 'scope_folder', '/Documents');
		$stats = ['owner' => $owner, 'folder' => $folder, 'indexed' => 0, 'skipped' => 0, 'removed' => 0, 'unchanged' => 0];
		if ($owner === '') {
			$stats['error'] = 'kein scope_owner gesetzt';
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
		$this->walk($node, $userFolder, $owner, $seen, $stats);
		$stats['removed'] = $this->mapper->deleteMissing($owner, array_keys($seen));
		return $stats;
	}

	/** @param list<int> $seen */
	private function walk(Folder $folder, Folder $userFolder, string $owner, array &$seen, array &$stats): void {
		foreach ($folder->getDirectoryListing() as $node) {
			if ($node instanceof Folder) {
				$this->walk($node, $userFolder, $owner, $seen, $stats);
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
			if ($size > 26214400) {
				$stats['skipped']++;
				continue;
			}
			$existing = $this->mapper->findOneByFileId($fileId);
			if ($existing !== null && (int)$existing->getMtime() === $mtime && (int)$existing->getSize() === $size) {
				$seen[$fileId] = true;
				$stats['unchanged']++;
				continue;
			}
			try {
				$local = $node->getStorage()->getLocalFile($node->getInternalPath());
				$text = $local !== false ? $this->extractor->extract($local, $node->getName()) : '';
			} catch (\Throwable $e) {
				$this->logger->warning('dossier: Extraktion fehlgeschlagen für {f}: {e}', ['f' => $node->getName(), 'e' => $e->getMessage()]);
				$text = '';
			}
			$entry = $existing ?? new IndexEntry();
			$entry->setFileId($fileId);
			$entry->setOwner($owner);
			$entry->setPath($userFolder->getRelativePath($node->getPath()) ?? $node->getName());
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
				$again = $this->mapper->findOneByFileId($fileId);
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
