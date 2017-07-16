<?php

class Ebulksms {

    private $base_url;
    private $username;
    private $api_key;
    private $error_code;
    private $error_message;
    public $messages;
    
    private $message_data;
    private $message;
    private $from;
    private $flash = 0;

    public function __construct($username, $api_key = null) {
        $this->username = $username;
        $this->api_key = $api_key;
        $this->base_url = 'http://api.ebulksms.com:8080/';
    }

    private function set_last_error($code, $message) {
        $this->error_code = $code;
        $this->error_message = $message;
    }

    public function get_last_error() {
        return array('code' => $this->error_code, 'message' => $this->error_message);
    }

    public function toString($array) {
        while (true) {
            if (is_array($array)) {
                $values = array_values($array);
                $values = $values[0];
            } else
                break;
        }
        return $array;
    }

    public function request($method, $data = array()) {
        $curl = curl_init($this->base_url . $method);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type: application/json"));
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_ENCODING, 'UTF-8');
        $resp['body'] = curl_exec($curl);
        $resp['code'] = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        return $this->output($resp);
    }

    public function getrequest($method, $arr_params = array()) {
        $response = array();
        $str_params = array();
        if (count($arr_params)) {
            foreach ($arr_params as $key => $value) {
                $str_params[] = $key . '=' . urlencode($value);
            }
        }
        $final_url = empty($str_params) ? $this->base_url . $method : $this->base_url . $method . '?' . implode('&', $str_params);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $final_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_ENCODING, "UTF-8");
        $response['body'] = curl_exec($ch);
        //$response['code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $response['body'];
    }

    public function get_service_status() {
        return true;
    }

    private function output($data) {
        //$data = json_decode($data, true);
        if ($data['code'] != 200) {
            $this->set_last_error($data['code'], $data['body']);
            return '';
        } else {
            return $data['body'];
        }
    }

    public function get_credits($email, $apikey) {
        $result = $this->getrequest("balance/{$email}/{$apikey}");
        return $result ? (int) $result : null;
    }

    public function get_apikey($email, $password) {
        $data = array(
            'username' => $email,
            'password' => $password,
        );
        $result = $this->request('getapikey.json', $data);
        return $result ? $this->toString($result) : null;
    }

    public function send() {
        $gsm = $this->message_data;
        $message = array(
            'sender' => $this->from,
            'messagetext' => $this->message,
            'flash' => "{$this->flash}",
        );

        $request = array('SMS' => array(
                'auth' => array(
                    'username' => $this->username,
                    'apikey' => $this->api_key
                ),
                'message' => $message,
                'recipients' => $gsm
        ));
        $json_data = json_encode($request);
        if ($json_data) {
            $response = $this->request('sendsms.json', $json_data);var_dump($response);
            $result = json_decode($response);
            return $result->response->status;
        } else {
            return null;
        }
    }

    public function add_recipients($arr_recipient, $country_code = '234') {
        $gsm = array();
        foreach ($arr_recipient as $recipient) {
            $mobilenumber = trim($recipient);
            if (substr($mobilenumber, 0, 1) == '0') {
                $mobilenumber = $country_code . substr($mobilenumber, 1);
            } elseif (substr($mobilenumber, 0, 1) == '+') {
                $mobilenumber = substr($mobilenumber, 1);
            }
            $generated_id = uniqid('int_', false);
            $generated_id = substr($generated_id, 0, 30);
            $gsm['gsm'][] = array('msidn' => $mobilenumber, 'msgid' => $generated_id);
        }
        $this->message_data = $gsm;
    }

    public function list_recipients($type = 'array') {
        if ($type === 'json') {
            return json_encode($this->message_data);
        } else if ($type === 'array') {
            return print_r($this->message_data, true);
        }
    }

    public function set_message($message) {
        $this->message = $message;
    }

    public function get_message() {
        return $this->message;
    }

    public function set_from($from) {
        $this->from = $from;
    }

    public function get_from() {
        return $this->from;
    }

    public function reset() {
        $this->message = '';
        $this->from = '';
        $this->message_data = array();
    }

    public function clear_recipients() {
        $this->message_data = array();
    }

}
