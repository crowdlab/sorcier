<?php
/**
 * If you want to make Singleton class, please, use this trait
 */
trait Singleton {
	protected static $_instance;

	/**
	 * Set instance manually (for the sake of testing)
	 *
	 * @param static $inst  экземпляр
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
	 * Reset instance. This method can be used for debugging/testing purposes, not production.
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

	private function __wakeup() { } // Mockery needs to override __wakeup

	final private function __clone() { }
}

?>
