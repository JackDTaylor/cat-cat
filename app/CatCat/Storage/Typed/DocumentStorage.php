<?php


namespace CatCat\Storage\Typed;


use CatCat\Storage\JsonStorage;
use CatFetcher\Model\Document;

class DocumentStorage extends JsonStorage {
	/**
	 * offsetGet
	 *
	 * @param mixed $offset
	 * @param null $default
	 * @return Document|null
	 */
	public function offsetGet($offset, $default = null) : ?Document {
		if($value = parent::offsetGet($offset, $default)) {
			$value = Document::fromObject($value);
		}

		return $value;
	}
}