<?php

declare(strict_types=1);

namespace OCA\SimpleFullTextFileSearch\Tests;

use OCA\SimpleFullTextFileSearch\Migration\Version2ScopeMigration;
use OCA\SimpleFullTextFileSearch\Migration\Version3RenameMigration;
use OCP\DB\ISchemaWrapper;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

class MigrationTest extends TestCase {
	/**
	 * Version 2: Multi-Scope Schema-Migration.
	 * Tauscht den Unique-Index von file_id auf (file_id, owner).
	 */
	public function testVersion2ScopeMigrationChangesSchema(): void {
		$output = $this->createStub(IOutput::class);
		$schema = $this->createStub(ISchemaWrapper::class);

		$table = new class {
			public array $droppedIndices = [];
			public array $addedIndices = [];

			public function hasIndex(string $name): bool {
				return $name === 'simplefulltextsearch_file_idx';
			}

			public function dropIndex(string $name): void {
				$this->droppedIndices[] = $name;
			}

			public function addUniqueIndex(array $columns, string $name): void {
				$this->addedIndices[$name] = $columns;
			}
		};

		$schema->method('hasTable')->willReturnCallback(static fn (string $table) => $table === 'simplefulltextsearch_index');
		$schema->method('getTable')->willReturn($table);

		$migration = new Version2ScopeMigration();
		$result = $migration->changeSchema($output, static fn () => $schema, []);

		$this->assertSame($schema, $result);
		$this->assertContains('simplefulltextsearch_file_idx', $table->droppedIndices);
		$this->assertSame(['file_id', 'owner'], $table->addedIndices['simplefulltextsearch_file_uix'] ?? null);

		// Fall: Tabelle existiert noch nicht
		$schemaWithoutTable = $this->createStub(ISchemaWrapper::class);
		$schemaWithoutTable->method('hasTable')->willReturn(false);
		$this->assertNull($migration->changeSchema($output, static fn () => $schemaWithoutTable, []));
	}

	/**
	 * Version 3: Rename-Migration:
	 * Daten aus oc_dossier_index landen in oc_simplefulltextsearch_index.
	 * Die Alt-Tabelle wird NIE geloescht oder geleert (idempotent & nicht-destruktiv).
	 */
	public function testRenameMigrationCopiesData(): void {
		$connection = $this->createMock(IDBConnection::class);
		$appConfig = $this->createStub(IAppConfig::class);
		$output = $this->createStub(IOutput::class);
		$schema = $this->createStub(ISchemaWrapper::class);

		// dossier_index existiert im Schema
		$schema->method('hasTable')->willReturnCallback(static fn (string $table) => $table === 'dossier_index');

		$executedSql = '';
		$connection->expects($this->once())
			->method('executeStatement')
			->willReturnCallback(function (string $sql) use (&$executedSql) {
				$executedSql = $sql;
				return 15; // 15 Datensaetze kopiert
			});

		$migration = new Version3RenameMigration($connection, $appConfig);
		$migration->postSchemaChange($output, static fn () => $schema, []);

		// Pruefen, dass die Datenuebernahme korrekt und idempotent erfolgte
		$this->assertStringContainsString('INSERT INTO `*PREFIX*simplefulltextsearch_index`', $executedSql);
		$this->assertStringContainsString('FROM `*PREFIX*dossier_index`', $executedSql);
		$this->assertStringContainsString('WHERE NOT EXISTS', $executedSql);
		$this->assertStringNotContainsString('DROP TABLE', $executedSql, 'Altdaten duerfen niemals geloescht werden!');
		$this->assertStringNotContainsString('DELETE FROM', $executedSql, 'Altdaten duerfen niemals geloescht werden!');
	}

	/**
	 * Version 3: Config-Migration:
	 * App-Config dossier (scopes, scope_owner, ...) wird nach simplefulltextsearch migriert.
	 */
	public function testConfigMigration(): void {
		$connection = $this->createStub(IDBConnection::class);
		$appConfig = $this->createStub(IAppConfig::class);
		$output = $this->createStub(IOutput::class);
		$schema = $this->createStub(ISchemaWrapper::class);

		$schema->method('hasTable')->willReturn(false);

		$configStore = [
			'dossier' => [
				'scopes' => json_encode([['owner' => 'bob', 'folder' => '/Projects']]),
				'scope_owner' => 'bob',
				'scope_folder' => '/Projects',
			],
			'simplefulltextsearch' => [],
		];

		$appConfig->method('getValueString')
			->willReturnCallback(static function (string $app, string $key, string $default) use (&$configStore) {
				return $configStore[$app][$key] ?? $default;
			});

		$savedValues = [];
		$appConfig->method('setValueString')
			->willReturnCallback(function (string $app, string $key, string $val) use (&$savedValues, &$configStore) {
				$savedValues[$app][$key] = $val;
				$configStore[$app][$key] = $val;
			});

		$migration = new Version3RenameMigration($connection, $appConfig);
		$migration->postSchemaChange($output, static fn () => $schema, []);

		$this->assertArrayHasKey('simplefulltextsearch', $savedValues);
		$this->assertSame(
			json_encode([['owner' => 'bob', 'folder' => '/Projects']]),
			$savedValues['simplefulltextsearch']['scopes'] ?? null
		);
		$this->assertSame('bob', $savedValues['simplefulltextsearch']['scope_owner'] ?? null);
	}
}
