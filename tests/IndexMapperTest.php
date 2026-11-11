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

		$expr = new class {
			public function in(string $col, $param): string { return "$col IN (...)"; }
			public function gt(string $col, $param): string { return "$col > $param"; }
		};
		$selectResult = new class($existingRows) {
			public function __construct(private array $rows) {}
			public function fetchAll(): array { return $this->rows; }
		};
		$deletedIds = [];
		// Universal-Fake: jeder getQueryBuilder()-Aufruf liefert eine frische
		// Stub-Instanz mit allen benoetigten Methoden (Select-Chunk + Delete-Bloecke).
		$makeQb = function () use ($expr, $selectResult, &$deletedIds) {
			$qb = $this->createStub(IQueryBuilder::class);
			foreach (['select', 'from', 'where', 'andWhere', 'orderBy', 'setMaxResults', 'delete'] as $m) {
				$qb->method($m)->willReturnSelf();
			}
			$qb->method('expr')->willReturn($expr);
			$qb->method('executeQuery')->willReturn($selectResult);
			$qb->method('executeStatement')->willReturn(2);
			$qb->method('createNamedParameter')->willReturnCallback(function ($val) use (&$deletedIds) {
				if (is_array($val)) {
					$deletedIds = array_merge($deletedIds, $val);
				}
				return ':param';
			});
			return $qb;
		};
		$db->method('getQueryBuilder')->willReturnCallback($makeQb);

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
