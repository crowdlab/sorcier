<?php
# require_once __DIR__ . '/../inc/config.php';
require_once 'swift_required.php';
// TODO (max): make Connectors for CometRP and Hybrid_Auth
/**
 * Коннекторы к базам и системам используемым в проекте
 */
final class Connector {
	use \Singleton;

	private $elasticInst = null;
	private $mailInst    = null;
	private $mongoInst   = null;
	private $mysqlInst   = null;
	private $redisInst   = null;

	/**
	 * @return \Elastica\Index
	 */
	public function getElastic() {
		if (is_null($this->elasticInst)) {
			self::setElastic();
		}
		return $this->elasticInst;
	}

	/**
	 * Checks that ElasticSearch is working correctly
	 * @return bool
	 */
	public function getElasticStatus() {
		try {
			self::getElastic()->getStatus(); // просто вызов на доступность
		} catch (\Elastica\Exception\ClientException $ex) {
			\logger\Log::instance()->logCrit('Elastica probably down', $ex->getMessage());
			return false;
		}
		return true;
	}

	/**
	 * @return \Swift_Mailer
	 */
	public function getMail() {
		if (is_null($this->mailInst)) {
			self::setMail();
		}
		return $this->mailInst;
	}

	/**
	 * @return \MongoClient
	 */
	public function getMongo() {
		if (is_null($this->mongoInst)) {
			self::setMongo();
		}
		return $this->mongoInst;
	}

	/**
	 * @return \mysqli
	 * @param $reconnect переподключиться
	 */
	public function getMySQL($reconnect = false) {
		if (is_null($this->mysqlInst) || $reconnect) {
			self::setMySQL();
		}
		return $this->mysqlInst;
	}

	/**
	 * @return \Redis
	 */
	public function getRedis() {
		if (is_null($this->redisInst)) {
			self::setRedis();
		}
		return $this->redisInst;
	}

	public function setAll(&$config = null) {
		self::setElastic($config);
		self::setMail($config);
		self::setMongo($config);
		self::setMySQL($config);
		self::setRedis($config);
	}

	public function setElastic(&$config = null) {
		if (is_null($config))
			global $config;
		if (!isset($config['elastic_host']) ||
			!isset($config['elastic_port']) ||
			!isset($config['elastic_index'])) {
			\logger\Log::instance()->logCrit('Elastica configs not setted', $config);
			// Пользователю можно и не знать об этом,
			// но в коде следует проверять доступность подсистемы через getElasticStatus
			return;
		}
		try {
			$elasticaClient = new \Elastica\Client([
				'host' => $config['elastic_host'],
				'port' => $config['elastic_port']
			]);
			$this->elasticInst = $elasticaClient->getIndex($config['elastic_index']);
			if (!$this->elasticInst->exists())
				\logger\Log::instance()->logCrit('Elastic Index '
					. $config['elastic_index'] . ' not created');
		} catch (\Elastica\Exception\ClientException $ex) {
			\logger\Log::instance()->logCrit('Elastica init error', $ex->getMessage());
		}
	}

	public function setMail(&$config = null) {
		if (is_null($config))
			global $config;
		if (!isset($config['mail_host']) ||
			!isset($config['mail_port']) ||
			!isset($config['mail_username']) ||
			!isset($config['mail_password'])) {
			\logger\Log::instance()->logCrit('Swift_Mail configs not set', $config);
			// Пользователю можно и не знать об этом
			// TODO (max): make getMailStatus
			return;
		}
		try {
			$mail_transport = \Swift_SmtpTransport::newInstance()
				->setHost($config['mail_host'])
				->setPort($config['mail_port'])
				->setUsername($config['mail_username'])
				->setPassword($config['mail_password']);
			$this->mailInst = \Swift_Mailer::newInstance($mail_transport);
		} catch (\Swift_TransportException $ex) {
			\logger\Log::instance()->logCrit('Swift_Mail init error', $ex->getMessage());
		}
	}

	public function setMongo(&$config = null) {
		if (is_null($config))
			global $config;
		if (!isset($config['mongo_host']) ||
			!isset($config['mongo_port'])) {
			\logger\Log::instance()->logCrit('Mongo configs not set', $config);
			return;
		}
		try {
			$this->mongoInst = new \MongoClient(
				"mongodb://${config['mongo_host']}:${config['mongo_port']}",
				['connectTimeoutMS' => 1000]);
		} catch (MongoException $ex) {
			\logger\Log::instance()->logCrit('Mongo init error', $ex->getMessage());
			if ($config['daemon'])
				return false;
			\Common::die500('database error');
		}
	}

	public function setMySQL(&$config = null) {
		if (is_null($config))
			global $config;
		if (!isset($config['mysql_host']) ||
			!isset($config['mysql_user']) ||
			!isset($config['mysql_pass']) ||
			!isset($config['mysql_db'])) {
			\logger\Log::instance()->logCrit('MySQL configs not set', $config);
			return;
		}
		$this->mysqlInst = @mysqli_connect(
			$config['mysql_host'],
			$config['mysql_user'],
			$config['mysql_pass'],
			$config['mysql_db']
		);
		if (!$this->mysqlInst) {
			\logger\Log::instance()->logCrit('MySQL init error');
			if ($config['daemon'])
				return false;
			\Common::die500('database error');
		}
		mysqli_query($this->mysqlInst, 'SET NAMES "utf8"');
		mysqli_query($this->mysqlInst, "set character_set_connection=utf8");
		mysqli_query($this->mysqlInst, "set group_concat_max_len=1048576");
	}

	public function setRedis(&$config = null) {
		if (is_null($config))
			global $config;
		if (!isset($config['redis_host']) ||
			!isset($config['redis_port'])) {
			\logger\Log::instance()->logCrit('Redis configs not set', $config);
			// Пользователю можно и не знать об этом
			return;
			// TODO (max): make getRedisStatus
		}
		try {
			$this->redisInst = new \Redis();
			$this->redisInst->connect(
				$config['redis_host'],
				$config['redis_port']
			);
		} catch (\RedisException $ex) {
			\logger\Log::instance()->logCrit('Redis init error', $ex->getMessage());
		}
	}
}
?>
