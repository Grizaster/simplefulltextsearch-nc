<?php

declare(strict_types=1);

namespace OCA\SimpleFullTextFileSearch\Tests;

use OCA\SimpleFullTextFileSearch\Service\ExtractorService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ExtractorServiceTest extends TestCase {
	/**
	 * P1: Temp-Cleanup: Vor und nach der Extraktion duerfen keine verwaisten
	 * elds_*-Dateien in sys_get_temp_dir() verbleiben (Inode-Leak-Schutz).
	 */
	public function testTempCleanup(): void {
		$logger = $this->createStub(LoggerInterface::class);
		$extractor = new ExtractorService($logger);

		$tmpDir = sys_get_temp_dir();
		$beforeFiles = glob($tmpDir . '/elds_*') ?: [];

		// Dummy-PDF anlegen
		$testPdf = tempnam($tmpDir, 'test_doc_');
		file_put_contents($testPdf, "%PDF-1.4\n%test dummy content\n");

		try {
			$extractor->extract($testPdf, 'test.pdf');
		} finally {
			@unlink($testPdf);
		}

		$afterFiles = glob($tmpDir . '/elds_*') ?: [];
		$leakedFiles = array_diff($afterFiles, $beforeFiles);

		$this->assertEmpty(
			$leakedFiles,
			'Es wurden temporaere Dateien im Temp-Verzeichnis geleakt: ' . implode(', ', $leakedFiles)
		);
	}

	/**
	 * P2: CLI-Fehler protokollieren statt schweigen:
	 * Wenn ein CLI-Kommando mit Exit-Code != 0 fehlschlaegt, muss logger->warning gerufen werden.
	 */
	public function testCliErrorLogging(): void {
		$warningCalled = false;
		$capturedContext = [];

		$logger = $this->createStub(LoggerInterface::class);
		$logger->method('warning')
			->willReturnCallback(function (string|\Stringable $msg, array $context) use (&$warningCalled, &$capturedContext) {
				$warningCalled = true;
				$capturedContext = $context;
			});

		$extractor = new ExtractorService($logger);

		// Per Reflection die private sh-Methode mit einem fehlschlagenden Kommando testen
		$reflector = new \ReflectionClass(ExtractorService::class);
		$shMethod = $reflector->getMethod('sh');
		$shMethod->setAccessible(true);

		$result = $shMethod->invoke($extractor, ['sh', '-c', 'echo "Fehler im CLI-Tool" >&2; exit 42']);

		$this->assertSame('', $result);
		$this->assertTrue($warningCalled, 'Bei CLI-Exitcode != 0 muss logger->warning aufgerufen werden.');
		$this->assertSame(42, $capturedContext['rc'] ?? null);
		$this->assertStringContainsString('Fehler im CLI-Tool', $capturedContext['err'] ?? '');
	}
}
