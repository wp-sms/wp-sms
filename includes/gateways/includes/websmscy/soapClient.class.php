<?php

/**
 * Class Deffinition
 *
 */
class WebsmsClient
{
	private $soap_client;
	private $username;
	private $password;
	private $session_path;
	private $time_to_live;

	function __construct($cfg)
	{
		$this->soap_client = new SoapClient($cfg['wsdl_file'], array('cache_wsdl' => WSDL_CACHE_NONE, 'trace' => true));
		$this->session_path = 25 * 60;
		$this->username = $cfg['username'];
		$this->password = $cfg['password'];
		$this->time_to_live = sys_get_temp_dir() . "/websms.com.cy.ses";
	}

	function authenticate()
	{
		$obj = new stdClass();
		$obj->username = $this->username;
		$obj->password = $this->password;

		$ret = $this->soap_client->authenticate($obj);

		if ($ret->success == 1) {
			$session = $ret->session_id;
			$this->setFileSession($session);
			return $ret->session_id;
		} else {
			throw new Exception('Invalid Username and or password');
		}
	}

	function getSession()
	{
		$session = $this->getFileSession();

		if ($session == null) {
			$session = $this->authenticate();
			$this->setFileSession($session);
		}

		return $session;
	}

	function getFileSession()
	{
		if (file_exists($this->session_path)) {
			$tm = filemtime($this->session_path);

			if (time() - $tm < $this->time_to_live) {
				$session = file_get_contents($this->session_path);
			} else {
				$session = null;
			}
			return $session;
		}

		return null;
	}

	function setFileSession($session)
	{
		file_put_contents($this->session_path, $session);
	}

	function touchFile()
	{
		touch($this->session_path);
	}

	function getCredits()
	{
		$session = $this->getSession();
		$res = $this->soap_client->getCredits($session);
		$this->touchFile();
		return $res;
	}

	function submitSM($from, $to, $message, $encoding = "GSM")
	{
		$obj = new stdClass();
		$obj->session_id = $this->getSession();
		$obj->from = $from;
		$obj->message = $message;
		//$obj->message="A[]{}";
		$obj->data_coding = $encoding;
		if (is_array($to))
			$obj->to = $to;
		else
			$obj->to = array($to);

		try {
			$ret = $this->soap_client->sendSM($obj);
			return $ret;
		} catch (SoapFault $soapFault) {
			throw new Exception($this->soap_client->__getLastResponse());
		}
	}

	function getBatch($batchId)
	{
		$obj = new stdClass();
		$obj->sessionId = $this->getSession();
		$obj->batchId = $batchId;

		try {
			$ret = $this->soap_client->getBatchStatus($obj);
			return $ret;
		} catch (SoapFault $soapFault) {
			var_dump($c);
			echo "Request :<br>", $this->soap_client->__getLastRequest(), "<br>";
			echo "Response :<br>", $this->soap_client->__getLastResponse(), "<br>";
		}
	}
}