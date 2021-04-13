<?php


namespace CatFetcher\Util;

use Exception;
use Throwable;

class Downloader {
	protected const VK_ERROR_PAGE_REGEX = (
		'#<button class="flat_button message_page_btn" id="msg_back_button">Назад</button>#ui'
	);

	/**
	 * doctypeValidator
	 *
	 * @param $content
	 * @return string
	 * @throws Exception
	 */
	public static function doctypeValidator($content) {
		if(strpos($content, '<!DOCTYPE') !== false) {
			$utf8_content = iconv('CP1251', 'UTF8', $content);

			if(preg_match(static::VK_ERROR_PAGE_REGEX, $utf8_content)) {
				// Deleted document, store as an empty file for now
				return '';
			}

			throw new Exception("`<!DOCTYPE` found");
		}

		return $content;
	}

	/**
	 * downloadFile
	 *
	 * @param $url
	 * @param int $attempts
	 * @param callable|null $postprocessor
	 * @param bool $throw_on_errors
	 * @return false|string
	 * @throws Exception
	 */
	public static function downloadFile($url, $attempts = 5, callable $postprocessor = null, $throw_on_errors = false) {
		$content = file_get_contents($url);

		if(!$content) {
			if($attempts > 0) {
				sleep(1);

				return static::downloadFile($url, $attempts -1, $postprocessor, $throw_on_errors);
			}

			if($throw_on_errors) {
				throw new Exception("Unable to download file {$url}");
			}

			return '';
		}

		if($postprocessor) {
			try {
				$content = $postprocessor($content);
			} catch(Throwable $exception) {
				if(!$throw_on_errors) {
					return '';
				}

				throw new Exception("Unable to download file {$url}: {$exception->getMessage()}");
			}
		}

		return $content;
	}
}