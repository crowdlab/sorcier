<?php
namespace Google;
use Google;
/**
 * Google URL Shortener API
 */
class UrlApi {
	private static $instance;

	/**
	 * Get instance
	 */
	public static function getInstance() {
		global $config;
		if (static::$instance == null)
			static::$instance = new static($config['google_shortener_key']);
		return static::$instance;
	}		

	const DEFAULT_API_URL = 'https://www.googleapis.com/urlshortener/v1/url';

	private function __construct($key, $apiURL = self::DEFAULT_API_URL) {
		$this->apiURL = $apiURL.'?key='.$key;
	}
	
	/**
	 * Shorten url
	 */
	function shorten($url) {
		$response = $this->send($url);
		return isset($response['id']) ? $response['id'] : false;
	}
	
	/**
	 * Expand a URL
	 */
	function expand($url) {
		$response = $this->send($url, false);
		return isset($response['longUrl']) ? $response['longUrl'] : false;
	}
	
	/**
	 * Send request to Google
	 */
	protected function send($url, $shorten = true) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		if($shorten) {
			$options = [
				CURLOPT_URL => $this->apiURL,
				CURLOPT_POST => 1,
				CURLOPT_POSTFIELDS => json_encode(['longUrl' => $url]),
				CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
			];
			\curl_setopt_many($ch, $options);
		} else {
			curl_setopt($ch, CURLOPT_URL, $this->apiURL.'&shortUrl='.$url);
		}
		$result = curl_exec($ch);
		curl_close($ch);
		return json_decode($result, true);
	}
}
?>
