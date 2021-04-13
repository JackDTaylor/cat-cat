<?php


namespace CatFetcher\Queue;


use CatCat\Config;
use CatCat\Database;
use CatFetcher\Fetcher;
use CatFetcher\Job\Job;
use CatFetcher\Model\StoredModel;
use Throwable;

class Queue {
	public string $id;

	protected Config $config;
	protected Database $db;

	protected array $jobs = [];
	protected array $queue = [];
	protected ?Job $active_job = null;

	protected array $errors = [];
	protected array $results = [];

	protected ?int $time_start = null;

	protected string $stored_model_repository;


	/**
	 * Queue constructor.
	 *
	 * @param Config $config
	 * @param Database $database
	 * @param string $job_config
	 */
	public function __construct(Config $config, Database $database, string $job_config) {
		$this->id = uniqid();

		$this->config = $config;
		$this->db = $database;


		$this->jobs = array_filter(array_map('trim', explode(',', $job_config)));

		$this->init();
	}

	protected function init() {
		foreach($this->jobs as $job) {
			[$code, $force_all] = array_pad(explode(':', $job, 2), 2, null);
			$force_all = $force_all == 'all';

			$this->queueJob(Fetcher::$jobs[ $code ], $force_all);
		}
	}

	/**
	 * queueJob
	 *
	 * @param string $Job
	 * @param bool $force_all
	 */
	protected function queueJob(string $Job, bool $force_all) {
		/** @var Job $job */
		$job = new $Job($this->config, $this->db, $force_all);

		foreach($job->dependencies() as $DependencyJob) {
			$this->queueJob($DependencyJob, $force_all);
		}

		// Queue if not already in queue
		$this->queue[$Job] = $this->queue[$Job] ?? $job;
	}

	/**
	 * execute
	 *
	 * @param bool $reset_timestart
	 * @return bool
	 * @throws Throwable
	 */
	public function execute($reset_timestart = true) {
		if($reset_timestart || is_null($this->time_start)) {
			$this->time_start = time();
		}

		if(!$this->active_job) {
			if(count($this->queue) < 1) {
				return true;
			}

			$this->active_job = array_shift($this->queue);
		}

		/** @var Job|string $ActiveJob */
		$ActiveJob = get_class($this->active_job);

		if(time() - $this->time_start > $ActiveJob::WORKER_LIFETIME) {
			return false;
		}

		if($done = $this->active_job->execute($this)) {
			$this->errors [$ActiveJob] = $this->active_job->errors;
			$this->results[$ActiveJob] = $this->active_job->result;

			$this->active_job = null;

			return $this->execute(false);
		}

		return $done;
	}

	/**
	 * getJobData
	 *
	 * @param string|Job $Job
	 * @return mixed
	 */
	public function getJobResult(string $Job) {
		return $this->results[ $Job ] ?? null;
	}

	/**
	 * getResults
	 *
	 * @return array
	 */
	public function getResults() {
		return $this->results;
	}

	/**
	 * getActiveJob
	 *
	 * @return Job|null
	 */
	public function getActiveJob() : ?Job {
		return $this->active_job;
	}

	/**
	 * logProgress
	 *
	 * @param int $finished_count
	 * @param int $estimated_total
	 * @param array $errors
	 */
	public function logProgress(int $finished_count, int $estimated_total, array $errors = []) {
		$this->db->queue["status/{$this->id}"] = (object)[
			'class' => get_class($this->active_job) ?: null,
			'done' => $finished_count,
			'total' => $estimated_total,
			'errors' => $errors,
		];
	}

	public function onBeforeSave() {
		$this->stored_model_repository = StoredModel::serializeRepository();
	}

	public function onAfterLoad() {
		if($this->stored_model_repository) {
			StoredModel::unserializeRepository($this->stored_model_repository);
		}
	}
}