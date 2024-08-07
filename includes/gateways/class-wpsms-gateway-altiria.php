<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;
use WP_SMS\Gateway;
use WP_SMS\Helper;

class altiria extends Gateway
{
    private $wsdl_link = "https://www.altiria.net:8443/apirest/ws";
    public $tariff = "http://www.altiria.net";
    public $unitrial = true;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = '“346xxxxxxxx (international format without + or 00)” for "International format without + or 00 (346xxxxxxxx for Spain, 52xxxxxxxxx por Mexico, 57xxxxxxxxx for Colombia etc)”';
    }

    public function SendSMS()
    {
        /**
         * Modify sender number
         *
         * @param string $this ->from sender number.
         * @since 3.4
         *
         */
        $this->from = apply_filters('wp_sms_from', $this->from);

        /**
         * Modify Receiver number
         *
         * @param array $this ->to receiver number
         * @since 3.4
         *
         */
        $this->to = apply_filters('wp_sms_to', $this->to);

        /**
         * Modify text message
         *
         * @param string $this ->msg text message.
         * @since 3.4
         *
         */
        $this->msg = apply_filters('wp_sms_msg', $this->msg);

        try {
            $this->to = Helper::removeNumbersPrefix(['+', '00'], $this->to);

            $body = array(
                'credentials' => [
                    'login'  => $this->username,
                    'passwd' => $this->password,
                ],
                'destination' => $this->to,
                'message'     => [
                    'msg'      => substr($this->msg, 0, 160),
                    'senderId' => $this->from,
                ],
            );

            $response = wp_remote_post($this->wsdl_link . '/sendSms', [
                'headers' => array(
                    'Content-Type' => 'application/json;charset=UTF-8'
                ),
                'body'    => wp_json_encode($body)
            ]);

            if (is_wp_error($response)) {
                return $response;
            }

            if (200 != wp_remote_retrieve_response_code($response)) {
                throw new Exception($response['body']);
            }

            $response = json_decode($response['body']);

            if ($response->status != '000') {
                throw new Exception($this->getErrorMessage($response->status));
            }

            $this->log($this->from, $this->msg, $this->to, $response);

            /**
             * Run hook after send sms.
             *
             * @param string $result result output.
             *
             * @since 2.4
             *
             */
            do_action('wp_sms_send', $response);

            return $response;

        } catch (Exception $e) {
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');

            return new WP_Error('send-sms', $e->getMessage());
        }
    }

    public function GetCredit()
    {
        $body = array(
            'credentials' => [
                'login'  => $this->username,
                'passwd' => $this->password,
            ],
        );

        $response = wp_remote_post($this->wsdl_link . '/getCredit', [
            'headers' => array(
                'Content-Type' => 'application/json;charset=UTF-8'
            ),
            'body'    => wp_json_encode($body)
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        if (200 != wp_remote_retrieve_response_code($response)) {
            return new WP_Error('account-credit', $response['body']);
        }

        $response = json_decode($response['body']);

        if ($response->status != '000') {
            return new WP_Error('account-credit', $this->getErrorMessage($response->status));
        }

        return $response->credit;
    }

    private function getErrorMessage($errorCode)
    {
        switch ($errorCode) {
            case "000":
                return "E ́xito";
                break;
            case "001":
                return "Error interno. Contactar con el soporte t ́ecnico";
                break;
            case "002":
                return "Error de acceso al puerto seguro 443. Contactar con el soporte t ́ecnico";
                break;
            case "010":
                return "Error en el formato del nu ́mero de tel ́efono";
                break;
            case "011":
                return "Error en el env ́ıo de los par ́ametros de la petici ́on o codificaci ́on incorrecta.";
                break;
            case "013":
                return "El mensaje excede la longitud m ́axima permitida";
                break;
            case "014":
                return "La petici ́on HTTP usa una codificaci ́on de caracteres inv ́alida";
                break;
            case "015":
                return "No hay destinatarios v ́alidos para enviar el mensaje";
                break;
            case "016":
                return "Destinatario duplicado";
                break;
            case "017":
                return "Mensaje vac ́ıo";
                break;
            case "018":
                return "Se ha excedido el m ́aximo nu ́mero de destinatarios autorizado";
                break;
            case "019":
                return "Se ha excedido el m ́aximo nu ́mero de mensajes autorizado";
                break;
            case "020":
                return "Error en la autentificaci ́on";
                break;
            case "033":
                return "El puerto destino del SMS es incorrecto";
                break;
            case "034":
                return "El puerto origen del SMS es incorrecto";
                break;
            case "035":
                return "La web m ́ovil enlazada en el mensaje no pertenece al usuario";
                break;
            case "036":
                return "La web m ́ovil enlazada en el mensaje no existe";
                break;
            case "037":
                return "Se ha excedido el m ́aximo nu ́mero de webs m ́oviles enlazadas en el mensaje Error de sintaxis en la definici ́on del env ́ıo con enlace a web m ́ovil";
                break;
            case "038":
                return "Error de sintaxis en la definici ́on del env ́ıo con enlace a web m ́ovil";
                break;
            case "039":
                return "Error de sintaxis en la definici ́on de los par ́ametros de la web m ́ovil parametrizada";
                break;
            default:
                return "Invalid error code";
                break;
        }
    }
}