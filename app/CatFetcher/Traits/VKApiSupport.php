<?php
namespace CatFetcher\Traits;


use CatFetcher\Util\Logger;
use Exception;

trait VKApiSupport {
	abstract function getVkApiAccessToken();

	/**
	 * fetchVkApiResponse
	 *
	 * @param string $method
	 * @param array $params
	 * @param int $attempts
	 * @return object
	 * @throws Exception
	 */
	public function fetchVkApiResponse(string $method, array $params, int $attempts = 5) {
		$params['access_token'] = $this->getVkApiAccessToken();
		$params['v'] = '5.52';

		$params = http_build_query(array_replace_recursive($params, [
			'lang' => 'ru',
		]));

		while($attempts > 0) {
			$response = json_decode(file_get_contents("https://api.vk.com/method/{$method}?{$params}"));

			if($response->response->items ?? null) {
				return $response;
			}

			Logger::log("Failed to fetch VK API response, retrying...");
			$attempts--;

			sleep(1);
		}

		throw new Exception("Unable to fetch VK API response: {$method}?{$params}");
	}
}