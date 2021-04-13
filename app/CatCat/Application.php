<?php


namespace CatCat;

use Exception;
use ReflectionException;

abstract class Application {
	public Config $config;
	public Database $db;

	/**
	 * Application constructor.
	 *
	 * @param string $config_file
	 * @throws ReflectionException
	 * @throws Exception
	 */
	public function __construct($config_file = '/config.json') {
		$config_file = realpath(APPROOT . $config_file);

		if(!$config_file) {
			throw new Exception("Config file '{$config_file}' not found");
		}

		$this->config = new Config(file_get_contents($config_file));
		$this->db     = new Database($this->config);
	}

	/**
	 * @return mixed
	 */
	abstract public function run();
 }