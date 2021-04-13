<?php


namespace CatFetcher\Model;

class Article extends Model {
	public string $code;
	public string $title;
	public array $segments;

	/**
	 * fromObject
	 *
	 * @param object $object
	 * @return static
	 */
	public static function fromObject(object $object): self {
		return new static($object->code, $object->title, $object->segments);
	}

	/**
	 * Article constructor.
	 *
	 * @param string $code
	 * @param string $title
	 * @param array $segments
	 */
	public function __construct(string $code, string $title, array $segments) {
		$this->code = $code;
		$this->title = $title;
		$this->segments = $segments;
	}
}