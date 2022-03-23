<?php

namespace OCP\Files\Lock;

use OCP\Lock\LockedException;

/**
 * @since 24.0.0
 */
class OwnerLockedException extends LockedException {
	private ILock $lock;

	/**
	 * @since 24.0.0
	 */
	public function __construct(ILock $lock) {
		$this->lock = $lock;
		$path = '';
		$readablePath = '';
		parent::__construct($path, null, $lock->getOwner(), $readablePath);
	}

	/**
	 * @since 24.0.0
	 */
	public function getLock(): ILock {
		return $this->lock;
	}
}
