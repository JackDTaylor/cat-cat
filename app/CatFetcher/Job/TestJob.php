<?php


namespace CatFetcher\Job;


use CatFetcher\Queue\Queue;
use Generator;

class TestJob extends Job {
	protected array $wall;

	/** @return string */
	public static function title() {
		return 'Тестовый импорт';
	}

	public const OPS_COUNT = 300;
	public const WORKER_LIFETIME = 10;

	/**
	 * getEstimatedTotal
	 *
	 * @return int
	 */
	public function getEstimatedTotal(): int {
		return static::OPS_COUNT;
	}

	/**
	 * init
	 *
	 * @param Queue $queue
	 */
	protected function init(Queue $queue) {
		parent::init($queue);

		$this->wall = (array)$queue->getJobResult(WallJob::class);
	}

	/**
	 * dependencies
	 *
	 * @return Generator
	 */
	public function dependencies() {
		yield WallJob::class;
	}

	/**
	 * @inheritDoc
	 * @return Generator
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function operations() {
		yield function(Job $job) {
			dpr('Test');
		};
	}
}