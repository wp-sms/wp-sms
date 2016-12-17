<?php
	class Default_Gateway {
		private $wsdl_link = '';
		public $tariff = '';
		public $unitrial = false;
		public $unit;
		public $flash = "enable";
		public $isflash = false;

		public function __construct() {
			$this->validateNumber = "09xxxxxxxx";
		}
		
		public function SendSMS() {
			return false;
		}
		
		public function GetCredit() {
			return 11;
		}
	}