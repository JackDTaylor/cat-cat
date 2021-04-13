<?php


namespace CatCat\Storage\Typed;


use CatCat\Storage\JsonStorage;
use CatFetcher\Model\Article;

class ArticleStorage extends JsonStorage {
	/**
	 * offsetGet
	 *
	 * @param mixed $offset
	 * @param null $default
	 * @return Article|null
	 */
	public function offsetGet($offset, $default = null) : ?Article {
		if($result = parent::offsetGet($offset, $default)) {
			$result = Article::fromObject($result);
		}

		return $result;
	}
}