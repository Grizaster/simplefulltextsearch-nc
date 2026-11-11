<?php
/** @var array $_ */
script('simplefulltextsearch', 'admin');
$scopes = $_['scopes'];
if ($scopes === []) {
	$scopes = [['owner' => '', 'folder' => '']];
}
?>
<div id="simplefulltextsearch-settings" class="section">
	<h2><?php p($_['titleText']) ?></h2>
	<p class="settings-hint"><?php p($_['hintText']) ?></p>

	<form id="simplefulltextsearch-form"
	      data-save-url="<?php p($_['saveUrl']) ?>"
	      data-reindex-url="<?php p($_['reindexUrl']) ?>"
	      data-strings="<?php p(json_encode($_['jsStrings'])) ?>">
		<label><?php p($_['scopesLabel']) ?></label><br>
		<div id="simplefulltextsearch-scopes">
			<?php foreach ($scopes as $scope) { ?>
			<div class="simplefulltextsearch-scope-row" style="margin-bottom: 6px;">
				<select class="simplefulltextsearch-scope-owner" style="width: 14em">
					<option value=""<?php if ($scope['owner'] === '') { ?> selected<?php } ?>><?php p($_['ownerEmptyLabel']) ?></option>
					<?php foreach ($_['users'] as $uid) { ?>
					<option value="<?php p($uid) ?>"<?php if ($scope['owner'] === $uid) { ?> selected<?php } ?>><?php p($uid) ?></option>
					<?php } ?>
					<?php if ($scope['owner'] !== '' && !in_array($scope['owner'], $_['users'], true)) { ?>
					<option value="<?php p($scope['owner']) ?>" selected><?php p($scope['owner']) ?> <?php p($_['notExistingLabel']) ?></option>
					<?php } ?>
				</select>
				<input class="simplefulltextsearch-scope-folder" type="text" style="width: 24em"
				       value="<?php p($scope['folder']) ?>" placeholder="<?php p($_['folderPlaceholder']) ?>">
				<button type="button" class="simplefulltextsearch-scope-remove">−</button>
			</div>
			<?php } ?>
		</div>
		<button type="button" id="simplefulltextsearch-scope-add"><?php p($_['addLabel']) ?></button><br><br>

		<button id="simplefulltextsearch-save" class="primary"><?php p($_['saveLabel']) ?></button>
		<button id="simplefulltextsearch-reindex"><?php p($_['reindexLabel']) ?></button>
		<span id="simplefulltextsearch-status" style="margin-left: 1em;"></span>
	</form>
</div>
