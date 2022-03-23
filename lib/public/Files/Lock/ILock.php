<?php

namespace OCP\Files\Lock;

/**
 * @since 24.0.0
 */
interface ILock {

	/** @since 24.0.0 */
	public const TYPE_USER = 0;
	/** @since 24.0.0 */
	public const TYPE_APP = 1;
	/** @since 24.0.0 */
	public const TYPE_TOKEN = 2;

	/** @since 24.0.0 */
	public const LOCK_EXCLUSIVE = 1;
	/** @since 24.0.0 */
	public const LOCK_SHARED = 2;

	/** @since 24.0.0 */
	public const LOCK_DEPTH_ZERO = 0;
	/** @since 24.0.0 */
	public const LOCK_DEPTH_INFINITE = -1;

	/**
	 * @since 24.0.0
	 */
	public function getLockType(): int;

	/**
	 * @since 24.0.0
	 */
	public function getOwner(): string;

	/**
	 * @since 24.0.0
	 */
	public function getFileId(): int;

	/**
	 * @since 24.0.0
	 */
	public function getTimeout(): int;

	/**
	 * @since 24.0.0
	 */
	public function getCreatedAt(): int;

	/**
	 * @since 24.0.0
	 */
	public function getToken(): string;

	/**
	 * @since 24.0.0
	 */
	public function __toString(): string;
}
