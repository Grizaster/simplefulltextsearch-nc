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
				<select class="dossier-scope-owner" style="width: 14em">
					<option value=""<?php if ($scope['owner'] === '') { ?> selected<?php } ?>>— Nutzer wählen —</option>
					<?php foreach ($_['users'] as $uid) { ?>
					<option value="<?php p($uid) ?>"<?php if ($scope['owner'] === $uid) { ?> selected<?php } ?>><?php p($uid) ?></option>
					<?php } ?>
					<?php if ($scope['owner'] !== '' && !in_array($scope['owner'], $_['users'], true)) { ?>
					<option value="<?php p($scope['owner']) ?>" selected><?php p($scope['owner']) ?> (nicht mehr vorhanden)</option>
					<?php } ?>
				</select>
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
