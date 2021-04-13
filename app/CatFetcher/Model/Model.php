<?php


namespace CatFetcher\Model;


abstract class Model {
	public ?string $id = null;

	/**
	 * fromObject
	 *
	 * @param object $object
	 * @return static
	 */
	abstract public static function fromObject(object $object) : self;
}