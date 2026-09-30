<?php

declare(strict_types=1);

namespace OCA\SimpleFullTextFileSearch\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getFileId()
 * @method void setFileId(int $fileId)
 * @method string getOwner()
 * @method void setOwner(string $owner)
 * @method string getPath()
 * @method void setPath(string $path)
 * @method string getName()
 * @method void setName(string $name)
 * @method int getMtime()
 * @method void setMtime(int $mtime)
 * @method int getSize()
 * @method void setSize(int $size)
 * @method string getText()
 * @method void setText(string $text)
 * @method int getIndexedAt()
 * @method void setIndexedAt(int $indexedAt)
 */
class IndexEntry extends Entity {
	/** @var int filecache fileid (NICHT autoincrement) */
	protected $fileId;
	protected $owner;
	protected $path;
	protected $name;
	protected $mtime;
	protected $size;
	protected $text;
	protected $indexedAt;

	public function __construct() {
		$this->addType('fileId', 'integer');
		$this->addType('mtime', 'integer');
		$this->addType('size', 'integer');
		$this->addType('indexedAt', 'integer');
	}
}
