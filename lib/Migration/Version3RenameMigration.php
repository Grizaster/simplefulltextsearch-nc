<?php

declare(strict_types=1);

namespace OCA\SimpleFullTextFileSearch\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Migration Version 3:
 * Rename-Migration: Uebernahme von Alt-Daten aus oc_dossier_index -> simplefulltextsearch_index
 * (Altdaten werden NIE geloescht!) sowie Migration der App-Konfigurationen
 * (scopes, scope_owner, scope_folder, provider_id) von 'dossier' nach 'simplefulltextsearch'.
 * Vollstaendig idempotent ausfuehrbar.
 */
class Version3RenameMigration extends SimpleMigrationStep {
	public function __construct(
		private ?IDBConnection $connection = null,
		private ?IAppConfig $appConfig = null,
	) {
	}

	private function getConnection(): ?IDBConnection {
		if ($this->connection !== null) {
			return $this->connection;
		}
		if (class_exists('\OCP\Server')) {
			try {
				return \OCP\Server::get(IDBConnection::class);
			} catch (\Throwable) {
			}
		}
		return null;
	}

	private function getAppConfig(): ?IAppConfig {
		if ($this->appConfig !== null) {
			return $this->appConfig;
		}
		if (class_exists('\OCP\Server')) {
			try {
				return \OCP\Server::get(IAppConfig::class);
			} catch (\Throwable) {
			}
		}
		return null;
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		return null;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$conn = $this->getConnection();
		$config = $this->getAppConfig();

		// 1. Daten-Migration: Alt-Tabelle oc_dossier_index uebernehmen falls vorhanden
		$hasDossierTable = $schema->hasTable('dossier_index');
		if (!$hasDossierTable && $conn !== null) {
			try {
				// Fallback-Pruefung direkt ueber Connection (falls Schema-Wrapper Praefixe abweichend filtert)
				$hasDossierTable = (bool)$conn->executeQuery("SHOW TABLES LIKE '*PREFIX*dossier_index'")->fetchOne();
			} catch (\Throwable) {
			}
		}

		if ($conn !== null && $hasDossierTable) {
			try {
				// Idempotenter Import: Nur Zeilen kopieren, die in simplefulltextsearch_index noch nicht existieren
				$query = 'INSERT INTO `*PREFIX*simplefulltextsearch_index` '
					. '(`file_id`, `owner`, `path`, `name`, `mtime`, `size`, `text`, `indexed_at`) '
					. 'SELECT d.`file_id`, d.`owner`, d.`path`, d.`name`, d.`mtime`, d.`size`, d.`text`, d.`indexed_at` '
					. 'FROM `*PREFIX*dossier_index` d '
					. 'WHERE NOT EXISTS ('
					. '  SELECT 1 FROM `*PREFIX*simplefulltextsearch_index` s '
					. '  WHERE s.`file_id` = d.`file_id` AND s.`owner` = d.`owner`'
					. ')';
				$copied = $conn->executeStatement($query);
				$output->info("Rename-Migration: $copied Datensaetze aus dossier_index nach simplefulltextsearch_index uebernommen (Originaltabelle unveraendert beibehalten).");
			} catch (\Throwable $e) {
				$output->warning('Rename-Migration aus dossier_index fehlgeschlagen: ' . $e->getMessage());
			}
		}

		// 2. Config-Migration: App-Config dossier -> simplefulltextsearch (idempotent, ueberschreibt keine gesetzten Werte)
		if ($config !== null) {
			$keys = ['scopes', 'scope_owner', 'scope_folder', 'provider_id'];
			foreach ($keys as $key) {
				$current = $config->getValueString('simplefulltextsearch', $key, '');
				if ($current === '') {
					$old = $config->getValueString('dossier', $key, '');
					if ($old !== '') {
						$config->setValueString('simplefulltextsearch', $key, $old);
						$output->info("AppConfig migriert: dossier.$key -> simplefulltextsearch.$key");
					}
				}
			}
		}
	}
}
