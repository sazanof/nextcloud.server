<?php

namespace OCP\Files\Lock;

use OCP\PreConditionNotMetException;

/**
 * @since 24.0.0
 */
interface ILockProvider {

	/**
	 * @throws PreConditionNotMetException
	 * @throws NoLockProviderException
	 * @since 24.0.0
	 */
	public function getLocks(int $fileId): array;

	/**
	 * @throws PreConditionNotMetException
	 * @throws OwnerLockedException
	 * @throws NoLockProviderException
	 * @since 24.0.0
	 */
	public function lock(LockScope $lockInfo): ILock;

	/**
	 * @throws PreConditionNotMetException
	 * @throws NoLockProviderException
	 * @since 24.0.0
	 */
	public function unlock(LockScope $lockInfo);
}
