<?php


namespace CatFetcher\Job;


use CatFetcher\Queue\Queue;
use CatFetcher\Traits\VKApiSupport;
use CatFetcher\Util\Logger;
use Closure;
use Exception;
use Generator;

class MembersJob extends Job {
	/** @return string */
	public static function title() {
		return 'Импорт участников сообщества';
	}

	use VKApiSupport;

	protected const CHUNK_SIZE = 1000;

	protected int $total;

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
	 */
	protected function init(Queue $queue) {
		parent::init($queue);
	}

	/**
	 * operations
	 *
	 * @return Generator
	 */
	public function operations() {
		yield function(MembersJob $job) { return $job->fetchChunk(0); };
		yield function(MembersJob $job) { return $job->db->misc['members'] = $job->result; };
	}

	/**
	 * fetchChunk
	 *
	 * @param $offset
	 * @return Generator|Closure
	 * @throws Exception
	 */
	protected function fetchChunk($offset) {
		$chunk = $this->fetchVkApiResponse('groups.getMembers', [
			'group_id' => $this->config->owner_id,
			'offset' => $offset,
			'count' => static::CHUNK_SIZE,
			'fields' => 'sex,bdate,city,country,photo_200,last_seen',
		]);

		$this->total = $this->total ?? $chunk->response->count;

		foreach($chunk->response->items as $item) {
			yield $item;
		}

		Logger::logProgress('Fetching members', $offset, $this->total);

		$offset += static::CHUNK_SIZE;

		if($offset >= $this->total) {
			return null;
		}

		return  function(MembersJob $job) use($offset) { return $job->fetchChunk($offset); };
	}
}