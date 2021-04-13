<?php
namespace CatFetcher;

use CatCat\Application;
use CatFetcher\Job\ArticlesJob;
use CatFetcher\Job\IndexJob;
use CatFetcher\Job\DocumentsJob;
use CatFetcher\Job\Exception\InputRequiredException;
use CatFetcher\Job\MembersJob;
use CatFetcher\Job\PhotosJob;
use CatFetcher\Job\PostsJob;
use CatFetcher\Job\TestJob;
use CatFetcher\Job\WallJob;
use CatFetcher\Queue\Queue;
use Exception;
use Serializable;
use Throwable;

class Fetcher extends Application implements Serializable {
	public static array $jobs = [
		'wall'      => WallJob::class,
		'posts'     => PostsJob::class,
		'articles'  => ArticlesJob::class,
		'documents' => DocumentsJob::class,
		'photos'    => PhotosJob::class,
		'index'     => IndexJob::class,
		'members'   => MembersJob::class,
		'test'      => TestJob::class,
	];

	protected Queue $queue;

	/**
	 * @param null $config
	 * @return string
	 * @throws Exception
	 */
	public function run($config = null) : string {
		if(!$config) {
			throw new Exception('Config is required');
		}

		$queue = new Queue($this->config, $this->db, $config);

		$this->saveQueue($queue);

		return $queue->id;
	}

	/**
	 * resume
	 *
	 * @param $id
	 * @return bool
	 * @throws Exception
	 * @throws Throwable
	 * @throws InputRequiredException
	 */
	public function resume(string $id) : bool {
		$queue = $this->loadQueue($id);

		if(!$queue) {
			throw new Exception("Queue '{$id}' not found");
		}

		try {
			if($done = $queue->execute()) {
				return true;
			}

			$this->saveQueue($queue);
			return false;
		} catch(InputRequiredException $exception) {
			$this->saveQueue($queue);
			throw $exception;
		}
	}

	/**
	 * loadQueue
	 *
	 * @param string $id
	 *
	 * @return Queue
	 */
	protected function loadQueue(string $id) : ?Queue {
		$id = preg_replace('#[^a-z0-9]#', '', $id);

		/** @var Queue $queue */
		$queue = $this->db->queue[$id] ?? null;

		if($queue) {
			$queue->onAfterLoad();
		}

		return $queue;
	}

	/**
	 * saveQueue
	 *
	 * @param Queue $queue
	 */
	protected function saveQueue(Queue $queue) : void {
		$queue->onBeforeSave();
		$this->db->queue[$queue->id] = $queue;
	}

	/**
	 * getStatus
	 *
	 * @param string $id
	 * @return object|null
	 */
	public function getStatus(string $id) : ?object {
		return $this->db->queue["status/{$id}"] ?? null;
	}

	/**
	 * getResults
	 *
	 * @param string $id
	 * @return array
	 */
	public function getActiveJobResults(string $id) {
		$queue = $this->loadQueue($id);
		$job = $queue->getActiveJob();

		return $job->result;
	}

	/**
	 * Serialization is disabled because $queue property is unnecessarily heavy to be serialized
	 * @inheritDoc
	 * @throws Exception
	 */
	public function serialize() {
		throw new Exception('Trying to serialize Application class, this operation is not supported.');
	}

	/** @inheritDoc */
	public function unserialize($serialized) {}
}