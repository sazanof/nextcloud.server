<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Vincent Petry <vincent@nextcloud.com>
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
namespace OCA\UpdateNotification\Controller;

use OC\User\Backend;
use OCP\User\Backend\ICountUsersBackend;
use OCA\UpdateNotification\ResetTokenBackgroundJob;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\Security\ISecureRandom;
use OCP\Util;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class AdminController extends Controller {
	/** @var IJobList */
	private $jobList;
	/** @var ISecureRandom */
	private $secureRandom;
	/** @var IConfig */
	private $config;
	/** @var ITimeFactory */
	private $timeFactory;
	/** @var IL10N */
	private $l10n;
	/** @var IUserManager */
	private $userManager;
	/** @var LoggerInterface */
	private $logger;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param IJobList $jobList
	 * @param ISecureRandom $secureRandom
	 * @param IConfig $config
	 * @param ITimeFactory $timeFactory
	 * @param IL10N $l10n
	 */
	public function __construct($appName,
								IRequest $request,
								IJobList $jobList,
								ISecureRandom $secureRandom,
								IConfig $config,
								ITimeFactory $timeFactory,
								IL10N $l10n,
								IUserManager $userManager,
								LoggerInterface $logger) {
		parent::__construct($appName, $request);
		$this->jobList = $jobList;
		$this->secureRandom = $secureRandom;
		$this->config = $config;
		$this->timeFactory = $timeFactory;
		$this->l10n = $l10n;
		$this->userManager = $userManager;
		$this->logger = $logger;
	}

	private function isUpdaterEnabled() {
		if ($this->config->getSystemValue('upgrade.disable-web', false) || $this->getUserCount() > 100) {
			return false;
		}
		return true;
	}

	/**
	 * @param string $channel
	 * @return DataResponse
	 */
	public function setChannel(string $channel): DataResponse {
		Util::setChannel($channel);
		$this->config->setAppValue('core', 'lastupdatedat', 0);
		return new DataResponse(['status' => 'success', 'data' => ['message' => $this->l10n->t('Channel updated')]]);
	}

	/**
	 * @return DataResponse
	 */
	public function createCredentials(): DataResponse {
		if (!$this->isUpdaterEnabled()) {
			return new DataResponse(['status' => 'error', 'message' => $this->l10n->t('Web updater is disabled')], Http::STATUS_FORBIDDEN);
		}

		// Create a new job and store the creation date
		$this->jobList->add(ResetTokenBackgroundJob::class);
		$this->config->setAppValue('core', 'updater.secret.created', $this->timeFactory->getTime());

		// Create a new token
		$newToken = $this->secureRandom->generate(64);
		$this->config->setSystemValue('updater.secret', password_hash($newToken, PASSWORD_DEFAULT));

		return new DataResponse($newToken);
	}

	// Copied from https://github.com/nextcloud/server/blob/a06001e0851abc6073af678b742da3e1aa96eec9/lib/private/Support/Subscription/Registry.php#L187-L214
	private function getUserCount(): int {
		$userCount = 0;
		$backends = $this->userManager->getBackends();
		foreach ($backends as $backend) {
			if ($backend->implementsActions(Backend::COUNT_USERS)) {
				/** @var ICountUsersBackend $backend */
				$backendUsers = $backend->countUsers();
				if ($backendUsers !== false) {
					$userCount += $backendUsers;
				} else {
					// TODO what if the user count can't be determined?
					$this->logger->warning('Can not determine user count for ' . get_class($backend), ['app' => 'updatenotification']);
				}
			}
		}

		$disabledUsers = $this->config->getUsersForUserValue('core', 'enabled', 'false');
		$disabledUsersCount = count($disabledUsers);
		$userCount = $userCount - $disabledUsersCount;

		if ($userCount < 0) {
			$userCount = 0;

			// this should never happen
			$this->logger->warning("Total user count was negative (users: $userCount, disabled: $disabledUsersCount)", ['app' => 'updatenotification']);
		}

		return $userCount;
	}
}
