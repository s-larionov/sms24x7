<?php

require_once 'Sms24x7Exception.php';
require_once 'httpclient/HttpClient.php';

class Sms24x7 {
	const TYPE_SMS = 'SMS';
	const TYPE_FLASH_SMS = 'FLASH-SMS';
	const TYPE_WAP_PUSH = 'WAP-PUSH';

	const DLR_STATUS_DELIVERED = 1;
	const DLR_STATUS_NOT_DELIVERED = 2;
	const DLR_STATUS_ACCEPT_BY_OPERATOR = 4;
	const DLR_STATUS_ACCEPT_BY_SMS_CENTER = 8;
	const DLR_STATUS_DECLINE_BY_SMS_CENTER = 16;
	const DLR_STATUS_UNDELIVERABLE = 32;

	protected $config = [
		'server' => null,
//		'email' => null,
//		'password' => null,
		'api_v' => '1.1',
		'format' => 'json',
	];

	protected $httpClient;

	protected $sid;

	public function __construct(array $config) {
		$this->config = array_merge($this->config, $config);
	}

	protected function getHttpClient() {
		if ($this->httpClient === null) {
			$this->httpClient = new HttpClient($this->getConfig('server'));
		}
		return $this->httpClient;
	}

	protected function getConfig($field = null, $default = null) {
		if ($field !== null) {
			if (is_array($field)) {
				$return = [];
				foreach($field as $fieldName) {
					$return[$fieldName] = $this->getConfig($fieldName);
				}
				return $return;
			} else if (array_key_exists($field, $this->config)) {
				return $this->config[$field];
			}
			return $default;
		}
		return $this->config;
	}

	protected function isAuthenticated() {
		return $this->sid !== null;
	}

	protected function getSid() {
		return $this->sid;
	}

	protected function prepareParameters(array $parameters) {
		$parameters = array_merge($parameters, $this->getConfig(['api_v', 'format']));

		if ($this->isAuthenticated()) {
			$parameters['sid'] = $this->getSid();
		} else {
			$parameters = array_merge($parameters, $this->getConfig(['email', 'password']));
		}

		return array_merge([], $parameters);
	}

	protected function request($method, array $parameters = []) {
		$params = $this->prepareParameters($parameters);
		$params['method'] = $method;

		$client = $this->getHttpClient();
		$client->setParameterGet($params);
		$response = $client->request();

		if ($response->getStatus() != 200) {
			throw new Exception("API request error: [{$response->getStatus}] {$response->getStatusText()}");
		}

		$responseData = json_decode($response->getBody(), true);
		if (json_last_error() !== JSON_ERROR_NONE || !isset($responseData['response'])) {
			throw new Exception("API response in wrong format");
		}
		$responseData = $responseData['response'];

		if (!isset($responseData['msg']) || !isset($responseData['msg']['err_code']) || $responseData['msg']['err_code'] != 0) {
			throw new Sms24x7Exception("[{$responseData['msg']['type']}] {$responseData['msg']['text']}", $responseData['msg']['err_code']);
		}

		if (isset($responseData['data'])) {
			return (array) $responseData['data'];
		}
		return [];
	}

	public function authenticate($email, $password, $remeberSid = true) {
		$response = $this->request('login', [
			'email' => $email,
			'password' => $password,
		]);

		if ($remeberSid) {
			$this->sid = $response['sid'];
		}
		return $response['sid'];
	}

	/**
	 * Confirm delivery message
	 * @require api_v 1.1
	 *
	 * @param $id
	 * @return array
	 */
	public function confirmMessage($id) {
		return $this->request('confirm_msg', ['id' => $id]);
	}

	/**
	 * Check message status
	 * @require api_v 1.0
	 *
	 * @param $id
	 * @return array [state, sender_name, text, phone, unicode, type, credits, n_raw_sms, start_time, last_update, dlr_mask, dlr_url, sms_validity]
	 */
	public function getMessageReport($id) {
		return $this->request('get_msg_report', ['id' => $id]);
	}

	/**
	 * Check message status
	 * @require api_v 1.0
	 *
	 * @return array [
	 *   [id] => 7052
	 *   [state] => ACTIVE
	 *   [interface] => API
	 *   [allow_federal_numbers] => 0
	 *   [check_text_for_premium_numbers] => 1
	 *   [check_for_stop_words] => 1
	 *   [moderate_dispatches] => 1
	 *   [start_page] =>
	 *   [email] => sergey@larionov.biz
	 *   [phone] =>
	 *   [first_name] =>
	 *   [last_name] =>
	 *   [translation_id] => ru_RU
	 *   [translit_id] =>
	 *   [local_time] => +4:00
	 *   [date_format] => %e/%c/%Y
	 *   [time_format] => %k:%i:%s
	 *   [credit_price_id] => 46
	 *   [gate_price_id] => 1
	 *   [credits] => 751
	 *   [credits_zero] => 0
	 *   [for_write_off] => 0
	 *   [credits_used] => 0
	 *   [credits_name] => SMS
	 *   [currency] => руб.
	 *   [sender_name] => sms-Service
	 *   [sms_validity] => 4320
	 *   [registration_date] => 18/12/2012 18:37:44
	 *   [last_login_date] => 18/12/2012 18:38:58
	 *   [phone_reports] => 0
	 *   [email_reports] => 1
	 *   [sms_ban_on_day] =>
	 *   [sms_ban_on_time_from] =>
	 *   [sms_ban_on_time_to] =>
	 *   [help] => 1
	 *   [natural_person] =>
	 *   [prepay] => 1
	 *   [only_for_dlvrd] => 0
	 *   [ads_filter] => DENY
	 *   [CRM] => 1
	 * )
	 */
	public function getProfile() {
		return $this->request('get_profile');
	}

	/**
	 * Send message
	 *
	 * @param string|string[] $phone
	 * @param string $text
	 * @param string|null $sender
	 * @param string $type
	 * @param array $additional - optional parameters: unicode, validity, dlr_mask, dlr_url, test
	 * @return array
	 */
	public function sendMessage($phone, $text, $sender = null, $type = self::TYPE_SMS, array $additional = array()) {
		$parameters = [
			'text' => (string) $text,
			'type' => $type
		];

		if ($sender) {
			$parameters['sender_name'] = $sender;
		}

		if (is_array($phone)) {
			$parameters['phones'] = json_encode($phone);
		} else {
			$parameters['phone'] = (string) $phone;
		}

		$parameters = array_merge($parameters, $additional);

		return $this->request('push_msg', $parameters);
	}
}
