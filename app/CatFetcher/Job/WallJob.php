<?php


namespace CatFetcher\Job;


use CatFetcher\Queue\Queue;
use CatFetcher\Traits\VKApiSupport;
use CatFetcher\Util\Logger;
use Closure;
use Exception;
use Generator;

class WallJob extends Job {
	/** @return string */
	public static function title() {
		return 'Импорт записей на стене';
	}

	public const CHUNK_SIZE = 100;

	protected array $existing_posts;
	protected int $total;

	use VKApiSupport;

	/**
	 * getVkApiAccessToken
	 *
	 * @return string
	 */
	function getVkApiAccessToken() {
		return $this->config->access_token;
	}

	/**
	 * init
	 *
	 * @param Queue $queue
	 * @throws Exception
	 */
	protected function init(Queue $queue) {
		parent::init($queue);

		$this->total = $this->fetchWallCount();
		$this->existing_posts = $this->force_all ? [] : $this->db->wall->getList();
	}

	/**
	 * @inheritDoc
	 */
	public function operations() {
		yield function(WallJob $job) { return $job->importChunk(0); };
		yield function(WallJob $job) { return $job->processExistingPosts(); };
	}

	/**
	 * getCompleteCount
	 *
	 * @return int
	 */
	public function getCompleteCount(): int {
		return count($this->existing_posts);
	}

	/**
	 * getEstimatedLeftCount
	 *
	 * @return int
	 */
	public function getEstimatedLeftCount(): int {
		return $this->getEstimatedTotal() - $this->getCompleteCount();
	}

	/**
	 * getEstimatedTotal
	 *
	 * @return int
	 */
	public function getEstimatedTotal(): int {
		return $this->total;
	}

	/**
	 * @param int $offset
	 *
	 * @return Closure
	 * @throws Exception
	 */
	protected function importChunk(int $offset) {
		Logger::logProgress('Fetching offset', $offset, $this->total);

		$chunk = $this->fetchWallOffset($offset);

		foreach($chunk->response->items as $item) {
			$item_key = "{$item->owner_id}_{$item->id}";

			$is_pinned = $item->is_pinned ?? false;
			$is_existing = in_array($item_key, $this->existing_posts);

			if(!$this->force_all && !$is_pinned && $is_existing) {
				// Found already known non-pinned post, stop fetching
				return null;
			}

			$this->db->wall_raw[ $item_key ] = $item;

			$this->existing_posts[] = $item_key;
		}

		$this->db->wall['list'] = array_values(array_unique($this->existing_posts));

		$offset += static::CHUNK_SIZE;

		if($offset >= $this->total) {
			return null;
		}

		return function(WallJob $job) use($offset) {
			return $job->importChunk($offset);
		};
	}

	/**
	 * processExistingPosts
	 *
	 * @return Generator
	 */
	protected function processExistingPosts() {
		foreach($this->existing_posts as $post_id) {
			yield $post_id => $this->db->wall_raw[ $post_id ];
		}
	}

	/**
	 * @throws Exception
	 */
	protected function fetchWallCount() {
		$response = $this->fetchVkApiResponse('wall.get', [
			'access_token' => $this->config->access_token,
			'owner_id'     => $this->config->owner_id,
			'count'        => 1
		]);

		return $response->response->count;
	}

	/**
	 * fetchWallOffset
	 *
	 * @param int $offset
	 * @return object
	 * @throws Exception
	 */
	protected function fetchWallOffset(int $offset) : object {
		return $this->fetchVkApiResponse('wall.get', [
			'access_token' => $this->config->access_token,
			'owner_id'     => $this->config->owner_id,
			'count'        => static::CHUNK_SIZE,
			'offset'       => $offset,
			'extended'     => 1
		]);
	}
}