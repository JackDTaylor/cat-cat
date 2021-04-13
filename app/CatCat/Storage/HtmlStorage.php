<?php
namespace CatCat\Storage;


class HtmlStorage extends Storage {
	/**
	 * getExtestion
	 *
	 * @param $key
	 * @return string
	 */
	protected function getExtension($key) {
		return '.html';
	}
}