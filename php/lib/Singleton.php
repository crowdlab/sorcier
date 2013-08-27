<?php
/**
 * If you want to make Singleton class, please, use this trait
 */
trait Singleton {
	protected static $_instance;

	/**
	 * Set instance manually (for the sake of testing)
	 *
	 * @param static $inst  instance
	 * @return void
	 */
	public static function setInstance($inst) {
		$calledClass = get_called_class();
		self::$_instance[$calledClass] = $inst;
	}

	/**
	 * Get instance
	 *
	 * @return static
	 * @static
	 */
	public static function getInstance() {
		$calledClass = get_called_class();
		if (!isset(self::$_instance[$calledClass]) || is_null(self::$_instance[$calledClass])) {
			self::$_instance[$calledClass] = new $calledClass;
		}
		return self::$_instance[$calledClass];
	}

	/**
	 * Reset instance. Used for debugging/testing purposes.
	 *
	 * @return void
	 * @static
	 */
	public static function reset() {
		if (!is_null(self::$_instance))
			self::$_instance = null;
	}

	/**
	 * @return void
	 */
	final private function __construct() {
		$this->init();
	}

	/**
	 * This function can be redefined and used as constructor replacement
	 */
	protected function init() { }

	// Mockery needs to override __wakeup
	private function __wakeup() { }

	final private function __clone() { }
}

?>
