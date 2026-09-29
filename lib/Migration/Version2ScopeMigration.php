<?php

declare(strict_types=1);

namespace OCA\Dossier\Migration;

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
		if (!$schema->hasTable('dossier_index')) {
			return null;
		}
		$table = $schema->getTable('dossier_index');
		if ($table->hasIndex('dossier_file_idx')) {
			$table->dropIndex('dossier_file_idx');
		}
		if (!$table->hasIndex('dossier_file_uix')) {
			$table->addUniqueIndex(['file_id', 'owner'], 'dossier_file_uix');
		}
		return $schema;
	}
}
