<?php


namespace CatCat\Storage;

class FileStorage extends RawStorage {
	/**
	 * getStoredPath
	 *
	 * @param $file_key
	 * @param $extension
	 * @return string
	 */
	public static function getStoredPath($file_key, $extension) {
		$file = md5($file_key) . $extension;

		return substr($file, 0, 2) . '/' . substr($file, 2, 2) . '/' . substr($file, 4);
	}
}