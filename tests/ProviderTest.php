<?php

declare(strict_types=1);

namespace OCA\SimpleFullTextFileSearch\Tests;

use OCA\SimpleFullTextFileSearch\Db\IndexMapper;
use OCA\SimpleFullTextFileSearch\Search\Provider;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IPreview;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\ISearchQuery;
use PHPUnit\Framework\TestCase;

class ProviderTest extends TestCase {
	private IndexMapper $mapper;
	private IRootFolder $rootFolder;
	private IURLGenerator $urlGenerator;
	private IPreview $previewManager;
	private IMimeTypeDetector $mimeTypeDetector;
	private IAppConfig $appConfig;
	private IL10N $l10n;
	private Provider $provider;

	protected function setUp(): void {
		$this->mapper = $this->createStub(IndexMapper::class);
		$this->rootFolder = $this->createStub(IRootFolder::class);
		$this->urlGenerator = $this->createStub(IURLGenerator::class);
		$this->previewManager = $this->createStub(IPreview::class);
		$this->mimeTypeDetector = $this->createStub(IMimeTypeDetector::class);
		$this->appConfig = $this->createStub(IAppConfig::class);
		$this->l10n = $this->createStub(IL10N::class);

		$this->l10n->method('t')->willReturnCallback(static fn (string $text) => $text);
		$this->urlGenerator->method('linkToRoute')->willReturn('/index.php/f/123');
		$this->urlGenerator->method('getAbsoluteURL')->willReturnCallback(static fn (string $u) => 'https://nc.test' . $u);
		$this->mimeTypeDetector->method('mimeTypeIcon')->willReturn('icon-file');

		// Standard-Scope fuer Provider-Suche
		$this->appConfig->method('getValueString')
			->willReturnCallback(function (string $app, string $key, string $default) {
				if ($key === 'scopes') {
					return json_encode([['owner' => 'alice', 'folder' => '/Docs']]);
				}
				return $default;
			});

		$this->provider = new Provider(
			$this->mapper,
			$this->rootFolder,
			$this->urlGenerator,
			$this->previewManager,
			$this->mimeTypeDetector,
			$this->appConfig,
			$this->l10n,
		);
	}

	/**
	 * P1: isReadable-Verweigerung: Nicht lesbare Dateien (z. B. Drop-Only)
	 * duerfen nicht als Treffer erscheinen (weder Titel noch Snippet noch Preview).
	 */
	public function testIsReadableDenied(): void {
		$user = $this->createStub(IUser::class);
		$user->method('getUID')->willReturn('bob');

		$query = $this->createStub(ISearchQuery::class);
		$query->method('getTerm')->willReturn('vertraulich');
		$query->method('getLimit')->willReturn(10);

		$this->mapper->method('search')->willReturn([
			[
				'file_id' => 99,
				'name' => 'geheim.pdf',
				'path' => '/Docs/geheim.pdf',
				'text' => 'Das ist vertraulich und darf nicht geleakt werden.',
			],
		]);

		$unreadableNode = $this->createStub(File::class);
		$unreadableNode->method('getId')->willReturn(99);
		$unreadableNode->method('getName')->willReturn('geheim.pdf');
		$unreadableNode->method('isReadable')->willReturn(false); // Zugriff verweigert!

		$userFolder = $this->createStub(Folder::class);
		$userFolder->method('getFirstNodeById')->willReturnCallback(static fn (int $id) => $id === 99 ? $unreadableNode : null);

		$this->rootFolder->method('getUserFolder')->willReturnCallback(static fn (string $uid) => $uid === 'bob' ? $userFolder : null);

		$result = $this->provider->search($user, $query);
		$this->assertCount(0, $result->getEntries(), 'Nicht lesbare Datei darf nicht in den Suchergebnissen auftauchen.');
	}

	/**
	 * P1: Pfad-Neutralisierung: Der interne Scope-Eigentuemer-Pfad darf NICHT
	 * an fremde Nutzer leaken; Pfad und dir-Parameter muessen aus Sicht des Suchenden berechnet werden.
	 */
	public function testPathNeutralization(): void {
		$user = $this->createStub(IUser::class);
		$user->method('getUID')->willReturn('bob');

		$query = $this->createStub(ISearchQuery::class);
		$query->method('getTerm')->willReturn('Vertrag');
		$query->method('getLimit')->willReturn(10);

		$ownerPrivatePath = '/Geheim/Finanzen/2026/Vertrag.pdf';
		$this->mapper->method('search')->willReturn([
			[
				'file_id' => 42,
				'name' => 'Vertrag.pdf',
				'path' => $ownerPrivatePath,
				'text' => 'Vertrag zwischen Partei A und Partei B',
			],
		]);

		$readableNode = $this->createStub(File::class);
		$readableNode->method('getId')->willReturn(42);
		$readableNode->method('getName')->willReturn('Vertrag.pdf');
		$readableNode->method('getPath')->willReturn('/bob/files/Shared/Vertrag.pdf');
		$readableNode->method('getMimetype')->willReturn('application/pdf');
		$readableNode->method('isReadable')->willReturn(true);

		$userFolder = $this->createStub(Folder::class);
		$userFolder->method('getFirstNodeById')->willReturnCallback(static fn (int $id) => $id === 42 ? $readableNode : null);
		// Aus Sicht von Bob liegt die geteilte Datei in /Shared/Vertrag.pdf
		$userFolder->method('getRelativePath')->willReturnCallback(static fn (string $path) => $path === '/bob/files/Shared/Vertrag.pdf' ? '/Shared/Vertrag.pdf' : null);

		$this->rootFolder->method('getUserFolder')->willReturnCallback(static fn (string $uid) => $uid === 'bob' ? $userFolder : null);

		$result = $this->provider->search($user, $query);
		$entries = $result->getEntries();
		$this->assertCount(1, $entries);

		$entry = $entries[0];
		// Pfad-Attribut muss den neutralisierten Pfad des Suchenden enthalten
		$this->assertSame('/Shared/Vertrag.pdf', $entry->getAttribute('path'));

		// Der Eigentuemer-Pfad darf weder im Pfad-Attribut noch im Snippet noch im Link vorkommen
		$this->assertStringNotContainsString('/Geheim/Finanzen', $entry->getAttribute('path'));
		$this->assertStringNotContainsString('/Geheim/Finanzen', $entry->getDescription());
		$this->assertStringNotContainsString('/Geheim/Finanzen', $entry->getResourceUrl());

		// Snippet enthaelt den korrekten Pfad aus Sicht des Suchenden
		$this->assertStringContainsString('· /Shared/Vertrag.pdf', $entry->getDescription());

		// Deep-Link enthaelt dir=/Shared/ (URL-encoded)
		$this->assertStringContainsString('dir=%2FShared%2F', $entry->getResourceUrl());
	}

	/**
	 * P1: Aushungerungs-Schutz: Wenn unberechtigte Treffer aus Scope A vor
	 * berechtigten Treffern aus Scope B liegen, duerfen die berechtigten Treffer
	 * nicht verschwinden.
	 */
	public function testSearchStarvationAvoidance(): void {
		$user = $this->createStub(IUser::class);
		$user->method('getUID')->willReturn('charlie');

		$query = $this->createStub(ISearchQuery::class);
		$query->method('getTerm')->willReturn('Rechnung');
		$query->method('getLimit')->willReturn(5);

		// DB liefert z. B. 20 Zeilen aus Scope A (nicht lesbar), gefolgt von 5 Zeilen aus Scope B (lesbar)
		$unreadableRows = [];
		for ($i = 1; $i <= 20; $i++) {
			$unreadableRows[] = [
				'file_id' => $i,
				'name' => "admin_rechnung_{$i}.pdf",
				'path' => "/Admin/rechnung_{$i}.pdf",
				'text' => "Rechnung $i geheim",
			];
		}
		$readableRows = [];
		for ($j = 21; $j <= 25; $j++) {
			$readableRows[] = [
				'file_id' => $j,
				'name' => "charlie_rechnung_{$j}.pdf",
				'path' => "/Team/rechnung_{$j}.pdf",
				'text' => "Rechnung $j fuer Charlie",
			];
		}

		$allRows = array_merge($unreadableRows, $readableRows);

		$this->mapper->method('search')
			->willReturnCallback(function (array $scopes, string $term, int $limit, int $offset = 0) use ($allRows) {
				return array_slice($allRows, $offset, $limit);
			});

		$userFolder = $this->createStub(Folder::class);
		$userFolder->method('getFirstNodeById')->willReturnCallback(function (int $fileId) {
			if ($fileId <= 20) {
				$node = $this->createStub(File::class);
				$node->method('getId')->willReturn($fileId);
				$node->method('isReadable')->willReturn(false); // Scope A: unlesbar
				return $node;
			}
			$node = $this->createStub(File::class);
			$node->method('getId')->willReturn($fileId);
			$node->method('getName')->willReturn("rechnung_{$fileId}.pdf");
			$node->method('getPath')->willReturn("/charlie/files/rechnung_{$fileId}.pdf");
			$node->method('getMimetype')->willReturn('application/pdf');
			$node->method('isReadable')->willReturn(true); // Scope B: lesbar
			return $node;
		});
		$userFolder->method('getRelativePath')->willReturnCallback(static fn (string $p) => substr($p, strlen('/charlie/files')));

		$this->rootFolder->method('getUserFolder')->willReturnCallback(static fn (string $uid) => $uid === 'charlie' ? $userFolder : null);

		$result = $this->provider->search($user, $query);
		$entries = $result->getEntries();

		// Erwartet: Charlie erhaelt seine 5 berechtigten Treffer und sieht nicht 0 Treffer (Aushungerung behoben)
		$this->assertCount(5, $entries, 'Berechtigte Treffer muessen trotz vorangegangener unberechtigter Treffer gefunden werden.');
		foreach ($entries as $entry) {
			$this->assertStringContainsString('rechnung_', $entry->getTitle());
		}
	}
}
