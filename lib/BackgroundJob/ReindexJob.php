<?php

declare(strict_types=1);

namespace OCA\SimpleFullTextFileSearch\BackgroundJob;

use OCA\SimpleFullTextFileSearch\Service\IndexerService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\Job;
use Psr\Log\LoggerInterface;

/**
 * Einmaliger Job fuer den Admin-Knopf "Jetzt neu indizieren": laeuft beim
 * naechsten Cron-Zyklus und entfernt sich danach selbst (Nextcloud loescht
 * ausgefuehrte Jobs ohne Intervall). Haelt den HTTP-Request schlank — ein
 * voller Reindex groesserer Bestaende wuerde sonst am Gateway-Timeout sterben.
 */
class ReindexJob extends Job {
	public function __construct(
		ITimeFactory $time,
		private IndexerService $indexer,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
	}

	protected function run($argument): void {
		try {
			$stats = $this->indexer->indexAll();
			foreach ($stats['errors'] as $error) {
				$this->logger->info('simplefulltextsearch: Reindex-Lauf: ' . $error);
			}
		} catch (\Throwable $e) {
			$this->logger->error('simplefulltextsearch: Reindex-Job fehlgeschlagen: ' . $e->getMessage());
		}
	}
}
