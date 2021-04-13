<?php


namespace CatFetcher\Temporary;


use Closure;
use Opis\Closure\SerializableClosure;

class TestClosure extends SerializableClosure {
	/**
	 * Constructor
	 *
	 * @param Closure $closure Closure you want to serialize
	 * @param TestRef $reference
	 */
	public function __construct(Closure $closure, TestRef $reference) {
//		$closure = $closure->bindTo($reference);

		parent::__construct($closure);
	}

	/**
	 * execute
	 *
	 * @param $context
	 * @return mixed
	 */
	public function execute($context) {
		$closure = $this->getClosure();
		$closure = $closure->bindTo($context);

		return $closure();
	}
}