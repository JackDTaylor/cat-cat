<?php


namespace CatFetcher\Util;

class Logger {
	/**
	 * logValue
	 *
	 * @param $value
	 * @return string|true
	 */
	public static function logValue($value) : string {
		if(is_string($value) || is_numeric($value)) {
			return $value;
		}

		if(is_null($value)) {
			return '[NULL]';
		}

		if(is_bool($value)) {
			return $value ? '[TRUE]' : '[FALSE]';
		}

		return print_r($value, true);
	}

	/**
	 * log
	 *
	 * @param mixed ...$messages
	 */
	public static function log(...$messages) {
		if(php_sapi_name() == 'cli') {
			echo implode(' ', array_map(fn($x) => static::logValue($x), $messages)) . PHP_EOL;
			return;
		}

		// Do nothing else for now
	}

	/**
	 * logProgress
	 *
	 * @param $message
	 * @param $offset
	 * @param $total
	 */
	public static function logProgress($message, $offset, $total) {
		$offset = (int)$offset;
		$total  = (int)$total;

		if(!$total) {
			static::log($message, '0/0', '(0.00%)');
			return;
		}

		$percentage = number_format($offset / $total * 100, 1);

		static::log($message, "{$offset}/{$total}", "({$percentage}%)");
	}
}