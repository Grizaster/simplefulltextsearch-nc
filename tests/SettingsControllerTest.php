<?php

declare(strict_types=1);

namespace OCA\SimpleFullTextFileSearch\Tests;

use OCA\SimpleFullTextFileSearch\Controller\AdminRequired;
use OCA\SimpleFullTextFileSearch\Controller\SettingsController;
use OCA\SimpleFullTextFileSearch\Service\IndexerService;
use OCP\AppFramework\Http;
use OCP\Group\IGroupManager;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class SettingsControllerTest extends TestCase {
	/**
	 * P1: Admin-Absicherung:
	 * Sowohl echtes PHP-8-Attribut #[AdminRequired] als auch DocBlock @AdminRequired
	 * muessen auf save() und reindex() vorhanden sein.
	 */
	public function testAttributesAndDocBlocks(): void {
		$reflector = new \ReflectionClass(SettingsController::class);

		foreach (['save', 'reindex'] as $action) {
			$method = $reflector->getMethod($action);

			// 1. PHP 8 Attribut pruefen
			$attributes = $method->getAttributes(AdminRequired::class);
			$this->assertNotEmpty(
				$attributes,
				"Methode SettingsController::{$action} muss das PHP-8-Attribut #[AdminRequired] tragen."
			);

			// 2. DocBlock fuer aeltere NC-Versionen pruefen
			$doc = (string)$method->getDocComment();
			$this->assertStringContainsString(
				'@AdminRequired',
				$doc,
				"Methode SettingsController::{$action} muss @AdminRequired im DocBlock behalten."
			);
		}
	}

	/**
	 * P1: Admin-ACL:
	 * Nicht-Admin-Benutzer werden mit HTTP 403 Forbidden abgewiesen.
	 */
	public function testNonAdminAccessForbidden(): void {
		$request = $this->createStub(IRequest::class);
		$appConfig = $this->createStub(IAppConfig::class);
		$indexer = $this->createStub(IndexerService::class);
		$userManager = $this->createStub(IUserManager::class);
		$groupManager = $this->createStub(IGroupManager::class);
		$userSession = $this->createStub(IUserSession::class);

		$nonAdmin = $this->createStub(IUser::class);
		$nonAdmin->method('getUID')->willReturn('standarduser');
		$userSession->method('getUser')->willReturn($nonAdmin);
		$groupManager->method('isAdmin')->willReturnCallback(static fn (string $uid) => $uid === 'standarduser' ? false : false);

		$controller = new SettingsController(
			$request,
			$appConfig,
			$indexer,
			$userManager,
			$groupManager,
			$userSession,
		);

		// save() aufrufen
		$saveResponse = $controller->save(json_encode([['owner' => 'standarduser', 'folder' => '/Docs']]));
		$this->assertSame(Http::STATUS_FORBIDDEN, $saveResponse->getStatus());

		// reindex() aufrufen
		$reindexResponse = $controller->reindex();
		$this->assertSame(Http::STATUS_FORBIDDEN, $reindexResponse->getStatus());
	}

	/**
	 * Admin-Benutzer duerfen Einstellungen speichern und reindexieren.
	 */
	public function testAdminAccessGranted(): void {
		$request = $this->createStub(IRequest::class);
		$appConfig = $this->createStub(IAppConfig::class);
		$indexer = $this->createStub(IndexerService::class);
		$userManager = $this->createStub(IUserManager::class);
		$groupManager = $this->createStub(IGroupManager::class);
		$userSession = $this->createStub(IUserSession::class);

		$admin = $this->createStub(IUser::class);
		$admin->method('getUID')->willReturn('admin');
		$userSession->method('getUser')->willReturn($admin);
		$groupManager->method('isAdmin')->willReturnCallback(static fn (string $uid) => $uid === 'admin' ? true : false);

		$userManager->method('get')->willReturnCallback(static fn (string $uid) => $uid === 'admin' ? $admin : null);
		$indexer->method('indexAll')->willReturn(['indexed' => 10, 'scopes' => 1]);

		$controller = new SettingsController(
			$request,
			$appConfig,
			$indexer,
			$userManager,
			$groupManager,
			$userSession,
		);

		$saveResponse = $controller->save(json_encode([['owner' => 'admin', 'folder' => '/Docs']]));
		$this->assertSame(Http::STATUS_OK, $saveResponse->getStatus());

		$reindexResponse = $controller->reindex();
		$this->assertSame(Http::STATUS_OK, $reindexResponse->getStatus());
	}
}
