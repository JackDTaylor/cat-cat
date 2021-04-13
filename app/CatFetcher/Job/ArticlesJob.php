<?php


namespace CatFetcher\Job;


use CatFetcher\Dom\ArticleDom;
use CatFetcher\Model\Article;
use CatFetcher\Queue\Queue;
use CatFetcher\Util\Downloader;
use CatFetcher\Util\Logger;
use DiDom\Exceptions\InvalidSelectorException;
use Exception;

class ArticlesJob extends Job {
	/** @return string */
	public static function title() {
		return 'Импорт статей';
	}

	protected array $article_codes;

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

		$posts = $queue->getJobResult(PostsJob::class);

		$this->article_codes = [];

		foreach($posts as $post) {
			if(!$post->article) {
				continue;
			}

			$this->article_codes[] = $post->article;
		}
	}

	/** @inheritDoc */
	public function operations() {
		foreach($this->article_codes as $code) {
			yield function(ArticlesJob $job) use($code) {
				yield $job->processArticle($code);
			};
		}
	}

	/**
	 * processArticle
	 *
	 * @param string $code
	 * @return Article
	 *
	 * @throws InvalidSelectorException
	 * @throws Exception
	 */
	public function processArticle(string $code) : Article {
		Logger::logProgress('Fetching articles', count($this->result), count($this->article_codes));

		if(!$this->force_all && isset($this->db->articles[$code])) {
			return $this->db->articles[$code];
		}

		$parser = new ArticleDom($code, $this->fetchRawArticle($code));

		return $this->db->articles[$code] = $parser->parse();
	}

	/**
	 * fetchRawArticle
	 *
	 * @param string $code
	 * @return string
	 * @throws Exception
	 */
	protected function fetchRawArticle(string $code) : string {
		if($this->db->articles_raw[$code] ?? null) {
			return $this->db->articles_raw[$code];
		}

		$article = Downloader::downloadFile("https://m.vk.com/@{$code}", 5, function($content) use($code) {
			if(!$content || !preg_match('#<div class="articleView__content_list">#', $content)) {
				throw new Exception("Unable to find article body");
			}

			return $content;
		}, true);

		return $this->db->articles_raw[$code] = $article;
	}
}