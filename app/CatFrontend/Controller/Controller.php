<?php


namespace CatFrontend\Controller;

use CatCat\Config;
use CatCat\Database;
use CatFrontend\UrlBuilder\UrlBuilder;
use CatFrontend\View;

abstract class Controller {
	protected Database $db;
	protected Config $config;
	protected UrlBuilder $url_builder;

	/**
	 * Controller constructor.
	 *
	 * @param Database $db
	 * @param Config $config
	 * @param UrlBuilder $url_builder
	 */
	public function __construct(Database $db, Config $config, UrlBuilder $url_builder) {
		$this->config = $config;
		$this->db = $db;
		$this->url_builder = $url_builder;
	}

	/**
	 * route
	 *
	 * @param array $query
	 * @return mixed
	 */
	abstract public function route(array $query);

	/**
	 * view
	 *
	 * @param string $name
	 * @param array $params
	 * @return View
	 */
	public function view(string $name, array $params = []) : View {
		return new View($name, $params, $this->getUrlBuilder());
	}

	/**
	 * getUrlBuilder
	 *
	 * @return UrlBuilder
	 */
	public function getUrlBuilder() : UrlBuilder {
		return $this->url_builder;
	}

	/**
	 * url
	 *
	 * @param string $url
	 * @return string
	 */
	public function url(string $url) : string {
		return "{$_SERVER['REQUEST_SCHEME']}://{$_SERVER['HTTP_HOST']}{$url}";
	}

	/**
	 * redirect
	 *
	 * @param string $url
	 * @return mixed
	 */
	public function redirect(string $url) {
		header("Location: {$url}");
		exit;
	}

	/**
	 * isPost
	 *
	 * @return bool
	 */
	public function isPost() : bool {
		return $_SERVER['REQUEST_METHOD'] == 'POST';
	}

	/**
	 * post
	 *
	 * @param string $key
	 * @param null $default
	 * @return mixed|null
	 */
	public function post(string $key, $default = null) {
		return $_POST[$key] ?? $default;
	}

	/**
	 * query
	 *
	 * @param string $key
	 * @param null $default
	 * @return mixed|null
	 */
	public function query(string $key, $default = null) {
		return $_GET[$key] ?? $default;
	}
}