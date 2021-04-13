<?php
namespace CatCat;

use Exception;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use stdClass;

class Config {
	public string $access_token;
	public int $owner_id;
	public bool $with_reposts;

	public stdClass $storage;

	/**
	 * Config constructor.
	 *
	 * @param string $json
	 * @throws ReflectionException
	 */
	public function __construct(string $json) {
		/** @var stdClass $config */
		$config = json_decode($json);

		$reflection = new ReflectionClass(static::class);
		$properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
		$properties = array_column($properties, 'name');

		foreach($config as $property => $value) {
			if(!in_array($property, $properties)) {
				continue;
			}

			$this->{$property} = $value;
		}
	}

	/**
	 * getStoragePath
	 *
	 * @param $name
	 * @return false|string|null
	 * @throws Exception
	 */
	public function getStoragePath($name) {
		$path = $this->storage->{$name} ?? "/storage/{$name}";
		$path = $path ? realpath(APPROOT . $path) : null;

		if($path) {
			return $path;
		}

		throw new Exception("Unknown storage path '{$name}'");
	}
}