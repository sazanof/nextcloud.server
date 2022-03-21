<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCA\UpdateNotification\Tests\Controller;

use OCA\UpdateNotification\Controller\AdminController;
use OCA\UpdateNotification\ResetTokenBackgroundJob;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\Security\ISecureRandom;
use Test\TestCase;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class AdminControllerTest extends TestCase {
	/** @var IRequest|\PHPUnit\Framework\MockObject\MockObject */
	private $request;
	/** @var IJobList|\PHPUnit\Framework\MockObject\MockObject */
	private $jobList;
	/** @var ISecureRandom|\PHPUnit\Framework\MockObject\MockObject */
	private $secureRandom;
	/** @var IConfig|\PHPUnit\Framework\MockObject\MockObject */
	private $config;
	/** @var AdminController */
	private $adminController;
	/** @var ITimeFactory|\PHPUnit\Framework\MockObject\MockObject */
	private $timeFactory;
	/** @var IL10N|\PHPUnit\Framework\MockObject\MockObject */
	private $l10n;
	/** @var IUserManager|\PHPUnit\Framework\MockObject\MockObject */
	private $userManager;
	/** @var LoggerInterface|\PHPUnit\Framework\MockObject\MockObject */
	private $logger;

	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->jobList = $this->createMock(IJobList::class);
		$this->secureRandom = $this->createMock(ISecureRandom::class);
		$this->config = $this->createMock(IConfig::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->adminController = new AdminController(
			'updatenotification',
			$this->request,
			$this->jobList,
			$this->secureRandom,
			$this->config,
			$this->timeFactory,
			$this->l10n,
			$this->userManager,
			$this->logger
		);
	}

	public function testCreateCredentials() {
		$backend1 = $this->createMock(UserInterface::class);
		$backend2 = $this->createMock(UserInterface::class);
		$backend3 = $this->createMock(UserInterface::class);
		$backend1
			->expects($this->once())
			->method('implementsActions')
			->with(Backend::COUNT_USERS)
			->willReturn(false);
		$backend2
			->expects($this->once())
			->method('implementsActions')
			->with(Backend::COUNT_USERS)
			->willReturn(true);
		$backend3
			->expects($this->once())
			->method('implementsActions')
			->with(Backend::COUNT_USERS)
			->willReturn(true);
		$backend1
			->expects($this->never())
			->method('countUsers');
		$backend2
			->expects($this->once())
			->method('countUsers')
			->with()
			->willReturn(false);
		$backend3
			->expects($this->once())
			->method('countUsers')
			->with()
			->willReturn(5);
		$this->userManager
			->expects($this->once())
			->method('getBackends')
			->with()
			->willReturn([$backend1, $backend2, $backend3]);
		$this->jobList
			->expects($this->once())
			->method('add')
			->with(ResetTokenBackgroundJob::class);
		$this->secureRandom
			->expects($this->once())
			->method('generate')
			->with(64)
			->willReturn('MyGeneratedToken');
		$this->config
			->expects($this->once())
			->method('setSystemValue')
			->with('updater.secret');
		$this->timeFactory
			->expects($this->once())
			->method('getTime')
			->willReturn(12345);
		$this->config
			->expects($this->once())
			->method('setAppValue')
			->with('core', 'updater.secret.created', 12345);

		$expected = new DataResponse('MyGeneratedToken');
		$this->assertEquals($expected, $this->adminController->createCredentials());
	}
}
