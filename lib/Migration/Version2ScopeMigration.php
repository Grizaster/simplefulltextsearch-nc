<?php

declare(strict_types=1);

namespace OCA\SimpleFullTextFileSearch\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Multi-Scope: Identitaet einer Index-Zeile ist (file_id, owner) statt file_id.
 * Eine an mehrere Eigentuermir geteilte Datei hat je Eigentuermir eine Zeile
 * (Pfad persoenlich verschieden) — vorher kippte die Zeile pro Lauf hin und her.
 */
class Version2ScopeMigration extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->hasTable('simplefulltextsearch_index')) {
			return null;
		}
		$table = $schema->getTable('simplefulltextsearch_index');
		if ($table->hasIndex('simplefulltextsearch_file_idx')) {
			$table->dropIndex('simplefulltextsearch_file_idx');
		}
		if (!$table->hasIndex('simplefulltextsearch_file_uix')) {
			$table->addUniqueIndex(['file_id', 'owner'], 'simplefulltextsearch_file_uix');
		}
		return $schema;
	}
}
