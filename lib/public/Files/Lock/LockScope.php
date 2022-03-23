<?php

namespace OCP\Files\Lock;

use OCP\Files\Node;

/**
 * @since 24.0.0
 */
class LockScope {
	private Node $node;
	private int $type;
	private string $owner;

	/**
	 * @param Node $node Node that is owned by the lock
	 * @param int $type Type of the lock owner
	 * @param string $owner Unique identifier for the lock owner based on the type
	 * @since 24.0.0
	 */
	public function __construct(
		Node $node,
		int $type,
		string $owner
	) {
		$this->node = $node;
		$this->type = $type;
		$this->owner = $owner;
	}

	/**
	 * @return Node
	 * @since 24.0.0
	 */
	public function getNode(): Node {
		return $this->node;
	}

	/**
	 * @return int
	 * @since 24.0.0
	 */
	public function getType(): int {
		return $this->type;
	}

	/**
	 * @return string user id / app id / lock token depending on the type
	 * @since 24.0.0
	 */
	public function getOwner(): string {
		return $this->owner;
	}

	/**
	 * @since 24.0.0
	 */
	public function __toString() {
		$typeString = 'unknown';
		if ($this->type === ILock::TYPE_USER) {
			$typeString = 'ILock::TYPE_USER';
		}
		if ($this->type === ILock::TYPE_APP) {
			$typeString = 'ILock::TYPE_APP';
		}
		if ($this->type === ILock::TYPE_TOKEN) {
			$typeString = 'ILock::TYPE_TOKEN';
		}
		return "$typeString  $this->owner " . $this->getNode()->getId();
	}
}
