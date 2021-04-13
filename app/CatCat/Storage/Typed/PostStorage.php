<?php


namespace CatCat\Storage\Typed;


use CatCat\Storage\JsonStorage;
use CatFetcher\Model\Post;

class PostStorage extends JsonStorage {
	/**
	 * offsetGet
	 *
	 * @param mixed $offset
	 * @param null $default
	 * @return Post|null
	 */
	public function offsetGet($offset, $default = null) : ?Post {
		if($offset == 'list') {
			dpr('PostStorage::offsetGet - $offset should not be "list", use getList() method');
		}

		if($result = parent::offsetGet($offset, $default)) {
			$result = Post::fromObject($result);
		}

		return $result;
	}

	/**
	 * getList
	 *
	 * @return array
	 */
	public function getList() : array {
		return parent::offsetGet('list') ?? [];
	}
}