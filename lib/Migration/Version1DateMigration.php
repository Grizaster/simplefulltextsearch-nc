<?php

declare(strict_types=1);

namespace OCA\SimpleFullTextFileSearch\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\IDBConnection;
use OCP\Migration\SimpleMigrationStep;
use Doctrine\DBAL\Types\Types;

class Version1DateMigration extends SimpleMigrationStep {
	/**
	 * Achtung: Nextcloud instanziert Migrationsschritte mit `new $class()` —
	 * echtes Constructor-DI ist NICHT verfuegbar. Deshalb Nullable-DI mit
	 * Fallback auf die oeffentliche \OCP\Server-API (identisches Muster wie
	 * Version3RenameMigration; \OC::$server ist interne API und im Store verboten).
	 */
	public function __construct(
		private ?IDBConnection $connection = null,
	) {
	}

	private function getConnection(): ?IDBConnection {
		if ($this->connection !== null) {
			return $this->connection;
		}
		if (class_exists('\\OCP\\Server')) {
			try {
				return \OCP\Server::get(IDBConnection::class);
			} catch (\Throwable) {
			}
		}
		return null;
	}
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('simplefulltextsearch_index')) {
			return null;
		}

		$table = $schema->createTable('simplefulltextsearch_index');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('file_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('owner', Types::STRING, ['notnull' => true, 'length' => 64]);
		$table->addColumn('path', Types::STRING, ['notnull' => true, 'length' => 4000]);
		$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 1024]);
		$table->addColumn('mtime', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->addColumn('size', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->addColumn('text', Types::TEXT, ['notnull' => true]);
		$table->addColumn('indexed_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['file_id'], 'simplefulltextsearch_file_idx');
		$table->addIndex(['owner'], 'simplefulltextsearch_owner_idx');

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// MariaDB-InnoDB-FULLTEXT auf (name, text) — nicht ueber DBAL-Schema abbildbar.
		$connection = $this->getConnection();
		if ($connection === null) {
			$output->info('FULLTEXT-Index konnte nicht angelegt werden (keine DB-Verbindung, Fallback: LIKE-Suche)');
			return;
		}
		try {
			$connection->executeQuery('ALTER TABLE `*PREFIX*simplefulltextsearch_index` ADD FULLTEXT KEY `simplefulltextsearch_ft` (`name`, `text`)');
		} catch (\Throwable $e) {
			$output->info('FULLTEXT-Index konnte nicht angelegt werden (Fallback: LIKE-Suche): ' . $e->getMessage());
		}
	}
}
