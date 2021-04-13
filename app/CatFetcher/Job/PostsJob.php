<?php


namespace CatFetcher\Job;


use CatFetcher\Job\Exception\InputRequiredException;
use CatFetcher\Model\Document;
use CatFetcher\Model\Photo;
use CatFetcher\Model\Post;
use CatFetcher\Queue\Queue;
use CatFetcher\Reference\Hashtag;
use CatFetcher\Util\Logger;
use Exception;
use Generator;

class PostsJob extends Job {
	/** @return string */
	public static function title() {
		return 'Обработка постов';
	}

	protected array $original_hashtags = [];

	// Post warnings
	protected array $no_hashtags = [];
	protected array $unknown_attachment_types = [];
	protected array $duplicate_articles = [];

	// Video stats
	protected array $local_videos = [];
	protected array $video_platforms = [];

	// Additional data
	protected array $additional_hashtags;
	protected array $hashtags;
	protected array $post_hashtags;

	protected array $posts;

	protected array $debug_post_keys = [
//		'-162479647_207180',
	];

	/** @inheritDoc */
	public function dependencies() {
		yield WallJob::class;
	}

	/**
	 * init
	 *
	 * @param Queue $queue
	 * @throws Exception
	 */
	protected function init(Queue $queue) {
		parent::init($queue);

		$this->additional_hashtags = $this->prepareAdditionalHashtags();
		$this->hashtags = $this->db->misc->getHashtags();

		$this->posts = (array)$queue->getJobResult(WallJob::class);
	}

	/**
	 * @return Generator
	 */
	public function operations() {
		yield function(PostsJob $job) { return $job->parseHashtags(); };
		yield function(PostsJob $job) { return $job->checkUnknownHashtags(); };

		foreach($this->posts as $post_key => $post) {
			yield function(PostsJob $job) use ($post_key) {
				yield $post_key => $job->processPost($post_key);
			};
		}
	}

	/**
	 * prepareAdditionalHashtags
	 *
	 * @return array
	 */
	protected function prepareAdditionalHashtags() : array {
		$data = $this->db->misc->getAdditionalHashtags();
		$result = [];

		foreach($data as $entry) {
			$post_id = $entry->post_id ?? null;
			$hashtag = $entry->hashtag ?? null;

			$hashtag = Hashtag::uniform($hashtag);

			if(!$post_id || !$hashtag) {
				continue;
			}

			$result[$post_id] = $result[$post_id] ?? [];
			$result[$post_id][] = $hashtag;
		}

		return $result;
	}

	/**
	 * parseHashtags
	 */
	protected function parseHashtags() {
		$this->post_hashtags = [];

		foreach($this->posts as $post_key => $post) {
			$hashtags = Hashtag::parseFromString($post->text);
			$hashtags = array_unique(array_merge($hashtags, $this->additional_hashtags[$post_key] ?? []));

			$this->post_hashtags[ $post_key ] = $hashtags;
		}
	}

	/**
	 * checkUnknownHashtags
	 *
	 * @throws InputRequiredException
	 */
	protected function checkUnknownHashtags() {
		$this->hashtags = $this->db->misc->getHashtags();

		$unknown_hashtags = [];

		foreach($this->post_hashtags as $post_key => $hashtags) {
			foreach($hashtags as $hashtag) {
				if(isset($this->hashtags[ $hashtag ])) {
					continue;
				}

				$unknown_hashtags[ $hashtag ] = true;
			}
		}

		if(count($unknown_hashtags)) {
			$this->db->misc['unknown-hashtags'] = array_keys($unknown_hashtags);

			throw new InputRequiredException('unknown-hashtags');
		}
	}

	/**
	 * @param string $post_key
	 * @param object $post
	 * @param bool $store_in_db
	 * @return Post
	 * @throws Exception
	 */
	protected function processPost(string $post_key, object $post = null, $store_in_db = true) : Post {
		$debug = in_array($post_key, $this->debug_post_keys);

		if(!$debug && !$this->force_all && isset($this->db->wall[ $post_key ])) {
			return $this->db->wall[ $post_key ];
		}

		if(is_null($post)) {
			$post = $this->posts[ $post_key ] ?? null;
		}

		$hashtags = $this->post_hashtags[ $post_key ];

		if(!$hashtags) {
			$this->no_hashtags[] = $post_key;
		}

		$model = new Post($post_key);

		$this->processPostData($model, $post);
		$this->processPostHashtags($model, $hashtags);
		$this->processPostAttachments($model, $post->attachments ?? []);
		$this->processPostRepost($model, $post->copy_history ?? []);

		$model->stats = (object)[
			'likes'    => $post->likes    ?? null,
			'reposts'  => $post->reposts  ?? null,
			'comments' => $post->comments ?? null,
		];
		if($store_in_db) {
			$this->db->wall[$post_key] = $model;
		}

		if($debug) {
			dpr($store_in_db, $model);
		}

		return $model;
	}

	/**
	 * processPostData
	 *
	 * @param Post $model
	 * @param object $post
	 */
	protected function processPostData(Post $model, object $post) {
		$model->date = $post->date;
		$model->text = $post->text;
		$model->signer = $post->signer_id ?? null;
	}

	/**
	 * @param Post $model
	 * @param array $hashtags
	 */
	protected function processPostHashtags(Post $model, array $hashtags) {
		$model->authors = [];
		$model->hashtags = [];

		foreach($hashtags as $hashtag) {
			if($this->hashtags[ $hashtag ] == Hashtag::AUTHOR) {
				$model->authors[] = $hashtag;
				continue;
			}

			$model->hashtags[] = $hashtag;
		}
	}

	/**
	 * @param Post $model
	 * @param array $attachments
	 * @throws Exception
	 */
	protected function processPostAttachments(Post $model, array $attachments) {
		$model->photos = [];
		$model->documents = [];
		$model->attachments = [];

		$model->article = null;

		foreach($attachments as $attachment) {
			$this->processPostAttachment($model, $attachment);
		}

		if($model->article) {
			[,$model->article] = explode('@', $model->article, 2);
		}
	}

	/**
	 * @param Post $model
	 * @param object $attachment
	 * @throws Exception
	 */
	protected function processPostAttachment(Post $model, object $attachment) {
		if($attachment->type == 'photo') {
			$photo = $attachment->photo;

			$photo_id = "{$photo->owner_id}_{$photo->id}";
			$photo_url = (
				$photo->photo_1280 ??
				$photo->photo_807 ??
				$photo->photo_604 ??
				$photo->photo_130 ??
				$photo->photo_75 ??
				null
			);

			if(!$photo_url) {
				throw new Exception('No image url: ' . Logger::logValue($attachment));
			}

			if(!preg_match('#^https://sun\d+-\d+\.userapi\.com/#', $photo_url)) {
				throw new Exception('Malformed photo url: ' . Logger::logValue($photo));
			}

			$photo = new Photo($photo_url, $photo->width, $photo->height, $photo->text ?? '');
			$photo->id = $photo_id;

			$model->photos[] = $photo->local_url;

			return;
		}

		if($attachment->type == 'doc') {
			$document = $attachment->doc;
			$document_id = "{$document->owner_id}_{$document->id}";

			$document = new Document($document->url, $document->ext, $document->title, $document->date);
			$document->id = $document_id;

			$model->documents[] = $document->local_url;
			return;
		}

		if($attachment->type == 'link') {
			$url = $attachment->link->url;

			if(!preg_match_all('#//(m\.)?vk\.com/@#', $url)) {
				return;
			}

			$url = preg_replace('#^https?://(m\.)?vk\.com/#', 'https://m.vk.com/', $url);

			if($model->article && $model->article != $url) {
				$this->duplicate_articles[$model->article] = $this->duplicate_articles[$model->article] ?? [];
				$this->duplicate_articles[$model->article][] = $url;

				return;
			}

			$model->article = $url;
			return;
		}

		if($attachment->type == 'video') {
			$video_platform = $attachment->video->platform ?? null;

			if($video_platform) {
				$this->video_platforms[ $video_platform ] = $this->video_platforms[ $video_platform ] ?? 0;
				$this->video_platforms[ $video_platform ]++;
			} else {
				$this->local_videos[] = "https://vk.com/video{$attachment->video->owner_id}_{$attachment->video->id}";
			}

			$model->attachments[] = $attachment;
			return;
		}

		$model->attachments[] = $attachment;

		$this->unknown_attachment_types[$attachment->type] = $this->unknown_attachment_types[$attachment->type] ?? 0;
		$this->unknown_attachment_types[$attachment->type]++;
	}

	/**
	 * @param Post $model
	 * @param object[] $reposts
	 * @throws Exception
	 */
	protected function processPostRepost(Post $model, array $reposts) {
		$model->repost = null;

		if(!$reposts) {
			return;
		}

		if($this->config->with_reposts) {
			while(count($reposts) > 1) {
				$popped_repost = array_pop($reposts);

				$last_repost = $reposts[ array_key_last($reposts) ];
				$last_repost->copy_history = [ $popped_repost ];
				$reposts[ array_key_last($reposts) ] = $last_repost;
			}
		}

		/** @var object $repost */
		$repost = pos($reposts);

		$repost_owner = $repost->owner_id ?? null;
		$repost_id    = $repost->id ?? null;

		if(!$repost_owner || !$repost_id) {
			throw new Exception("Repost has no owner or id ({$model->id})");
		}

		$repost_key = "{$repost_owner}_{$repost_id}";

		if($this->config->with_reposts) {
			$model->repost = $this->processPost($repost_key, $repost, false);
		} else {
			$model->repost = $repost_key;
		}
	}
}