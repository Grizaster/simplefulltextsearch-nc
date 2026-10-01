<?php
// End-to-End-Test SimpleFullTextFileSearch: Indexierung + Provider-Suche (läuft via php8.3 CLI)
require_once '/var/www/nextcloud/lib/base.php';

use OCA\SimpleFullTextFileSearch\Search\Provider;
use OCA\SimpleFullTextFileSearch\Service\IndexerService;
use OCP\Search\IFilter;
use OCP\Search\IFilterCollection;
use OCP\Search\ISearchQuery;

echo "== INDEXIERUNG ==\n";
try {
	$stats = \OCP\Server::get(IndexerService::class)->indexUser();
	echo json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
} catch (\Throwable $e) {
	echo "EX: " . get_class($e) . ": " . $e->getMessage() . " @ " . $e->getFile() . ":" . $e->getLine() . "\n\n";
}

$term = $argv[1] ?? 'Rechnung';
echo "== SUCHE: '$term' ==\n";

$query = new class($term) implements ISearchQuery {
	public function __construct(private string $term) {
	}
	public function getTerm(): string {
		return $this->term;
	}
	public function getFilter(string $name): ?IFilter {
		return null;
	}
	public function getFilters(): IFilterCollection {
		return new class implements IFilterCollection {
			public function getIterator(): Traversable {
				return new ArrayIterator([]);
			}
			public function count(): int {
				return 0;
			}
			public function offsetExists(mixed $offset): bool {
				return false;
			}
			public function offsetGet(mixed $offset): mixed {
				return null;
			}
			public function offsetSet(mixed $offset, mixed $value): void {
			}
			public function offsetUnset(mixed $offset): void {
			}
		};
	}
	public function getSortOrder(): int {
		return 1;
	}
	public function getLimit(): int {
		return 15;
	}
	public function getCursor() {
		return 0;
	}
	public function getRoute(): string {
		return 'files';
	}
	public function getRouteParameters(): array {
		return [];
	}
};

try {
	$user = \OCP\Server::get(\OCP\IUserManager::class)->get('bensn');
	$result = \OCP\Server::get(Provider::class)->search($user, $query);
	$ser = $result->jsonSerialize();
	$entries = $ser['entries'] ?? [];
	if (!isset($ser['entries'])) {
		echo 'RAW: ' . mb_substr((string)json_encode($ser), 0, 1500) . "\n";
	}
	echo 'TREFFER: ' . count($entries) . "\n";
	$fail = 0;
	foreach ($entries as $e) {
		$d = is_array($e) ? $e : $e->jsonSerialize();
		echo '- ' . $d['title'] . "\n   " . mb_substr((string)$d['subline'], 0, 140) . "\n";
		// Vertrag-Check: resourceUrl = /f/<fileid>-Deep-Link (Browser oeffnet die
		// Datei direkt) + Query dir+scrollto MUSS drin sein (iOS-35-Vertrag, guard!),
		// und dir . scrollto === attributes.path.
		$u = parse_url((string)$d['resourceUrl']);
		parse_str((string)($u['query'] ?? ''), $q);
		$path = (string)($d['attributes']['path'] ?? '');
		$okDeep = (bool)preg_match('#/f/\d+$#', (string)($u['path'] ?? ''));
		$okQuery = isset($q['dir'], $q['scrollto']) && ($q['dir'] . $q['scrollto']) === $path;
		$fail += ($okDeep && $okQuery) ? 0 : 1;
		echo '   [vertrag] deep-link=' . ($okDeep ? 'OK' : 'FAIL(' . ($u['path'] ?? '?') . ')')
			. ' dir+scrollto==path=' . ($okQuery ? 'OK' : 'FAIL(' . ($q['dir'] ?? '?') . '+' . ($q['scrollto'] ?? '?') . ' != ' . $path . ')')
			. "\n   " . (string)$d['resourceUrl'] . "\n";
	}
	echo ($fail === 0 ? 'VERTRAG: OK' : 'VERTRAG: ' . $fail . ' FEHLER') . "\n";
} catch (\Throwable $e) {
	echo "EX-SUCHE: " . get_class($e) . ": " . $e->getMessage() . " @ " . $e->getFile() . ":" . $e->getLine() . "\n";
}
