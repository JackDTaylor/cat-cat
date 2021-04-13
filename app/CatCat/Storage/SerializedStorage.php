<?php
namespace CatCat\Storage;


use Exception;

class SerializedStorage extends Storage {
	/**
	 * getExtestion
	 *
	 * @param $key
	 * @return string
	 */
	protected function getExtension($key) {
		return '.dat';
	}

	/**
	 * offsetGet
	 *
	 * @param mixed $offset
	 * @param null $default
	 * @return false|mixed|string|null
	 * @throws Exception
	 */
	public function offsetGet($offset, $default = null) {
		$attempts = 3;
		$data = false;

		while($attempts-- && $data === false) {
			$data = @gzinflate(parent::offsetGet($offset, $default));

			if($data === false) {
				usleep(250_000);
			}
		}

		if($data === false) {
			$class = static::class;
			throw new Exception("Unable to get {$class} offset '{$offset}'");
		}

		return unserialize($data);
	}

	/**
	 * offsetSet
	 *
	 * @param mixed $offset
	 * @param mixed $value
	 * @return false|int|void
	 * @throws Exception
	 */
	public function offsetSet($offset, $value) {
//		if($value instanceof Queue && $value->getActiveJob()) {
//			$job = $value->getActiveJob();
//
//			header('Content-Type: text/plain; charset=utf-8');
//
//			echo '############# OP-0 ##############################################################################', PHP_EOL;
//			$op0 = $job->operations[0];
//			echo print_r(iterator_to_array($op0->execute($job, $value)), true);
//			echo 'OP0: ', print_r($op0, true);
//			echo PHP_EOL, PHP_EOL, PHP_EOL, PHP_EOL;
//
//			echo '############# OP-1 ##############################################################################', PHP_EOL;
//			$op1s = serialize($op0);
//			echo 's(OP0): ', $op1s, PHP_EOL;
//			$op1 = unserialize($op1s);
//			echo print_r(iterator_to_array($op1->execute($job, $value)), true);
//			echo 'OP1: ', print_r($op1, true);
//			echo PHP_EOL, PHP_EOL, PHP_EOL, PHP_EOL;
//
//			echo '############# OP-2 ##############################################################################', PHP_EOL;
//
//			dprb();
//			dpr($op1s, serialize($op1), $op0, $op1);
//			$op2s = serialize($op1);
//			echo 's(OP1): ', $op2s, PHP_EOL;
//			$op2 = unserialize($op2s);
//			echo print_r(iterator_to_array($op2->execute($job, $value)), true);
//			echo 'OP2: ', print_r($op2, true);
//			echo PHP_EOL, PHP_EOL, PHP_EOL, PHP_EOL;
//
//			echo '############# OP-3 ##############################################################################', PHP_EOL;
//			$op3s = serialize($op2);
//			echo 's(OP2): ', $op3s, PHP_EOL;
//			$op3 = unserialize($op3s);
//			echo print_r(iterator_to_array($op3->execute($job, $value)), true);
//			echo 'OP3: ', print_r($op3, true);
//			echo PHP_EOL, PHP_EOL, PHP_EOL, PHP_EOL;
//
//			echo '############# DONE ##############################################################################', PHP_EOL;
//			exit;
//
//			dpr($offset, $value->getActiveJob()->getEstimatedLeftCount(), $value);
//		}

		return parent::offsetSet($offset, gzdeflate(serialize($value), 5));
	}
}