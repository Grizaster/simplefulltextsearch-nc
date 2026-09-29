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

	public function findOneByFileId(int $fileId): ?IndexEntry {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)))
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
	public function search(string $owner, string $term, int $limit): array {
		$limit = max(1, min(100, $limit));
		$boolean = $this->buildBoolean($term);
		$rows = [];
		if ($boolean !== '') {
			try {
				$qb = $this->db->getQueryBuilder();
				$qb->select('file_id', 'name', 'path', 'text')
					->from($this->getTableName())
					->where($qb->expr()->eq('owner', $qb->createNamedParameter($owner)))
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
				->where($qb->expr()->eq('owner', $qb->createNamedParameter($owner)))
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

	/** @param list<int> $keepFileIds */
	public function deleteMissing(string $owner, array $keepFileIds): int {
		// Erst Kandidaten holen, dann in 500er-Bloecken per positivem IN loeschen:
		// NOT IN mit >1000 Parametern wirft QueryException (Expressions-Grenze).
		$qb = $this->db->getQueryBuilder();
		$qb->select('file_id')
			->from($this->getTableName())
			->where($qb->expr()->eq('owner', $qb->createNamedParameter($owner)));
		$existing = array_map('intval', array_column($qb->executeQuery()->fetchAll(), 'file_id'));
		$stale = array_values(array_diff($existing, $keepFileIds));
		$deleted = 0;
		foreach (array_chunk($stale, 500) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete($this->getTableName())
				->where($qb->expr()->eq('owner', $qb->createNamedParameter($owner)))
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
