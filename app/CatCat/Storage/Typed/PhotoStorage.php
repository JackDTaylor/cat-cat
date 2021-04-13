<?php


namespace CatCat\Storage\Typed;


use CatCat\Storage\JsonStorage;
use CatFetcher\Model\Photo;

class PhotoStorage extends JsonStorage {
	/**
	 * offsetGet
	 *
	 * @param mixed $offset
	 * @param null $default
	 * @return Photo|null
	 */
	public function offsetGet($offset, $default = null) : ?Photo {
		if($result = parent::offsetGet($offset, $default)) {
			$result = Photo::fromObject($result);
		}

		return $result;
	}
}