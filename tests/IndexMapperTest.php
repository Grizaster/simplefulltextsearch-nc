<?php

declare(strict_types=1);

namespace OCA\SimpleFullTextFileSearch\Tests;

use OCA\SimpleFullTextFileSearch\Db\IndexMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

class IndexMapperTest extends TestCase {
	/**
	 * P2: Verwaiste Index-Eintraege:
	 * deleteOrphanedScopes loescht Zeilen von Scopes, die nicht mehr konfiguriert sind.
	 */
	public function testDeleteOrphanedScopes(): void {
		$db = $this->createStub(IDBConnection::class);
		$mapper = new IndexMapper($db);

		// Vorhandene Index-Zeilen:
		// 1: alice in /Docs (aktiv)
		// 2: bob in /Projects (geloescht aus Config)
		// 3: alice in /Photos (geloescht aus Config)
		$existingRows = [
			['id' => 1, 'owner' => 'alice', 'path' => '/Docs/file1.pdf'],
			['id' => 2, 'owner' => 'bob', 'path' => '/Projects/plan.pdf'],
			['id' => 3, 'owner' => 'alice', 'path' => '/Photos/pic.jpg'],
		];

		$selectQb = $this->createStub(IQueryBuilder::class);
		$selectQb->method('select')->willReturnSelf();
		$selectQb->method('from')->willReturnSelf();

		$selectResult = new class($existingRows) {
			public function __construct(private array $rows) {}
			public function fetchAll(): array { return $this->rows; }
		};
		$selectQb->method('executeQuery')->willReturn($selectResult);

		$deletedIds = [];
		$deleteQb = $this->createStub(IQueryBuilder::class);
		$deleteQb->method('delete')->willReturnSelf();
		$deleteQb->method('where')->willReturnSelf();
		$deleteQb->method('expr')->willReturn(new class {
			public function in(string $col, $param): string { return "$col IN (...)"; }
		});
		$deleteQb->method('createNamedParameter')->willReturnCallback(function ($val) use (&$deletedIds) {
			if (is_array($val)) {
				$deletedIds = array_merge($deletedIds, $val);
			}
			return ':param';
		});
		$deleteQb->method('executeStatement')->willReturn(2);

		$db->method('getQueryBuilder')->willReturnOnConsecutiveCalls($selectQb, $deleteQb);

		// Aktive Konfiguration enthaelt nur noch alice -> /Docs
		$activeScopes = [
			['owner' => 'alice', 'folder' => '/Docs'],
		];

		$deletedCount = $mapper->deleteOrphanedScopes($activeScopes);

		$this->assertSame(2, $deletedCount);
		// Nur ID 2 (bob) und ID 3 (alice /Photos) duerfen geloescht werden, ID 1 bleibt erhalten
		$this->assertEqualsCanonicalizing([2, 3], $deletedIds);
	}
}
