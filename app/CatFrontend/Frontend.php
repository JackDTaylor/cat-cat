<?php


namespace CatFrontend;


use CatCat\Application;
use CatFrontend\Controller\Controller;
use CatFrontend\Controller\ImportController;
use CatFrontend\Controller\SiteController;
use CatFrontend\UrlBuilder\SiteUrlBuilder;
use CatFrontend\UrlBuilder\UrlBuilder;
use Exception;

class Frontend extends Application {
	protected ?UrlBuilder $url_builder = null;

	/**
	 * @param array $query
	 * @return string
	 */
	protected function route(array $query) {
		if($query[0] === '@import') {
			return ImportController::class;
		}

		return SiteController::class;
	}

	/**
	 * run
	 *
	 * @return false|string
	 * @throws Exception
	 */
	public function run() {
		[$uri] = explode('?', $_SERVER['REQUEST_URI'], 2);
		$query = explode('/', trim(urldecode($uri), '/'));

		/** @var Controller $Controller */
		/** @var Controller $controller */

		$Controller = $this->route($query);
		$controller = new $Controller($this->db, $this->config, $this->createUrlBuilder());

		return $controller->route($query);
	}

	/**
	 * createUrlBuilder
	 *
	 * @return SiteUrlBuilder
	 */
	protected function createUrlBuilder() {
		return new SiteUrlBuilder();
	}
}