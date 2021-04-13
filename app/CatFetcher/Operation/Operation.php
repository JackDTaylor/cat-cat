<?php


namespace CatFetcher\Operation;

use CatFetcher\Job\Job;
use CatFetcher\Queue\Queue;
use Closure;
use Exception;
use Opis\Closure\SecurityException;
use Opis\Closure\SerializableClosure;

class Operation extends SerializableClosure {
	public int $attempts = 3;

		/**
	 * Operation constructor.
	 *
	 * @param $closure
	 * @param JobReference $reference
	 * @throws Exception
	 */
	public function __construct($closure, JobReference $reference) {
		if($closure instanceof SerializableClosure) {
			$closure = $closure->getClosure();
		}

		if($closure instanceof Closure == false) {
			throw new Exception('Operation can only be constructed with Closure or SerializableClosure');
		}

		$closure = $closure->bindTo($reference);

		parent::__construct($closure);
	}

	/**
	 * execute
	 *
	 * @param Job $job
	 * @param Queue $queue
	 * @return mixed
	 * @throws Exception
	 */
	public function execute(Job $job) {
		$closure = $this->getClosure();
		$closure = $closure->bindTo($job);

		return $closure($job);
	}

	/**
	 * serialize
	 *
	 * @return string
	 */
	public function serialize() {
		return parent::serialize();
	}

	/**
	 * unserialize
	 *
	 * @param string $data
	 * @throws SecurityException
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function unserialize($data) {
		parent::unserialize($data);
	}
}
