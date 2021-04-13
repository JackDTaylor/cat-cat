<?php


namespace CatCat;


use CatCat\Storage\FileStorage;
use CatCat\Storage\HtmlStorage;
use CatCat\Storage\JsonStorage;
use CatCat\Storage\SerializedStorage;
use CatCat\Storage\Typed\ArticleStorage;
use CatCat\Storage\Typed\DocumentStorage;
use CatCat\Storage\Typed\MiscStorage;
use CatCat\Storage\Typed\PhotoStorage;
use CatCat\Storage\Typed\PostStorage;
use Exception;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

class Database {
	public FileStorage $files;

	public JsonStorage $wall_raw;
	public PostStorage $wall;

	public JsonStorage $index;

	public PhotoStorage $photos;
	public DocumentStorage $documents;

	public HtmlStorage    $articles_raw;
	public ArticleStorage $articles;

	public MiscStorage       $misc;
	public SerializedStorage $queue;

	/**
	 * Database constructor.
	 *
	 * @param Config $config
	 * @throws ReflectionException
	 * @throws Exception
	 */
	public function __construct(Config $config) {
		$reflection = new ReflectionClass(static::class);
		$properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

		foreach($properties as $property) {
			$Storage = $property->getType()->getName();
			$name = $property->getName();

			$this->{$name} = new $Storage($config->getStoragePath($name));
		}
	}
}