<?php
	
	class medianaNew extends WP_SMS {
		
		public $tariff = "http://mediana.ir/";
		public $unitrial = true;
		public $unit;
		public $flash = "enable";
		public $isflash = false;
		
		private $uri = "http://37.130.202.190:13434/Dispatcher";
		private $client = null;
		private $apiKey = '';
		
		public function __construct() {
			parent::__construct();
			$this->validateNumber = "09xxxxxxxx";
			$this->apiKey         = $this->options["gateway_username"];
			if ( strlen( $this->apiKey ) < 54 ) {
				return new WP_Error(
					'required-class' ,
					__(
						'لطفا کلید API-KEY را در بخش نام کاربری API وارد نمایید.فرمت اطلاعات وارد شده اشتباه است.' ,
						'wp-sms'
					)
				);
			}
		}
		
		public function SendSMS() {
			// Check gateway credit
			if ( is_wp_error( $this->GetCredit() ) ) {
				return new WP_Error(
					'account-credit' , __( 'Your account does not credit for sending sms.' , 'wp-sms' )
				);
			}
			
			/**
			 * Modify sender number
			 *
			 * @since 3.4
			 *
			 * @param string $this ->from sender number.
			 */
			$this->from = apply_filters( 'wp_sms_from' , $this->from );
			
			/**
			 * Modify Receiver number
			 *
			 * @since 3.4
			 *
			 * @param array $this ->to receiver number
			 */
			$this->to = apply_filters( 'wp_sms_to' , $this->to );
			
			/**
			 * Modify text message
			 *
			 * @since 3.4
			 *
			 * @param string $this ->msg text message.
			 */
			$this->msg = apply_filters( 'wp_sms_msg' , $this->msg );
			
			
			$result = $this->curl(
				'/send/oneToMany/' ,
				json_encode(
					[
						'api_key'     => $this->apiKey ,
						'send_number' => $this->from ,
						'recipients'  => $this->to ,
						'message'     => $this->msg ,
					]
				)
				,
				'json'
			);
			
			
			if ( ! empty( $result['status'] ) && $result['status'] == 'Inserted Into Send Queue.' ) {
				$this->InsertToDB( $this->from , $this->msg , $this->to );
				
				/**
				 * Run hook after send sms.
				 *
				 * @since 2.4
				 *
				 * @param string $result result output.
				 */
				do_action( 'wp_sms_send' , $result['status'] );
				
				return $result;
			}
			
			
			return $result;
			
		}
		
		public function GetCredit() {
			// Check username and password
			if ( ! $this->username ) {
				return new WP_Error(
					'account-credit' ,
					__( 'Username/Password does not set for this gateway' , 'wp-sms' )
				);
			}
			
			
			$result = $this->curl(
				'/credits/mine/' ,
				[
					'api_key' => $this->apiKey ,
				]
			);
			
			if ( ! empty( $result['credit'] ) ) {
				
				
				return $result['credit'];
			} else {
				return $result;
			}
			
			
		}
		
		protected function curl( $url , $parameters , $type = 'form' ) {
			$ch = curl_init();
			
			curl_setopt( $ch , CURLOPT_URL , $this->uri . $url );
			curl_setopt( $ch , CURLOPT_POST , 1 );
			curl_setopt(
				$ch ,
				CURLOPT_POSTFIELDS ,
				$parameters
			);
			if ( $type == 'json' ) {
				curl_setopt(
					$ch ,
					CURLOPT_HTTPHEADER ,
					array(
						'Content-Type: application/json'
					)
				);
			}
// Receive server response ...
			curl_setopt( $ch , CURLOPT_RETURNTRANSFER , true );
			
			$server_output = curl_exec( $ch );
			
			curl_close( $ch );

// Further processing ...
			return $this->checkError( str_replace( [ '\"' , '"{' , '}"' ] , [ '"' , '{' , '}' ] , $server_output ) );
		}
		
		/**
		 * this function will check response errors and will be throw if error exist
		 *
		 * @param $result
		 *
		 * @return mixed|\WP_Error
		 */
		public function checkError( $result ) {
			
			$description = json_decode( $result , true );
			
			
			if ( is_array( $description ) && ! empty( $description['descriptions'] ) ) {
				
				
				switch ( $description['code'] ) {
					/**
					 *
					 *
					 * 1002 authentication failed.
					 *
					 * 1003 authorization failed.
					 *
					 * 1201 server error to retrieve credit
					 *
					 * 1901 server error in calculating price of the message
					 *
					 * 1402, 1403  send number not found
					 *
					 * 2209 user info not found
					 *
					 * 2218 configs for user not found
					 *
					 * 2001 server internal error to send message
					 *
					 * 1203 insuffiecient credit
					 *
					 * 1502 pattern not found
					 *
					 * 1503 invalid params for pattern
					 * --------------------------------------
					 *
					 * 1001  error in inputs
					 */
					
					case 1001:
						return new WP_Error(
							'required-class' ,
							__(
								'error in inputs' ,
								'wp-sms'
							)
						);
						break;
					case 1002:
					case 1003:
						return new WP_Error(
							'required-class' ,
							__(
								'authentication failed' ,
								'wp-sms'
							)
						);
						break;
					case 1201:
						return new WP_Error(
							'required-class' ,
							__(
								'server error to retrieve credit' ,
								'wp-sms'
							)
						);
						break;
					case 1402:
					case 1403:
						return new WP_Error(
							'required-class' ,
							__(
								'send number not found' ,
								'wp-sms'
							)
						);
						break;
					case 2209:
						return new WP_Error(
							'required-class' ,
							__(
								'user info not found' ,
								'wp-sms'
							)
						);
						break;
					case 2218:
						return new WP_Error(
							'required-class' ,
							__(
								'configs for user not found' ,
								'wp-sms'
							)
						);
						break;
					case 2001:
						return new WP_Error(
							'required-class' ,
							__(
								'server internal error to send message' ,
								'wp-sms'
							)
						);
						break;
					case 1203:
						return new WP_Error(
							'required-class' ,
							__(
								'insuffiecient credit' ,
								'wp-sms'
							)
						);
						break;
					case 1502:
						return new WP_Error(
							'required-class' ,
							__(
								'pattern not found' ,
								'wp-sms'
							)
						);
						break;
					case 1503:
						return new WP_Error(
							'required-class' ,
							__(
								'invalid params for pattern' ,
								'wp-sms'
							)
						);
						break;
					
					
				}
			}
			
			return $description;
		}
	}
