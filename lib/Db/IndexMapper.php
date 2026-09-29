<?php

declare(strict_types=1);

namespace OCA\Dossier\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<IndexEntry>
 */
class IndexMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'dossier_index', IndexEntry::class);
	}

	public function findOne(string $owner, int $fileId): ?IndexEntry {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('owner', $qb->createNamedParameter($owner)))
			->setMaxResults(1);
		$row = $qb->executeQuery()->fetch();
		if ($row === false) {
			return null;
		}
		return $this->mapRowToEntity($row);
	}

	/**
	 * Volltextsuche (MariaDB FULLTEXT, BOOLEAN MODE) mit LIKE-Fallback.
	 *
	 * @return array<int, array{file_id:int,name:string,path:string,text:string}>
	 */
	/**
	 * @param list<array{owner:string,folder:string}> $scopes
	 */
	public function search(array $scopes, string $term, int $limit): array {
		$limit = max(1, min(100, $limit));
		$boolean = $this->buildBoolean($term);
		$rows = [];
		if ($scopes === []) {
			return [];
		}
		if ($boolean !== '') {
			try {
				$qb = $this->db->getQueryBuilder();
				$qb->select('file_id', 'name', 'path', 'text')
					->from($this->getTableName())
					->where($this->scopeExpr($qb, $scopes))
					->andWhere('MATCH(`name`, `text`) AGAINST (' . $qb->createNamedParameter($boolean) . ' IN BOOLEAN MODE)')
					->setMaxResults($limit);
				$rows = $qb->executeQuery()->fetchAll();
			} catch (\Throwable) {
				$rows = [];
			}
		}
		if ($rows === []) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('file_id', 'name', 'path', 'text')
				->from($this->getTableName())
				->where($this->scopeExpr($qb, $scopes))
				->andWhere($qb->expr()->orX(
					$qb->expr()->iLike('name', $qb->createNamedParameter('%' . $this->escapeLike($term) . '%')),
					$qb->expr()->iLike('text', $qb->createNamedParameter('%' . $this->escapeLike($term) . '%'))
				))
				->setMaxResults($limit);
			$rows = $qb->executeQuery()->fetchAll();
		}
		return array_map(static function ($r): array {
			return [
				'file_id' => (int)$r['file_id'],
				'name' => (string)$r['name'],
				'path' => (string)$r['path'],
				'text' => (string)$r['text'],
			];
		}, $rows);
	}

	/**
	 * Nur Zeilen UNTERHALB von $folder (Praefix) werden geprueft/geloescht — so kann
	 * der Lauf eines Suchbereichs keinen anderen Bereich verschlucken (Multi-Scope!).
	 * @param list<int> $keepFileIds
	 */
	public function deleteMissing(string $owner, string $folder, array $keepFileIds): int {
		// Erst Kandidaten holen, dann in 500er-Bloecken per positivem IN loeschen:
		// NOT IN mit >1000 Parametern wirft QueryException (Expressions-Grenze).
		$prefix = $this->prefixOf($folder);
		$qb = $this->db->getQueryBuilder();
		$qb->select('file_id')
			->from($this->getTableName())
			->where($qb->expr()->eq('owner', $qb->createNamedParameter($owner)))
			->andWhere($qb->expr()->like('path', $qb->createNamedParameter($this->escapeLike($prefix) . '%')));
		$existing = array_map('intval', array_column($qb->executeQuery()->fetchAll(), 'file_id'));
		$stale = array_values(array_diff($existing, $keepFileIds));
		$deleted = 0;
		foreach (array_chunk($stale, 500) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete($this->getTableName())
				->where($qb->expr()->eq('owner', $qb->createNamedParameter($owner)))
				->andWhere($qb->expr()->like('path', $qb->createNamedParameter($this->escapeLike($prefix) . '%')))
				->andWhere($qb->expr()->in('file_id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
			$deleted += (int)$qb->executeStatement();
		}
		return $deleted;
	}

	public function deleteByFileId(int $fileId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/** Ordner -> Praefix fuer Pfad-LIKE (Pfade immer mit fuehrendem Slash). */
	private function prefixOf(string $folder): string {
		return rtrim($folder, '/') . '/';
	}

	/**
	 * OR-Gruppe ueber alle Suchbereiche: (owner = ? AND path LIKE 'ordner/%').
	 * @param list<array{owner:string,folder:string}> $scopes
	 */
	private function scopeExpr(IQueryBuilder $qb, array $scopes) {
		$or = $qb->expr()->orX();
		foreach ($scopes as $s) {
			$or->add($qb->expr()->andX(
				$qb->expr()->eq('owner', $qb->createNamedParameter($s['owner'])),
				$qb->expr()->like('path', $qb->createNamedParameter($this->escapeLike($this->prefixOf($s['folder'])) . '%')),
			));
		}
		return $or;
	}

	private function buildBoolean(string $term): string {
		$clean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $term) ?? '';
		$words = preg_split('/\s+/', $clean) ?: [];
		$out = [];
		foreach ($words as $w) {
			if (mb_strlen($w) >= 2) {
				$out[] = '+' . $w . '*';
			}
		}
		return implode(' ', array_slice($out, 0, 8));
	}

	private function escapeLike(string $s): string {
		return addcslashes($s, '\\%_');
	}
}
