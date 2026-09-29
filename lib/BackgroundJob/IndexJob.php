<?php

declare(strict_types=1);

namespace OCA\Dossier\BackgroundJob;

use OCA\Dossier\Service\IndexerService;
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
			$stats = $this->indexer->indexUser();
			if (isset($stats['error'])) {
				$this->logger->info('dossier: Index-Lauf uebersprungen: ' . $stats['error']);
			}
		} catch (\Throwable $e) {
			$this->logger->error('dossier: Index-Job fehlgeschlagen: ' . $e->getMessage());
		}
	}
}
