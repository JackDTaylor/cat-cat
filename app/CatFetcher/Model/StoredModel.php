<?php


namespace CatFetcher\Model;


abstract class StoredModel extends Model {
	/**
	 * @deprecated
	 * @var array
	 */
	protected static array $repositories = [];

	/**
	 * repository
	 * @deprecated
	 * @return mixed
	 */
	public static function repository() {
		static::$repositories[ static::class ] = static::$repositories[ static::class ] ?? [];
		return static::$repositories[ static::class ];
	}

	/**
	 * serialize
	 *
	 * @deprecated
	 * @return string
	 */
	public static function serializeRepository() {
		return serialize(static::$repositories);
	}

	/**
	 * unserialize
	 *
	 * @deprecated
	 * @param $data
	 */
	public static function unserializeRepository(string $data) {
		static::$repositories = unserialize($data);
	}

	/**
	 * register
	 *
	 * @param StoredModel $model
	 */
	public static function register(self $model) {
		$repository = static::repository();
		$repository[ $model->storageKey() ] = $model;

		static::$repositories[static::class] = $repository;
	}

	/**
	 * find
	 *
	 * @deprecated
	 * @param $key
	 * @return static
	 */
	public static function find($key) : ?self {
		return static::repository()[$key] ?? null;
	}

	/**
	 * storageKey
	 *
	 * @return mixed
	 */
	abstract public function storageKey();

	public function __construct() {
		static::register($this);
	}
}