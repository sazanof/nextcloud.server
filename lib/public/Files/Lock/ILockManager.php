<?php

namespace OCP\Files\Lock;

use OCP\PreConditionNotMetException;

/**
 * @since 24.0.0
 */
interface ILockManager extends ILockProvider {

	/**
	 * @throws PreConditionNotMetException if there is already a lock provider registered
	 * @since 24.0.0
	 */
	public function registerLockProvider(ILockProvider $lockProvider): void;

	/**
	 * @return bool
	 * @since 24.0.0
	 */
	public function isLockProviderAvailable(): bool;

	/**
	 * Run within the scope of a given lock condition
	 *
	 * The callback will also be executed if no lock provider is present
	 *
	 * @since 24.0.0
	 */
	public function runInScope(LockScope $lock, callable $callback): void;

	/**
	 * @throws NoLockProviderException if there is no lock provider available
	 * @since 24.0.0
	 */
	public function getLockInScope(): ?LockScope;
}
