<?php


namespace CatFetcher\Model;

use CatCat\Storage\FileStorage;

class Photo extends StoredModel {
	public string $download_url;
	public string $local_url;
	public int $width;
	public int $height;
	public string $text;

	/**
	 * fromObject
	 *
	 * @param object $object
	 * @return static
	 */
	public static function fromObject(object $object) : self {
		return new static($object->download_url, $object->width, $object->height, $object->text);
	}

	/**
	 * Photo constructor.
	 *
	 * @param $url
	 * @param $width
	 * @param $height
	 * @param string $text
	 */
	public function __construct($url, $width, $height, $text = '') {
		$local_file = FileStorage::getStoredPath($url, '.jpg');

		$this->download_url = $url;
		$this->local_url    = $local_file;
		$this->width        = $width;
		$this->height       = $height;
		$this->text         = $text;

		parent::__construct();
	}

	/**
	 * storageKey
	 *
	 * @return string
	 */
	public function storageKey() {
		return $this->local_url;
	}
}