<?php
/**
 * user operations should extend this
 */
abstract class UserSingletonBase {
	protected static $instance;
	protected $user;
	protected $session;

	final private function __construct(&$session) {
		$this->session = & $session;
		if (isset($this->session['user']))
			$this->user = $this->session['user'];
		else
			$this->user = [];
	}

	public function saveSession() {
		if (isset($this->user))
			$this->session['user'] = $this->user;
	}

	public static function setInstance($inst) {
		$calledClass = get_called_class();
		static::$instance[$calledClass] = $inst;
	}

	final private function __clone() { /* ... @return UserSingleton */
	}

	private function __wakeup() { /* ... @return UserSingleton */
	} // Mockery needs to override __wakeup

	/**
	 * This function returns whether user is actually logged in
	 *
	 * @return bool
	 */
	public static function canInstance(&$session = null) {
		if (is_null($session)) $session = & $_SESSION;
		return isset($session['user']);
	}

	/**
	 * Reset instance. Used for debugging/testing purposes.
	 */
	public static function reset() {
		if (!is_null(static::$instance))
			static::$instance = null;
	}

	/**
	 * Get instance
	 *
	 * @return UserSingleton
	 */
	public static function getInstance(&$session = null) {
		if (is_null($session)) $session = & $_SESSION;
		$calledClass = get_called_class();
		if (!isset(static::$instance[$calledClass])
			|| is_null(static::$instance[$calledClass])
		) {
			static::$instance[$calledClass] = new $calledClass($session);
		}
		return static::$instance[$calledClass];
	}

	/**
	 * After login, $session['user'] should be set
	 */
	abstract public static function login($params);

	/**
	 * Get user info
	 *
	 * @return array
	 */
	public function getUser() {
		return $this->user;
	}

	protected function postLogout() { }

	/**
	 * Finish session
	 */
	public function logout() {
		unset($this->user);
		unset($this->session['user']);
		static::$instance = null;
		$this->postLogout();
		return ['message' => 'ok'];
	}
}

?>
