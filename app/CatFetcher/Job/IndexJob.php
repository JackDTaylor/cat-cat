<?php

namespace CatFetcher\Job;

use CatFetcher\Model\Post;
use CatFetcher\Queue\Queue;
use CatFetcher\Reference\Hashtag;
use Generator;

class IndexJob extends Job {
	/** @return string */
	public static function title() {
		return 'Индексация хэштегов';
	}

	/** @var Post[] */
	protected array $posts;

	/** @inheritDoc */
	public function dependencies() {
		yield PostsJob::class;
	}

	/**
	 * init
	 *
	 * @param Queue $queue
	 */
	protected function init(Queue $queue) {
		parent::init($queue);

		$this->posts = (array)$queue->getJobResult(PostsJob::class);
	}

	/**
	 * operations
	 *
	 * @return Generator
	 */
	public function operations() {
		yield function(IndexJob $job) { return $job->buildIndex(); };
		yield function(IndexJob $job) { return $job->storeResult(); };
	}

	/**
	 * buildIndex
	 *
	 * @return array
	 */
	protected function buildIndex() {
		$result = [];
		$no_author = Hashtag::NO_AUTHOR;

		foreach($this->posts as $post_key => $post) {
			$tags = [
				'hashtags' => $post->hashtags ?: [],
				'authors'  => $post->authors ?: [$no_author],
			];

			foreach($tags as $type => $items) {
				$result[$type] = $result[$type] ?? [];

				foreach($items as $tag) {
					$result[$type][$tag] = $result[$type][$tag] ?? [];
					$result[$type][$tag][] = $post_key;
				}
			}
		}

		foreach($result as $type => $items) {
			foreach($items as $key => $post_keys) {
				$this->db->index["{$type}/{$key}"] = $post_keys;
			}
		}

		return $result;
	}

	protected function storeResult() {
		foreach($this->result as $type => $items) {
			$this->db->index[$type] = array_keys($items);
		}
	}
}