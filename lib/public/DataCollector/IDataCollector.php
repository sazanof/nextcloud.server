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

use OC\AppFramework\Http\Request;
use OCP\AppFramework\Http\Response;

/**
 * DataCollectorInterface.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface IDataCollector {
	/**
	 * Collects data for the given Request and Response.
	 */
	public function collect(Request $request, Response $response, \Throwable $exception = null): void;

	/**
	 * Reset the state of the profiler.
	 */
	public function reset(): void;

	/**
	 * Returns the name of the collector.
	 */
	public function getName(): string;

}
