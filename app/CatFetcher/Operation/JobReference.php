<?php


namespace CatFetcher\Operation;


use CatFetcher\Job\Job;
use CatFetcher\Queue\Queue;

class JobReference {
	public string $queue;
	public string $class;

	/**
	 * JobReference constructor.
	 *
	 * @param Job $job
	 * @param Queue $queue
	 */
	public function __construct(Job $job, Queue $queue) {
		$this->queue = $queue->id;
		$this->class = get_class($job);
	}
}