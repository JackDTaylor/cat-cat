<?php


namespace CatFetcher\Job\Exception;


use Exception;

class InputRequiredException extends Exception {
	public string $input_code;

	/**
	 * InputRequiredException constructor.
	 *
	 * @param string $input_code
	 */
	public function __construct(string $input_code) {
		$this->input_code = $input_code;

		parent::__construct("The operation was aborted: User input '{$this->input_code}' required");
	}
}