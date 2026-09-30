<?php

declare(strict_types=1);

namespace OCA\SimpleFullTextFileSearch\BackgroundJob;

use OCA\SimpleFullTextFileSearch\Service\IndexerService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class IndexJob extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private IndexerService $indexer,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(30 * 60); // alle 30 Minuten inkrementell
	}

	protected function run($argument): void {
		try {
			$stats = $this->indexer->indexAll();
			foreach ($stats['errors'] as $error) {
				$this->logger->info('simplefulltextsearch: Index-Lauf: ' . $error);
			}
		} catch (\Throwable $e) {
			$this->logger->error('simplefulltextsearch: Index-Job fehlgeschlagen: ' . $e->getMessage());
		}
	}
}
