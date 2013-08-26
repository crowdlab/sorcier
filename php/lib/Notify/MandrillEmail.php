<?php
namespace Notify;
use CrowdlabWorker\BaseWorker;
use Notify;
use Notify\Type;

class MandrillEmail {
	private $mandrill;
	private $message;
	private $template_name;
	private $merge_vars = [];

	function __construct() {

		global $config;
		$host = $config['host'];
		$facebook = isset($config['facebook']) ? $config['facebook'] : '';
		$twitter  = isset($config['twitter'])  ? $config['twitter']  : '';

		$this->message = [
			'headers' => [],
			'important' => false,
			'track_opens' => null,
			'track_clicks' => null,
			'auto_text' => null,
			'auto_html' => null,
			'inline_css' => null,
			'url_strip_qs' => null,
			'preserve_recipients' => false,
			'tracking_domain' => null,
			'signing_domain' => null,
			'merge' => true,
			'global_merge_vars' => [
				[
					'name' => 'settings_link',
					'content' => "http://$host/xnet/mysettings"
				],
				[
					'name' => 'facebook',
					'content' => $facebook
				],
				[
					'name' => 'twitter',
					'content' => $twitter
				],
				[
					'name' => 'progress',
					'content' => "http://$host/"
				]
			],
			'google_analytics_domains' => [$host],
			'google_analytics_campaign' => 'email'
		];
		return $this;
	}


	/**
	 * Settin' notification type for choosing right template
	 * @param $type Int Notification type
	 * @return $this
	 */
	function SetType($type) {
		$this->template_name = Type::getConstName($type);
		$this->message['google_analytics_campaign'] = 'notification';
		return $this;
	}

	/**
	 * Setting recipients an fetching basic info about them from DB
	 * @param $ids Array(Int) recipients
	 * @return $this
	 */
	function SetRecipientsByIDs($ids) {
		$users = self::fetchUsersInfo($ids);

		foreach ($users as $user) {
			if (!isset($user['email']))
				continue;
			$this->message['to'][] = [
				'email' => $user['email'],
				'name' => $user['name'].' '.$user['surname']
			];
			$this->SetUserVars($user['email'], $user);
		}
		return $this;
	}

	/**
	 * Setting recipients by email
	 * @param $emails
	 * @return $this
	 */
	function SetRecipientsByEmail($emails) {
		foreach ($emails as $email) {
			$this->message['to'][] = [
				'email' => $email
			];
		}
		return $this;
	}

	/**
	 * Setting reply-to header for conversations
	 * @param $email
	 * @return $this
	 */
	function SetReplyTo($email) {
		if (!!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$this->message['headers']['Reply-To'] = $email;
		}
		return $this;
	}

	/**
	 * Setting user specific variables for template (links,texts, etc.)
	 * @param $user Int || String UserId or Email
	 * @param $vars
	 * @return $this
	 */
	function SetUserVars($user, $vars) {

		if (!!filter_var($user, FILTER_VALIDATE_EMAIL)) {
			$email = $user;
		} else {
			$emails = \DAO\User\MailDAO::getInstance()->getEmailsByIds([$user]);
			if (!!$emails) {
				$email = $emails[0]['email'];
			} else {
				return $this;
			}
		}
		!isset($this->merge_vars[$email])
			? $this->merge_vars[$email] = $vars
			: $this->merge_vars[$email] += $vars;
		return $this;
	}

	/**
	 * Setting global variables for template (host, social links, etc.)
	 * @param $vars
	 * @return $this
	 */
	function SetGlobalVars($vars) {
		$v = self::resortVars($vars);
		foreach($v as $e) {
			$this->message['global_merge_vars'][] = $e;
		}
		return $this;
	}

	/**
	 * Set google analytics campaign
	 * @param $campaign String
	 * @return $this
	 */
	function SetCampaign($campaign) {
		$this->message['google_analytics_campaign'] = $campaign;
		return $this;
	}

	/**
	 * Setting author of \Operations::$ImmediateTypes notification
	 * @param $uid author id
	 * @return $this
	 */
	function  SetAuthor($uid) {
		$aData = \DAO\User\DAO::getInstance()
			->getUsersByIds([$uid],['id','name','surname','photo']);
		if (!isset($aData[0]))
			return $this;
		foreach ($aData[0] as $k => $v) {
			$this->message['global_merge_vars'][] = [
			 'name'    => "author_$k",
			 'content' => $v
			];
		}
		return $this;
	}


	/**
	 * Sending email to mandrill server;
	 */
	function Send() {
		foreach($this->merge_vars as $email=>$vars) {
			$this->message['merge_vars'][] = [
				'rcpt' => $email,
				'vars' => self::resortVars($vars)
			];
		}

		$data['message'] = $this->message;
		$data['template_name'] = $this->template_name;

		BaseWorker::sendToWorker('sendMailNotification', $data);
	}

	/**
	 * Cast key-value array to simple array of name/content structures
	 * @param $vars
	 * @return array
	 */
	private static function resortVars($vars) {
		$r = [];
		foreach($vars as $k => $v) {
			$r[] = ['name' => $k, 'content' => $v];
		}
		return $r;
	}

	/**
	 * Getting user information as array of 'email => info[]'
	 * @param $ids
	 * @return array
	 */
	private static function fetchUsersInfo($ids) {
		$emails = \DAO\User\MailDAO::getInstance()->getEmailsByIds($ids);
		//Make $emails associative
		$aemails = [];
		foreach($emails as $v) {
			$aemails[(int) $v['user_id']] = $v['email'];
		}
		$users = \DAO\User\DAO::getInstance()
			->getUsersByIds($ids, ['id','name','surname','photo']);

		//Merge data
		foreach($users as &$user) {
			if (isset($aemails[(int) $user['id']]))
				$user['email'] = $aemails[(int) $user['id']];
		}
		return $users;
	}
}
