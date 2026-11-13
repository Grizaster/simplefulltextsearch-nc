<?php

declare(strict_types=1);

namespace OCA\SimpleFullTextFileSearch\Tests;

use OCA\SimpleFullTextFileSearch\BackgroundJob\ReindexJob;
use OCA\SimpleFullTextFileSearch\Controller\SettingsController;
use OCA\SimpleFullTextFileSearch\Service\IndexerService;
use OCP\AppFramework\Http;
use OCP\BackgroundJob\IJobList;
use OCP\IGroupManager;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class SettingsControllerTest extends TestCase {
	/**
	 * Vertrag: Die Endpunkte duerfen KEINE Relaxing-Attribute tragen
	 * (#[NoAdminRequired], #[PublicPage], #[NoCSRFRequired]) — ohne sie verlangt
	 * Nextclouds SecurityMiddleware Admin-Rechte + CSRF (NotAdminException bzw.
	 * CrossSiteRequestForgeryException). Ein eigenes "AdminRequired"-Attribut
	 * wurde bewusst entfernt: es hat bei Nextcloud keine Wirkung.
	 */
	public function testNoRelaxingAttributesOrAnnotations(): void {
		$reflector = new \ReflectionClass(SettingsController::class);
		$forbidden = ['NoAdminRequired', 'PublicPage', 'NoCSRFRequired'];

		foreach (['save', 'reindex'] as $action) {
			$method = $reflector->getMethod($action);
			$doc = (string)$method->getDocComment();
			foreach ($forbidden as $name) {
				$this->assertSame(
					[],
					$method->getAttributes($name),
					"Methode SettingsController::{$action} darf das Attribut #[{$name}] nicht tragen."
				);
				$this->assertStringNotContainsString(
					'@' . $name,
					$doc,
					"Methode SettingsController::{$action} darf @{ $name } nicht im DocBlock tragen."
				);
			}
		}
	}

	/**
	 * Negativ-Vertrag (Fail-Open-Schutz): Die kritischen DI-Deps muessen zwingend
	 * gesetzt sein — Nullable-Defaults wuerden die Pruefung still abschalten,
	 * sobald der Container nichts liefert.
	 */
	public function testCriticalDependenciesAreMandatory(): void {
		$ctor = new \ReflectionMethod(SettingsController::class, '__construct');
		$mandatory = [IGroupManager::class, IUserSession::class, IJobList::class];
		foreach ($ctor->getParameters() as $param) {
			$typeName = $param->getType()?->getName() ?? '';
			if (in_array($typeName, $mandatory, true)) {
				$this->assertFalse(
					$param->isDefaultValueAvailable(),
					"Konstruktor-Parameter {$param->getName()} darf keinen Default haben (Fail-Open-Gefahr)."
				);
				$this->assertFalse(
					$param->allowsNull(),
					"Konstruktor-Parameter {$param->getName()} darf nicht nullable sein (Fail-Open-Gefahr)."
				);
			}
		}
	}

	/**
	 * P1: Admin-ACL — Nicht-Admin-Benutzer werden mit HTTP 403 Forbidden abgewiesen
	 * (eigene Belt-Pruefung, unabhaengig vom Core-Middleware-Lauf).
	 */
	public function testNonAdminAccessForbidden(): void {
		$nonAdmin = $this->createStub(IUser::class);
		$nonAdmin->method('getUID')->willReturn('standarduser');
		$userSession = $this->createStub(IUserSession::class);
		$userSession->method('getUser')->willReturn($nonAdmin);
		$groupManager = $this->createStub(IGroupManager::class);
		$groupManager->method('isAdmin')->willReturn(false);

		$controller = new SettingsController(
			$this->createStub(IRequest::class),
			$this->createStub(IAppConfig::class),
			$this->createStub(IndexerService::class),
			$this->createStub(IUserManager::class),
			$groupManager,
			$userSession,
			$this->createStub(IJobList::class),
		);

		$saveResponse = $controller->save(json_encode([['owner' => 'standarduser', 'folder' => '/Docs']]));
		$this->assertSame(Http::STATUS_FORBIDDEN, $saveResponse->getStatus());

		$reindexResponse = $controller->reindex();
		$this->assertSame(Http::STATUS_FORBIDDEN, $reindexResponse->getStatus());
	}

	/**
	 * Ohne eingeloggten User (getUser() === null) ebenfalls 403 — kein Fail-Open.
	 */
	public function testMissingUserForbidden(): void {
		$userSession = $this->createStub(IUserSession::class);
		$userSession->method('getUser')->willReturn(null);

		$controller = new SettingsController(
			$this->createStub(IRequest::class),
			$this->createStub(IAppConfig::class),
			$this->createStub(IndexerService::class),
			$this->createStub(IUserManager::class),
			$this->createStub(IGroupManager::class),
			$userSession,
			$this->createStub(IJobList::class),
		);

		$this->assertSame(Http::STATUS_FORBIDDEN, $controller->reindex()->getStatus());
	}

	/**
	 * Admin-Benutzer duerfen speichern; reindex() plant einen EINMALIGEN
	 * Hintergrundjob (keine synchrone Indexierung im HTTP-Request — sonst
	 * Gateway-Timeout bei grossen Bestaenden) und liefert 'scheduled'.
	 */
	public function testAdminAccessGrantedAndReindexSchedulesJob(): void {
		$admin = $this->createStub(IUser::class);
		$admin->method('getUID')->willReturn('admin');
		$userSession = $this->createStub(IUserSession::class);
		$userSession->method('getUser')->willReturn($admin);
		$groupManager = $this->createStub(IGroupManager::class);
		$groupManager->method('isAdmin')->willReturnCallback(static fn (string $uid) => $uid === 'admin');

		$userManager = $this->createStub(IUserManager::class);
		$userManager->method('get')->willReturnCallback(static fn (string $uid) => $uid === 'admin' ? $admin : null);

		$jobList = $this->createMock(IJobList::class);
		$jobList->expects($this->once())
			->method('add')
			->with(ReindexJob::class);

		$controller = new SettingsController(
			$this->createStub(IRequest::class),
			$this->createStub(IAppConfig::class),
			$this->createStub(IndexerService::class),
			$userManager,
			$groupManager,
			$userSession,
			$jobList,
		);

		$saveResponse = $controller->save(json_encode([['owner' => 'admin', 'folder' => '/Docs']]));
		$this->assertSame(Http::STATUS_OK, $saveResponse->getStatus());

		$reindexResponse = $controller->reindex();
		$this->assertSame(Http::STATUS_OK, $reindexResponse->getStatus());
		$data = $reindexResponse->getData();
		$this->assertTrue($data['scheduled'] ?? false, "reindex() muss 'scheduled' liefern (asyncer Hintergrundjob).");
	}
}
