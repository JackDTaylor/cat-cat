<?php
namespace CatCat\Storage;


use Exception;

class JsonStorage extends Storage {
	/** @inheritDoc */
	protected function getExtension($key) {
		return '.json';
	}

	/**
	 * offsetGet
	 *
	 * @param mixed $offset
	 * @param null $default
	 * @return false|mixed|string|null
	 */
	public function offsetGet($offset, $default = null) {
		return json_decode(parent::offsetGet($offset, $default));
	}

	/**
	 * offsetSet
	 *
	 * @param mixed $offset
	 * @param mixed $value
	 * @return false|int|void
	 * @throws Exception
	 */
	public function offsetSet($offset, $value) {
		$value = json_encode($value);

		return parent::offsetSet($offset, $value);
	}
}