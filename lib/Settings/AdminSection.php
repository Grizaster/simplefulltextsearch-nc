<?php

declare(strict_types=1);

namespace OCA\SimpleFullTextFileSearch\Settings;

use OCP\Settings\IIconSection;
use OCP\IL10N;

class AdminSection implements IIconSection {
	public function __construct(
		private IL10N $l10n,
	) {
	}

	public function getId(): string {
		return 'simplefulltextsearch';
	}

	public function getName(): string {
		return $this->l10n->t('Document search');
	}

	public function getPriority(): int {
		return 80;
	}

	public function getIcon(): string {
		return 'icon-search';
	}
}
