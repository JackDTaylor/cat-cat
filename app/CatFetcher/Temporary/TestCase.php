<?php /** @noinspection PhpMissingDocCommentInspection */


namespace CatFetcher\Temporary;


class TestCase {
	/**
	 * test
	 *
	 * @param $i
	 * @return array
	 */
	function someMethod($i) {
		return ["key-{$i}" => $i ** 2];
	}

	protected function getTestClosure($ref) {
		$i = 3;

		return new TestClosure(function(TestCase $ctx) use($i) { $ctx->someMethod($i); }, $ref);
	}

	public function executeTest() {
//		$ref = new TestRef();
//		$closure = $this->getTestClosure($ref);
//
//		$closure = new SerializableClosure(fn() => $this->someMethod());
//		$closure = new SerializableClosure(function($test) { return $test->someMethod(); });
//		dpr(serialize($closure), serialize(unserialize(serialize($closure))));
//		exit;
//		header('Content-Type: text/plain; charset=utf-8');
//
//		echo '############# OP-0 ##############################################################################', PHP_EOL;
////		$op0 = $job->operations[0];
//		echo print_r(($closure->execute($this)), true);
//		echo 'OP0: ', print_r($closure, true);
//		echo PHP_EOL, PHP_EOL, PHP_EOL, PHP_EOL;
//
//		echo '############# OP-1 ##############################################################################', PHP_EOL;
//		$op1s = serialize($closure);
//		echo 's(OP0): ', $op1s, PHP_EOL;
//		$op1 = unserialize($op1s);
//		echo print_r(($op1->execute($this)), true);
//		echo 'OP1: ', print_r($op1, true);
//		echo PHP_EOL, PHP_EOL, PHP_EOL, PHP_EOL;
//
//		echo '############# OP-2 ##############################################################################', PHP_EOL;
//
//		dprb();
//		dpr($op1s, serialize($op1), $closure, $op1);
//
//		$op2s = serialize($op1);
//		echo 's(OP1): ', $op2s, PHP_EOL;
//		$op2 = unserialize($op2s);
//		echo print_r(($op2->execute($this)), true);
//		echo 'OP2: ', print_r($op2, true);
//		echo PHP_EOL, PHP_EOL, PHP_EOL, PHP_EOL;
//
//		echo '############# OP-3 ##############################################################################', PHP_EOL;
//		$op3s = serialize($op2);
//		echo 's(OP2): ', $op3s, PHP_EOL;
//		$op3 = unserialize($op3s);
//		echo print_r(($op3->execute($this)), true);
//		echo 'OP3: ', print_r($op3, true);
//		echo PHP_EOL, PHP_EOL, PHP_EOL, PHP_EOL;
//
//		echo '############# DONE ##############################################################################', PHP_EOL;
//		exit;
	}
}