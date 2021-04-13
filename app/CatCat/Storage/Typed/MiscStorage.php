<?php


namespace CatCat\Storage\Typed;


use CatCat\Storage\JsonStorage;

class MiscStorage extends JsonStorage {
	/**
	 * getHashtags
	 *
	 * @return array
	 */
	public function getHashtags() : array {
		return (array)$this['hashtags'] ?? [];
	}

	/**
	 * setHashtags
	 *
	 * @param array $value
	 */
	public function setHashtags(array $value) {
		$this['hashtags'] = $value;
	}

	/**
	 * getAdditionalHashtags
	 *
	 * @return array
	 */
	public function getAdditionalHashtags() : array {
		return $this['additional-hashtags'] ?? [];
	}

}