<?php


namespace CatFrontend\Controller;


use CatFetcher\Model\Post;
use CatFetcher\Reference\Hashtag;

class SiteController extends Controller {
	/**
	 * @param array $query
	 * @return mixed
	 */
	public function route(array $query) {
		$author_id = $query[0] ?: null;
		$post_id = $query[1] ?? null;

		if($author_id && $post_id) {
			return $this->postAction($author_id, $post_id);
		}

		if($author_id) {
			return $this->postsAction($author_id, $this->query('page', 1));
		}

		return $this->hashtagsAction();
	}

	/**
	 * listAction
	 *
	 * @return false|string
	 */
	protected function hashtagsAction() {
		$types_config = [
			'authors' => 'Авторы',
			'hashtags' => 'Хэштеги'
		];
		$types = [];
		$type_counts = [];

		foreach($types_config as $type => $label) {
			$tags = $this->db->index[$type];

			$type_counts[$type] = [];

			foreach($tags as $tag) {
				$type_counts[$type][$tag] = count($this->db->index["{$type}/{$tag}"]);
			}

			asort($type_counts[$type]);
			$type_counts[$type] = array_reverse($type_counts[$type]);

			$types[$type] = [];

			foreach($type_counts[$type] as $tag => $count) {
				$types[$type][] = (object)[
					'hashtag' => $tag,
					'url'     => Hashtag::toUrl($tag),
					'count'   => $count,
				];
			}
		}

		return $this->view('hashtags', [
			'back_url' => null,
			'types'    => $types_config,
			'authors'  => $types['authors'],
			'hashtags' => $types['hashtags'],
			'total_count' => array_sum($type_counts['authors']),
		]);
	}

	/**
	 * authorAction
	 *
	 * @param $hashtag
	 * @param int $page
	 * @return false|string
	 */
	protected function postsAction($hashtag, $page = 1) {
		$page = (int)$page;
		$posts = $this->db->index["authors/#{$hashtag}@catx2"] ?? $this->db->index["hashtags/#{$hashtag}@catx2"];

		if($page > 0) {
			$posts = array_slice($posts, ($page - 1) * 100, 100);
		}

		if(!$posts) {
			return $this->view('error', [
				'back_url' => true,
				'message' => 'Ничего не найдено'
			]);
		}

		/** @var Post[] $posts */
		$posts = array_map(fn($x) => $this->db->wall[$x], $posts);
		$articles = [];

		foreach($posts as $post) {
			if($post->article) {
				$articles[ $post->article ] = $this->db->articles[ $post->article ];
			}
		}

		usort($posts, fn($a, $b) => $b->date <=> $a->date);

		return $this->view('posts', [
			'back_url'    => $this->getUrlBuilder()->getHomepageUrl(),
			'posts'       => $posts,
			'articles'    => $articles,
		]);
	}

	/**
	 * postAction
	 *
	 * @param $hashtag
	 * @param $post
	 * @return false|string
	 */
	protected function postAction($hashtag, $post) {
		$post = $this->db->wall[$post] ?? null;
		$article = null;

		if($post->article) {
			$article = $this->db->articles[ $post->article ];
		}

		$post->photos    = array_map(fn($x) => $this->db->photos[$x], $post->photos);
		$post->documents = array_map(fn($x) => $this->db->documents[$x], $post->documents);

		return $this->view('post', [
			'back_url' => $this->getUrlBuilder()->getHashtagUrl($hashtag),
			'post' => $post,
			'article' => $article,
		]);
	}
}