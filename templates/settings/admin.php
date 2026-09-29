<?php
/** @var array $_ */
script('dossier', 'admin');
$scopes = $_['scopes'];
if ($scopes === []) {
	$scopes = [['owner' => '', 'folder' => '']];
}
?>
<div id="dossier-settings" class="section">
	<h2><?php p($_['titleText']) ?></h2>
	<p class="settings-hint"><?php p($_['hintText']) ?></p>

	<form id="dossier-form"
	      data-save-url="<?php p($_['saveUrl']) ?>"
	      data-reindex-url="<?php p($_['reindexUrl']) ?>">
		<label>Suchbereiche — jeder Eintrag ist ein Ordner, der vollständig durchsucht wird</label><br>
		<div id="dossier-scopes">
			<?php foreach ($scopes as $scope) { ?>
			<div class="dossier-scope-row" style="margin-bottom: 6px;">
				<input class="dossier-scope-owner" type="text" style="width: 14em"
				       value="<?php p($scope['owner']) ?>" placeholder="Nutzer, z. B. bensn">
				<input class="dossier-scope-folder" type="text" style="width: 24em"
				       value="<?php p($scope['folder']) ?>" placeholder="Ordner, z. B. /Documents">
				<button type="button" class="dossier-scope-remove">−</button>
			</div>
			<?php } ?>
		</div>
		<button type="button" id="dossier-scope-add">+ Suchbereich hinzufügen</button><br><br>

		<button id="dossier-save" class="primary">Speichern</button>
		<button id="dossier-reindex">Jetzt neu indizieren</button>
		<span id="dossier-status" style="margin-left: 1em;"></span>
	</form>
</div>
