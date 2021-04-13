<?php


namespace CatFrontend\Controller;

use CatFetcher\Fetcher;
use CatFetcher\Job\Exception\InputRequiredException;
use CatFetcher\Job\Job;
use CatFetcher\Reference\Hashtag;
use CatFrontend\View;
use Exception;
use ReflectionException;
use Throwable;

class ImportController extends Controller {
	/**
	 * @param array $query
	 * @return mixed
	 * @throws Exception
	 * @throws Throwable
	 * @noinspection PhpMethodParametersCountMismatchInspection
	 */
	public function route(array $query) {
		$query = array_slice($query, 1);

		$action = $query[0] ?? 'index';

		$args = array_slice($query, 1);

		switch($action) {
			case 'run':    return $this->runAction(...$args);
			case 'worker': return $this->workerAction(...$args);
			case 'result': return $this->resultAction(...$args);
			case 'status': return $this->statusAction(...$args);
			case 'config': return $this->configAction(...$args);
			case 'input':  return $this->inputAction(...$args);
			case 'do':     return $this->doAction(...$args);

			case 'index': return $this->indexAction(...$args);
		}

		throw new Exception('Action not found');
	}

	/**
	 * run
	 *
	 * @param $config
	 * @return string
	 * @throws ReflectionException
	 * @throws Exception
	 */
	protected function run($config) {
		$fetcher = new Fetcher;

		return $fetcher->run($config);
	}

	/**
	 * getQueueFrontendUrl
	 *
	 * @param $queue_id
	 * @return string
	 */
	protected function getQueueFrontendUrl($queue_id) {
		return $this->url("/@import/do/{$queue_id}");
	}

	/**
	 * getQueueWorkerUrl
	 *
	 * @param $queue_id
	 * @return string
	 */
	protected function getQueueWorkerUrl($queue_id) {
		return $this->url("/@import/worker/{$queue_id}");
	}

	/**
	 * getQueueStatusUrl
	 *
	 * @param $queue_id
	 * @return string
	 */
	protected function getQueueStatusUrl($queue_id) {
		return $this->url("/@import/status/{$queue_id}");
	}

	/**
	 * runAction
	 *
	 * @return array
	 * @throws ReflectionException
	 */
	protected function runAction() {
		$config = $_GET['config'] ?? null;
		$queue_id = $this->run($config);

		return [
			'success' => true,
			'queue' => $queue_id,
			'proceed_url' => $this->getQueueWorkerUrl($queue_id),
			'status_url' => $this->getQueueStatusUrl($queue_id),
		];
	}

	/**
	 * workerAction
	 *
	 * @param mixed $queue_id
	 * @return array
	 * @throws ReflectionException
	 * @throws Exception
	 * @throws Throwable
	 */
	protected function workerAction($queue_id = null) {
		if(!$queue_id) {
			throw new Exception('No $queue_id provided');
		}

		$fetcher = new Fetcher;

		$input = null;
		$proceed_url = $this->url("/@import/worker/{$queue_id}");

		try {
			if($done = $fetcher->resume($queue_id)) {
				$proceed_url = false;
			}
		} catch(InputRequiredException $exception) {
			$input = [
				'url' => $this->url("/@import/input/{$queue_id}/{$exception->input_code}")
			];
		}

		return [
			'success' => true,
			'queue' => $queue_id,
			'input_required' => $input,
			'proceed_url' => $proceed_url,
		];
	}

	/**
	 * statusAction
	 *
	 * @param null $id
	 * @return mixed
	 * @throws ReflectionException
	 * @throws Exception
	 */
	protected function statusAction($id) {
		if(!$id) {
			throw new Exception('No id provided');
		}

		$fetcher = new Fetcher;
		$status = $fetcher->getStatus($id);

		/** @var Job $Job */
		$Job = $status->class ?? null;
		$title = null;

		if($Job) {
			$title = $Job::title();
		}

		return [
			'success' => true,
			'title' => $title ?: 'Импорт',
			'status' => $status,
		];
	}

	/**
	 * resultAction
	 *
	 * @param null $id
	 * @throws ReflectionException
	 * @throws Exception
	 */
	protected function resultAction($id = null) {
		if(!$id) {
			throw new Exception('No id provided');
		}

		$fetcher = new Fetcher;
		$results = $fetcher->getActiveJobResults($id);

		dpr($results);
	}

	/**
	 * indexAction
	 *
	 * @return mixed
	 */
	protected function indexAction() {
		return $this->redirect('/@import/config');
	}

	/**
	 * configAction
	 *
	 * @return View
	 * @throws ReflectionException
	 */
	protected function configAction() {
		if($this->isPost()) {
			$config = [];

			foreach($this->post('jobs', []) as $job_id => $value) {
				if(!isset(Fetcher::$jobs[$job_id])) {
					continue;
				}

				$enabled = (bool)($value['enabled'] ?? false);
				$force_all = (bool)($value['force_all'] ?? false);

				if(!$enabled) {
					continue;
				}

				$config[] = $force_all ? "{$job_id}:all" : $job_id;
			}

			$config = implode(',', $config);

			$queue_id = $this->run($config);

			return $this->redirect($this->getQueueFrontendUrl($queue_id));
		}

		return $this->view('@import/config', [
			'back_url' => $this->getUrlBuilder()->getHomepageUrl(),
		])->setPageTitle('Новый импорт данных');
	}

	/**
	 * doAction
	 *
	 * @param string $queue_id
	 * @return mixed
	 * @throws Exception
	 */
	protected function doAction(string $queue_id) {
		if(!$queue_id) {
			throw new Exception('No queue_id provided');
		}

		return $this->view('@import/do', [
			'queue_id' => $queue_id,
			'back_url' => $this->url('/@import/config'),
		])->setPageTitle("Импорт данных #{$queue_id}");
	}

	/**
	 * inputAction
	 *
	 * @param string $queue_id
	 * @param string $code
	 * @return array|View
	 */
	protected function inputAction(string $queue_id, string $code) {
		$import_url = $this->url("/@import/do/{$queue_id}");

		if($this->isPost()) {
			$hashtags = $this->post('hashtags');
			$hashtags = array_map(fn($x) => $x ? Hashtag::AUTHOR : Hashtag::OTHER, $hashtags);
			$hashtags = array_replace_recursive($this->db->misc->getHashtags(), $hashtags);

			$this->db->misc->setHashtags($hashtags);

			return $this->redirect($import_url);
		}

		$view_data = [];
		if($code == 'unknown-hashtags') {
			$view_data['hashtags'] = $this->db->misc['unknown-hashtags'];
		}

		return $this->view("@import/input/{$code}", array_replace_recursive($view_data, [
			'queue_id' => $queue_id,
			'code' => $code,
			'back_url' => $import_url,
			'back_title' => 'Вернуться без сохранения',
		]))->setPageTitle("Заполнение недостающих данных по импорту #{$queue_id}");
	}
}