<?php
/** @var array $_ */
script('dossier', 'admin');
?>
<div id="dossier-settings" class="section">
	<h2><?php p($_['titleText']) ?></h2>
	<p class="settings-hint"><?php p($_['hintText']) ?></p>

	<form id="dossier-form"
	      data-save-url="<?php p($_['saveUrl']) ?>"
	      data-reindex-url="<?php p($_['reindexUrl']) ?>">
		<label for="dossier-owner">Nextcloud-Nutzer (Eigentümer des Ordners)</label><br>
		<input id="dossier-owner" type="text" style="width: 22em"
		       value="<?php p($_['scope_owner']) ?>" placeholder="z. B. bensn"><br><br>

		<label for="dossier-folder">Ordner (Pfad im Dateibereich dieses Nutzers)</label><br>
		<input id="dossier-folder" type="text" style="width: 22em"
		       value="<?php p($_['scope_folder']) ?>" placeholder="z. B. /Documents"><br><br>

		<button id="dossier-save" class="primary">Speichern</button>
		<button id="dossier-reindex">Jetzt neu indizieren</button>
		<span id="dossier-status" style="margin-left: 1em;"></span>
	</form>
</div>
