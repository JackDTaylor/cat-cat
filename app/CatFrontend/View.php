<?php
namespace CatFrontend;

use CatFrontend\UrlBuilder\UrlBuilder;

class View {
	protected string $__view;
	protected UrlBuilder $__url_builder;
	protected string $__page_title = 'Cat_Cat';

	/**
	 * View constructor.
	 *
	 * @param string $view
	 * @param array $params
	 * @param UrlBuilder $url_builder
	 */
	public function __construct(string $view, array $params, UrlBuilder $url_builder) {
		$this->__view = $view;
		$this->__url_builder = $url_builder;

		foreach($params as $param => $value) {
			$this->{$param} = $value;
		}
	}

	/**
	 * getUrlBuilder{
	 *
	 * @return UrlBuilder
	 */
	public function getUrlBuilder() : UrlBuilder {
		return $this->__url_builder;
	}

	/**
	 * compile
	 *
	 * @return false|string
	 */
	public function compile() {
		ob_start();
		include APPROOT . "/views/layout.phtml";
		return ob_get_clean();
	}

	/**
	 * title
	 *
	 * @return string
	 */
	public function title() {
		return $this->__page_title;
	}

	/**
	 * setPageTitle
	 *
	 * @param $title
	 * @return $this
	 */
	public function setPageTitle($title) {
		$this->__page_title = $title;
		return $this;
	}

	/**
	 * content
	 *
	 * @return false|string
	 */
	public function content() {
		ob_start();

		/** @noinspection PhpIncludeInspection */
		include APPROOT . "/views/{$this->__view}.phtml";
		return ob_get_clean();
	}

	/**
	 * render
	 *
	 * @param $view
	 * @param array $params
	 * @return false|string
	 */
	public function render($view, array $params = []) {
		$view = new static($view, $params, $this->__url_builder);

		return $view->content();
	}

	/**
	 * __toString
	 *
	 * @return false|string
	 */
	public function __toString() {
		return $this->compile();
	}
}