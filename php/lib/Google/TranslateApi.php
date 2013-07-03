<?php
namespace Google;
use Google;
require_once __DIR__ . '/../../inc/common.php.inc';
/**
 * Google Translate API
 */
class TranslateApi {
	private static $instance;

	/**
	 * Get instance
	 */
	public static function getInstance() {
		if (static::$instance == null)
			static::$instance = new static(\Config::get('google_shortener_key'));
		return static::$instance;
	}		

	const DEFAULT_API_URL = 'https://www.googleapis.com/language/translate/v2';

	private function __construct($key, $apiURL = self::DEFAULT_API_URL) {
		$this->apiURL = $apiURL.'?key='.$key;
		$this->key = $key;
	}
	
	/**
	 * translate text
	 * @param $text
	 * @param $lang
	 */
	function translate($text, $lang = 'en') {
		$response = $this->send($text, $lang);
		return isset($response['data']['translations'])
				&& count($response['data']['translations'])
			? $response['data']['translations'][0]
			: $response; // error
	}
	
	/**
	 * Send request to Google
	 * @param $text
	 * @param $lang
	 */
	protected function send($text, $lang = 'en') {
		$ch = curl_init();
		$fields = [
			'q'      => $text,
			'target' => $lang
		];
		if (strlen($text) >= 5000) // google api limitation
			return ['error' => 'Text too large to translate'];
		$url = (strlen($text) < 500)
			? $this->apiURL.'&'.http_build_query($fields)
			: self::DEFAULT_API_URL;
		$options = [
			CURLOPT_URL            => $url,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_RETURNTRANSFER => 1,
		];
		if ($url == self::DEFAULT_API_URL) {
			// POST request
			$fields['key'] = $this->key;
			$built_query = http_build_query($fields);
			$options[CURLOPT_HTTPHEADER] = [
				'X-HTTP-Method-Override: GET',
				'Content-length: '.strlen($built_query)
			];
			$options[CURLOPT_POST] = true;
			$options[CURLOPT_POSTFIELDS] = $built_query;
		}
		\curl_setopt_many($ch, $options);
		$result = curl_exec($ch);
		if ($result === false) {
			\Logger\Log::instance()->logError(curl_error($ch));
		}
		curl_close($ch);
		return json_decode($result, true);
	}
}
?>
