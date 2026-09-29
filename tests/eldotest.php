<?php
// End-to-End-Test Dossier: Indexierung + Provider-Suche (läuft via php8.3 CLI)
require_once '/var/www/nextcloud/lib/base.php';

use OCA\Dossier\Search\Provider;
use OCA\Dossier\Service\IndexerService;
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
	foreach ($entries as $e) {
		$d = $e->jsonSerialize();
		echo '- ' . $d['title'] . "\n   " . mb_substr((string)$d['subline'], 0, 140) . "\n";
	}
} catch (\Throwable $e) {
	echo "EX-SUCHE: " . get_class($e) . ": " . $e->getMessage() . " @ " . $e->getFile() . ":" . $e->getLine() . "\n";
}
