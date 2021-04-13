<?php
namespace CatCat\Storage;


use ArrayAccess;
use Exception;

abstract class Storage implements ArrayAccess {
	protected string $path;

	/**
	 * Cache constructor.
	 *
	 * @param string $path
	 * @throws Exception
	 */
	public function __construct(string $path) {
		if(!$path) {
			throw new Exception("No storage path provided");
		}

		$original_path = $path;
		$path = realpath($path);

		if(!$path || !is_dir($path)) {
			throw new Exception("Storage path '{$original_path}' not exists");
		}

		$this->path = $path;
	}

	/**
	 * getExtestion
	 *
	 * @param $key
	 * @return string
	 */
	protected function getExtension($key) {
		return '';
	}

	/**
	 * Get file by key
	 *
	 * @param $key
	 * @return string
	 */
	protected function getFile($key) {
		return "{$this->path}/{$key}{$this->getExtension($key)}";
	}

	/**
	 * @inheritDoc
	 */
	public function offsetExists($offset) {
		return is_readable($this->getFile($offset));
	}

	/**
	 * @inheritDoc
	 */
	public function offsetGet($offset, $default = null) {
		if(!isset($this[$offset])) {
			return $default;
		}

		return file_get_contents($this->getFile($offset));
	}

	/**
	 * @inheritDoc
	 * @throws Exception
	 */
	public function offsetSet($offset, $value) {
		$file = $this->getFile($offset);

		if(!is_dir(dirname($file))) {
			if(!mkdir(dirname($file), 0755, true)) {
				throw new Exception('Unable to create storage dir for ' . $file);
			}
		}

		return file_put_contents($this->getFile($offset), $value);
	}

	/**
	 * @inheritDoc
	 */
	public function offsetUnset($offset) {
		if(!isset($this[$offset])) {
			return true;
		}

		return unlink($this->getFile($offset));
	}
}