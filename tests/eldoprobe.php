<?php
// Fein-Diagnose: Marker je Schritt + Shutdown-Handler (fängt auch stumme Tode).
register_shutdown_function(static function (): void {
	$e = error_get_last();
	fwrite(STDOUT, "SHUTDOWN last_error=" . json_encode($e) . PHP_EOL);
	fflush(STDOUT);
});
$m = static function (string $s): void {
	fwrite(STDOUT, $s . PHP_EOL);
	fflush(STDOUT);
};

$m('M1 vor boot');
require '/var/www/nextcloud/lib/base.php';
$m('M2 boot_ok');

$x = \OCP\Server::get(\OCA\SimpleFullTextFileSearch\Service\IndexerService::class);
$m('M3 got_indexer');

$uf = \OCP\Server::get(\OCP\Files\IRootFolder::class)->getUserFolder('bensn');
$m('M4 userfolder');

$node = $uf->get('/Documents');
$m('M5 node=' . get_class($node));

$kids = $node->getDirectoryListing();
$m('M6 count=' . count($kids));

$first = $kids[0] ?? null;
if ($first !== null) {
	$m('M7 first=' . $first->getName());
	$local = $first->getStorage()->getLocalFile($first->getInternalPath());
	$m('M8 local=' . var_export($local, true));
}

exec('echo probe_ok', $o, $rc);
$m('M9 exec rc=' . $rc . ' out=' . implode(',', $o));

try {
	$s = $x->indexUser();
	$m('M10 stats=' . json_encode($s));
} catch (\Throwable $e) {
	$m('M10 EX ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
}
$m('ENDE');
