<?php


namespace CatFetcher\Model;

use CatCat\Storage\FileStorage;

class Document extends StoredModel {
	public string $base_url;
	public string $download_url;
	public string $local_url;
	public string $title;

	public int $date;

	/**
	 * fromObject
	 *
	 * @param object $object
	 * @return static
	 */
	public static function fromObject(object $object): self {
		$ext = $object->ext ?? preg_replace('/\.(.*)$/', '$1', $object->local_url);

		return new static($object->download_url, $ext, $object->title, $object->date);
	}

	/**
	 * Document constructor.
	 *
	 * @param $url
	 * @param $ext
	 * @param $title
	 * @param $date
	 */
	public function __construct($url, $ext, $title, $date) {
		[$base_url] = explode('?', $url);
		$local_file = FileStorage::getStoredPath($base_url, ".{$ext}");

		$this->base_url      = $base_url;
		$this->download_url  = $url;
		$this->local_url     = $local_file;
		$this->title         = $title;
		$this->date          = $date;

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