<?php /** @noinspection PhpDocRedundantThrowsInspection */


namespace CatFetcher\Job;


use CatCat\Config;
use CatCat\Database;
use CatFetcher\Job\Exception\InputRequiredException;
use CatFetcher\Operation\JobReference;
use CatFetcher\Operation\Operation;
use CatFetcher\Queue\Queue;
use Closure;
use Exception;
use Generator;
use Opis\Closure\SerializableClosure;
use Throwable;

abstract class Job {
	/**
	 * label
	 *
	 * @return string
	 */
	public static function title() {
		return static::class;
	}

	public const WORKER_LIFETIME = 4;

	protected bool $is_initialized = false;
	protected bool $force_all      = false;

	public array $result = [];
	public array $errors = [];

	protected Config $config;
	protected Database $db;

	protected JobReference $reference;

	// TODO: Revert back to protected
	public array $operations = [];


	/**
	 * Job constructor.
	 *
	 * @param Config $config
	 * @param Database $database
	 * @param bool $force_all
	 */
	function __construct(Config $config, Database $database, bool $force_all) {
		$this->config = $config;
		$this->db = $database;

		$this->force_all = $force_all;
	}

	/**
	 * init
	 *
	 * @param Queue $queue
	 */
	protected function init(Queue $queue) {
		$this->reference = new JobReference($this, $queue);
	}

	/**
	 * dependencies
	 * @return Generator
	 */
	public function dependencies() {
		yield from [];
	}

	/**
	 * Yields operations.
	 * Each operation MUST be full (not php 5.4 short) closure with $job argument to use instead of $this.
	 * Correct:
	 *    yield function(MyJob $job) use ($some_variable) { return $job->someMethod($some_variable); };
	 *
	 * Incorrect:
	 *    yield function() { $this->someMethod(); };
	 *    yield fn() => $this->someMethod();
	 *
	 * This is the only way I've found to workaround `opis/closure` issue with closure losing $this and context after
	 *  subsequent serialization/unserialization.
	 *
	 * Reproducible example is:
	 *
	 *   $closure = new SerializableClosure(fn() => $this->someMethod());
	 *   echo serialize($closure); // `this` is present, everything looks fine.
	 *   echo serialize(unserialize(serialize($closure)))); // `this` is `null`, any context variables are lost.
	 *
	 * @return Generator
	 */
	abstract public function operations();


	/**
	 * initOperations
	 *
	 * @throws Exception
	 */
	protected function initOperations() {
		foreach($this->operations() as $operation) {
			$this->queueOperation($operation);
		}
	}

	/**
	 * isClosure
	 *
	 * @param $value
	 * @return bool
	 */
	protected static function isClosure($value) {
		return $value instanceof Closure || $value instanceof SerializableClosure;
	}

//	protected static function ensureOperation($value) {
//		if($value instanceof Operation) {
//			return $value;
//		}
//
//		if(static::isClosure($value) == false) {
//			return null;
//		}
//
//		if($value instanceof SerializableClosure) {
//			$value = $value->getClosure();
//		}
//
//		return new Operation($value);
//	}

	/**
	 * queueOperation
	 *
	 * @param mixed $closure SerializableClosure|Closure
	 * @param bool $immediate
	 * @throws Exception
	 */
	protected function queueOperation($closure, $immediate = false) {
		if(static::isClosure($closure) == false) {
			return;
		}

		$operation = new Operation($closure, $this->reference);
		if($immediate) {
			array_unshift($this->operations, $operation);
		} else {
			$this->operations[] = $operation;
		}
	}

	/**
	 * execute
	 *
	 * @param Queue $queue
	 * @return bool
	 * @throws Exception
	 * @throws Throwable
	 */
	public function execute(Queue $queue) : bool {
		$time_start = microtime(true);

		if($this->is_initialized == false) {
			$this->init($queue);
			$this->initOperations();

			$this->is_initialized = true;
		}

		while(count($this->operations) > 0) {
			$operation = array_shift($this->operations);

			try {
				if($this->runOperation($operation, $queue) == false) {
					if($operation->attempts-- > 0) {
						array_unshift($this->operations, $operation);
					}

					throw new Exception('Failed to execute operation, requeued');
				}
			} catch(InputRequiredException $exception) {
				array_unshift($this->operations, $operation);

				throw $exception;
			} catch(Throwable $exception) {
				$this->errors[] = $exception->getMessage();

				if(count($this->errors) > 100) {
//					dpr('Too many errors', $this->errors);
					throw $exception;
//					throw new Exception('Too many errors');
				}
			}

			$queue->logProgress($this->getCompleteCount(), $this->getEstimatedTotal(), $this->errors);

			if(microtime(true) - $time_start > static::WORKER_LIFETIME) {
				return count($this->operations) < 1; // Done if no operations left
			}
		}

		return true;
	}

	/**
	 * runOperation
	 *
	 * @param Operation $operation
	 * @param Queue $queue
	 * @return bool
	 * @throws InputRequiredException
	 * @throws Exception
	 */
	protected function runOperation(Operation $operation, Queue $queue) : bool {
		$result = $operation->execute($this);

		if(static::isClosure($result)) {
			$this->queueOperation($result, true);
			return true;
		}

		if($result instanceof Generator) {
			$is_assoc = null;

			foreach($result as $key => $value) {
				if(is_null($is_assoc)) {
					$is_assoc = ($key !== 0);
				}

				if($is_assoc) {
					$this->result[$key] = $value;
				} else {
					$this->result[] = $value;
				}
			}

			// Try to queue returned closure as operation if it is present and is closure
			$this->queueOperation($result->getReturn(), true);

			return true;
		}

		if(is_null($result)) {
			return true;
		}

		if(is_bool($result)) {
			return $result;
		}

		$this->result = $result;

		return true;
	}

	/**
	 * getEstimatedTotal
	 *
	 * @return int
	 */
	public function getEstimatedTotal() : int {
		return $this->getCompleteCount() + $this->getEstimatedLeftCount();
	}


	/**
	 * getEstimatedTotal
	 *
	 * @return int
	 */
	public function getEstimatedLeftCount() : int {
		return count($this->operations);
	}

	/**
	 * getCompleteCount
	 *
	 * @return int
	 */
	public function getCompleteCount() : int {
		return count($this->result);
	}
}