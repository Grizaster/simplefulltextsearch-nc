<?php

declare(strict_types=1);

namespace {
	// Autoloader fuer OCA\SimpleFullTextFileSearch
	spl_autoload_register(static function (string $class): void {
		$prefix = 'OCA\\SimpleFullTextFileSearch\\';
		if (str_starts_with($class, $prefix)) {
			$relative = substr($class, strlen($prefix));
			$path = __DIR__ . '/../lib/' . str_replace('\\', '/', $relative) . '.php';
			if (file_exists($path)) {
				require_once $path;
			}
		}
	});
}

// Stubs fuer Nextcloud OCP-Klassen, damit Tests ohne vollstaendige Nextcloud-Laufzeit laufen koennen.

namespace OCP {
	if (!interface_exists(IUser::class)) {
		interface IUser {
			public function getUID(): string;
		}
	}

	if (!interface_exists(IRequest::class)) {
		interface IRequest {
		}
	}

	if (!interface_exists(IUserManager::class)) {
		interface IUserManager {
			public function get(string $uid): ?IUser;
		}
	}

	if (!interface_exists(IUserSession::class)) {
		interface IUserSession {
			public function getUser(): ?IUser;
		}
	}

	if (!interface_exists(IAppConfig::class)) {
		interface IAppConfig {
			public function getValueString(string $app, string $key, string $default = ''): string;
			public function setValueString(string $app, string $key, string $value): void;
		}
	}

	if (!interface_exists(IL10N::class)) {
		interface IL10N {
			public function t(string $text, array $parameters = []): string;
		}
	}

	if (!interface_exists(IURLGenerator::class)) {
		interface IURLGenerator {
			public function linkToRoute(string $routeName, array $arguments = []): string;
			public function linkToRouteAbsolute(string $routeName, array $arguments = []): string;
			public function getAbsoluteURL(string $url): string;
		}
	}

	if (!interface_exists(IPreview::class)) {
		interface IPreview {
			public function isMimeSupported(string $mime): bool;
		}
	}

	if (!interface_exists(IDBConnection::class)) {
		interface IDBConnection {
			public function executeQuery(string $query, array $params = [], array $types = []);
			public function executeStatement(string $query, array $params = [], array $types = []): int;
			public function getQueryBuilder();
		}
	}
}

namespace OCP\Group {
	if (!interface_exists(IGroupManager::class)) {
		interface IGroupManager {
			public function isAdmin(string $uid): bool;
		}
	}
}

namespace OCP\Files {
	if (!class_exists(Node::class)) {
		abstract class Node {
			abstract public function getId();
			abstract public function getName(): string;
			abstract public function getPath(): string;
			abstract public function getMimetype(): string;
			abstract public function isReadable(): bool;
		}
	}

	if (!class_exists(Folder::class)) {
		class Folder extends Node {
			public function getId() { return 1; }
			public function getName(): string { return 'folder'; }
			public function getPath(): string { return '/folder'; }
			public function getMimetype(): string { return 'httpd/unix-directory'; }
			public function isReadable(): bool { return true; }
			public function getRelativePath(string $path): ?string { return null; }
			public function getFirstNodeById(int $id): ?Node { return null; }
			public function get(string $path) { return null; }
			public function getDirectoryListing(): array { return []; }
		}
	}

	if (!class_exists(File::class)) {
		class File extends Node {
			public function getId() { return 2; }
			public function getName(): string { return 'file.txt'; }
			public function getPath(): string { return '/folder/file.txt'; }
			public function getMimetype(): string { return 'text/plain'; }
			public function isReadable(): bool { return true; }
			public function getSize(): int { return 100; }
			public function getMTime(): int { return time(); }
			public function getStorage() { return null; }
			public function getInternalPath(): string { return 'file.txt'; }
		}
	}

	if (!interface_exists(IRootFolder::class)) {
		interface IRootFolder {
			public function getUserFolder(string $userId): Folder;
		}
	}

	if (!interface_exists(IMimeTypeDetector::class)) {
		interface IMimeTypeDetector {
			public function mimeTypeIcon(string $mimetype): string;
		}
	}

	if (!class_exists(NotFoundException::class)) {
		class NotFoundException extends \Exception {}
	}
}

namespace OCP\Search {
	if (!interface_exists(IProvider::class)) {
		interface IProvider {
			public function getId(): string;
			public function getName(): string;
			public function getOrder(string $route, array $routeParameters): ?int;
			public function search(\OCP\IUser $user, ISearchQuery $query): SearchResult;
		}
	}

	if (!interface_exists(ISearchQuery::class)) {
		interface ISearchQuery {
			public function getTerm(): string;
			public function getLimit(): int;
		}
	}

	if (!class_exists(SearchResultEntry::class)) {
		class SearchResultEntry {
			private array $attributes = [];

			public function __construct(
				private string $thumbnailUrl,
				private string $title,
				private string $description,
				private string $resourceUrl,
				private string $icon,
			) {
			}

			public function addAttribute(string $key, mixed $value): void {
				$this->attributes[$key] = $value;
			}

			public function getAttribute(string $key): mixed {
				return $this->attributes[$key] ?? null;
			}

			public function getAttributes(): array {
				return $this->attributes;
			}

			public function getTitle(): string {
				return $this->title;
			}

			public function getDescription(): string {
				return $this->description;
			}

			public function getResourceUrl(): string {
				return $this->resourceUrl;
			}

			public function getThumbnailUrl(): string {
				return $this->thumbnailUrl;
			}
		}
	}

	if (!class_exists(SearchResult::class)) {
		class SearchResult {
			private function __construct(
				private string $name,
				private array $entries,
			) {
			}

			public static function complete(string $name, array $entries): self {
				return new self($name, $entries);
			}

			public function getEntries(): array {
				return $this->entries;
			}

			public function getName(): string {
				return $this->name;
			}
		}
	}
}

namespace OCP\DB {
	if (!interface_exists(ISchemaWrapper::class)) {
		interface ISchemaWrapper {
			public function hasTable(string $tableName): bool;
			public function getTable(string $tableName);
			public function createTable(string $tableName);
		}
	}
}

namespace OCP\DB\QueryBuilder {
	if (!interface_exists(IQueryBuilder::class)) {
		interface IQueryBuilder {
			public const PARAM_INT = 1;
			public const PARAM_INT_ARRAY = 2;
			public const PARAM_STR = 3;
			public function select(string ...$select);
			public function from(string $from, ?string $alias = null);
			public function where($predicates);
			public function andWhere($predicates);
			public function delete(string $delete, ?string $alias = null);
			public function setMaxResults(int $maxResults);
			public function setFirstResult(int $firstResult);
			public function executeQuery();
			public function executeStatement(): int;
			public function expr();
			public function createNamedParameter(mixed $value, int $type = 3, ?string $placeHolder = null): string;
		}
	}
}

namespace OCP\AppFramework {
	if (!class_exists(App::class)) {
		class App {
			public function __construct(string $appName, array $urlParams = []) {}
		}
	}

	if (!class_exists(Controller::class)) {
		abstract class Controller {
			public function __construct(
				protected string $appName,
				protected \OCP\IRequest $request,
			) {
			}
		}
	}

	if (!class_exists(Http::class)) {
		class Http {
			public const STATUS_OK = 200;
			public const STATUS_BAD_REQUEST = 400;
			public const STATUS_FORBIDDEN = 403;
		}
	}
}

namespace OCP\AppFramework\Bootstrap {
	if (!interface_exists(IBootstrap::class)) {
		interface IBootstrap {}
	}
}

namespace OCP\AppFramework\Http {
	if (!class_exists(JSONResponse::class)) {
		class JSONResponse {
			public function __construct(
				private mixed $data = [],
				private int $statusCode = 200,
			) {
			}

			public function getData(): mixed {
				return $this->data;
			}

			public function getStatus(): int {
				return $this->statusCode;
			}
		}
	}
}

namespace OCP\AppFramework\Db {
	if (!class_exists(Entity::class)) {
		abstract class Entity {
			protected $id;
			public function getId() { return $this->id; }
			public function setId($id) { $this->id = $id; }
			protected function addType($name, $type) {}
		}
	}

	if (!class_exists(QBMapper::class)) {
		abstract class QBMapper {
			public function __construct(
				protected \OCP\IDBConnection $db,
				protected string $tableName,
				protected ?string $entityClass = null,
			) {
			}

			public function getTableName(): string {
				return $this->tableName;
			}
		}
	}
}

namespace OCP\Migration {
	if (!interface_exists(IOutput::class)) {
		interface IOutput {
			public function info(string $message): void;
			public function warning(string $message): void;
		}
	}

	if (!interface_exists(IMigrationStep::class)) {
		interface IMigrationStep {
			public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options);
			public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void;
		}
	}

	if (!class_exists(SimpleMigrationStep::class)) {
		abstract class SimpleMigrationStep implements IMigrationStep {
			public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options) { return null; }
			public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {}
		}
	}
}

namespace Psr\Log {
	if (!interface_exists(LoggerInterface::class)) {
		interface LoggerInterface {
			public function warning(string|\Stringable $message, array $context = []): void;
			public function info(string|\Stringable $message, array $context = []): void;
			public function error(string|\Stringable $message, array $context = []): void;
			public function debug(string|\Stringable $message, array $context = []): void;
		}
	}
}
