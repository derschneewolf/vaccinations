<?php

declare(strict_types=1);

namespace OCA\Vaccinations\Db;

use OCP\AppFramework\Db\TTransactional;
use OCP\IDBConnection;

/**
 * Runs a callback in a database transaction (rolled back on any exception).
 * A separate class keeps IDBConnection out of service unit tests.
 */
class TransactionRunner {
	use TTransactional;

	public function __construct(
		private readonly IDBConnection $db,
	) {
	}

	/**
	 * @template T
	 * @param callable(): T $callback
	 * @return T
	 */
	public function run(callable $callback): mixed {
		return $this->atomic($callback, $this->db);
	}
}
