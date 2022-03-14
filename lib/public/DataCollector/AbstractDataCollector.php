<?php
/**
 * @copyright 2022 Carl Schwan <carl@carlschwan.eu>
 *
 * @author Carl Schwan <carl@carlschwan.eu>
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @license AGPL-3.0-or-later AND MIT
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=1);

namespace OCP\DataCollector;

/**
 * Children of this class must store the collected data in
 * the data property.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bschussek@symfony.com>
 * @author Carl Schwan <carl@carlschwan.eu>
 */
abstract class AbstractDataCollector implements IDataCollector, \JsonSerializable {
	/** @var array */
	protected $data = [];

	public function getName(): string {
		return static::class;
	}

	/**
	 * Reset the state of the profiler. By default it only empties the
	 * $this->data contents, but you can override this method to do
	 * additional cleaning.
	 */
	public function reset(): void {
		$this->data = [];
	}

	public function __sleep(): array {
		return ['data'];
	}

	/**
	 * @internal to prevent implementing \Serializable
	 */
	final protected function serialize() {
	}

	/**
	 * @internal to prevent implementing \Serializable
	 */
	final protected function unserialize(string $data) {
	}

	public function jsonSerialize() {
		return $this->data;
	}

}
