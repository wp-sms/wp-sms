<?php

namespace WP_SMS\Gateway;

class malath extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://sms.malath.net.sa/";
    public $tariff = "https://sms.malath.net.sa/";
    public $unitrial = false;
    public $unit;
    public $flash = "false";
    public $isflash = false;
    private $TextEncode = "UTF-8";

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "e.g. 96xxxxxxxxxx (Should be 12 digits)";
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

        // Get the credit.
        $credit = $this->GetCredit();

        // Check gateway credit
        if (is_wp_error($credit)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $credit->get_error_message(), 'error');

            return $credit;
        }

        $MSG_Count = $this->Count_MSG($this->msg);

        // 1010 -> SMS Text Grater that 6 part .
        if ($MSG_Count > 6) {
            return 1010;
        }

        if ($this->IsItUnicode($this->msg)) {
            $this->msg = bin2hex(mb_convert_encoding($this->msg, 'utf-16', 'utf-8'));
            $UC        = 'U';
        } else {
            $UC = 'E';
        }

        $args = array(
            'timeout' => 60,
        );

        $response = wp_remote_get(add_query_arg([
            'username' => $this->username,
            'password' => $this->password,
            'mobile'   => implode(',', $this->to),
            'message'  => urlencode($this->msg),
            'sender'   => $this->from,
            'unicode'  => $UC,
        ], $this->wsdl_link . 'httpSmsProvider.aspx'), $args);

        // Check gateway credit
        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new \WP_Error('send-sms', $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code == '200') {

            $result = $response['body'];

            // Log the result
            $this->log($this->from, $this->msg, $this->to, $result);

            /**
             * Run hook after send sms.
             *
             * @param string $result result output.
             * @since 2.4
             *
             */
            do_action('wp_sms_send', $result);

            return $result;
        } else {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response['body'], 'error');

            return new \WP_Error('send-sms', $response['body']);
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username or !$this->password) {
            return new \WP_Error('account-credit', __('API username or API password is not entered.', 'wp-sms'));
        }

        $response = wp_remote_get(add_query_arg([
            'username' => $this->username,
            'password' => $this->password,
        ], $this->wsdl_link . 'api/getBalance.aspx'));

        // Check gateway credit
        if (is_wp_error($response)) {
            return new \WP_Error('account-credit', $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code == '200') {
            if (strstr($response['body'], 'Error')) {
                return new \WP_Error('account-credit', $response['body']);
            }
            return $response['body'];
        } else {
            return new \WP_Error('account-credit', $response['body']);
        }

        return true;
    }

    private function ToUnicode($Text)
    {

        $Backslash = "\ ";
        $Backslash = trim($Backslash);

        $UniCode = array
        (
            "¡"        => "060C",
            "º"        => "061B",
            "¿"        => "061F",
            "Á"        => "0621",
            "Â"        => "0622",
            "Ã"        => "0623",
            "Ä"        => "0624",
            "Å"        => "0625",
            "Æ"        => "0626",
            "Ç"        => "0627",
            "È"        => "0628",
            "É"        => "0629",
            "Ê"        => "062A",
            "Ë"        => "062B",
            "Ì"        => "062C",
            "Í"        => "062D",
            "Î"        => "062E",
            "Ï"        => "062F",
            "Ð"        => "0630",
            "Ñ"        => "0631",
            "Ò"        => "0632",
            "Ó"        => "0633",
            "Ô"        => "0634",
            "Õ"        => "0635",
            "Ö"        => "0636",
            "Ø"        => "0637",
            "Ù"        => "0638",
            "Ú"        => "0639",
            "Û"        => "063A",
            "Ý"        => "0641",
            "Þ"        => "0642",
            "ß"        => "0643",
            "á"        => "0644",
            "ã"        => "0645",
            "ä"        => "0646",
            "å"        => "0647",
            "æ"        => "0648",
            "ì"        => "0649",
            "í"        => "064A",
            "Ü"        => "0640",
            "ð"        => "064B",
            "ñ"        => "064C",
            "ò"        => "064D",
            "ó"        => "064E",
            "õ"        => "064F",
            "ö"        => "0650",
            "ø"        => "0651",
            "ú"        => "0652",
            "!"        => "0021",
            '"'        => "0022",
            "#"        => "0023",
            "$"        => "0024",
            "%"        => "0025",
            "&"        => "0026",
            "'"        => "0027",
            "("        => "0028",
            ")"        => "0029",
            "*"        => "002A",
            "+"        => "002B",
            ","        => "002C",
            "-"        => "002D",
            "."        => "002E",
            "/"        => "002F",
            "0"        => "0030",
            "1"        => "0031",
            "2"        => "0032",
            "3"        => "0033",
            "4"        => "0034",
            "5"        => "0035",
            "6"        => "0036",
            "7"        => "0037",
            "8"        => "0038",
            "9"        => "0039",
            ":"        => "003A",
            ";"        => "003B",
            "<"        => "003C",
            "="        => "003D",
            ">"        => "003E",
            "?"        => "003F",
            "@"        => "0040",
            "A"        => "0041",
            "B"        => "0042",
            "C"        => "0043",
            "D"        => "0044",
            "E"        => "0045",
            "F"        => "0046",
            "G"        => "0047",
            "H"        => "0048",
            "I"        => "0049",
            "J"        => "004A",
            "K"        => "004B",
            "L"        => "004C",
            "M"        => "004D",
            "N"        => "004E",
            "O"        => "004F",
            "P"        => "0050",
            "Q"        => "0051",
            "R"        => "0052",
            "S"        => "0053",
            "T"        => "0054",
            "U"        => "0055",
            "V"        => "0056",
            "W"        => "0057",
            "X"        => "0058",
            "Y"        => "0059",
            "Z"        => "005A",
            "["        => "005B",
            $Backslash => "005C",
            "]"        => "005D",
            "^"        => "005E",
            "_"        => "005F",
            "`"        => "0060",
            "a"        => "0061",
            "b"        => "0062",
            "c"        => "0063",
            "d"        => "0064",
            "e"        => "0065",
            "f"        => "0066",
            "g"        => "0067",
            "h"        => "0068",
            "i"        => "0069",
            "j"        => "006A",
            "k"        => "006B",
            "l"        => "006C",
            "m"        => "006D",
            "n"        => "006E",
            "o"        => "006F",
            "p"        => "0070",
            "q"        => "0071",
            "r"        => "0072",
            "s"        => "0073",
            "t"        => "0074",
            "u"        => "0075",
            "v"        => "0076",
            "w"        => "0077",
            "x"        => "0078",
            "y"        => "0079",
            "z"        => "007A",
            "{"        => "007B",
            "|"        => "007C",
            "}"        => "007D",
            "~"        => "007E",
            "©"        => "00A9",
            "®"        => "00AE",
            "÷"        => "00F7",
            "×"        => "00F7",
            "§"        => "00A7",
            " "        => "0020",
            "\n"       => "000D",
            "\r"       => "000A",
            "\t"       => "0009",
            "é"        => "00E9",
            "ç"        => "00E7",
            "à"        => "00E0",
            "ù"        => "00F9",
            "µ"        => "00B5",
            "è"        => "00E8"
        );

        $Result = "";
        $StrLen = strlen($Text);
        for ($i = 0; $i < $StrLen; $i++) {

            $currect_char = substr($Text, $i, 1);

            if (array_key_exists($currect_char, $UniCode)) {
                $Result .= $UniCode[$currect_char];
            }

        }

        return $Result;
    }

    public function StrLen($Text)
    {
        if ($this->TextEncode == 'UTF-8')
            $Text = iconv('UTF-8', 'WINDOWS-1256', $Text);

        return strlen($Text);
    }

    public function Count_MSG($Text)
    {
        $StrLen  = StrLen($Text);
        $MSG_Num = 0;

        if ($this->IsItUnicode($Text)) {
            if ($StrLen > 70) {
                while ($StrLen > 0) {
                    $StrLen -= 67;
                    $MSG_Num++;
                }
            } else {
                $MSG_Num++;
            }
        } else {
            if ($StrLen > 160) {
                while ($StrLen > 0) {
                    $StrLen -= 134;
                    $MSG_Num++;
                }
            } else {
                $MSG_Num++;
            }
        }

        return $MSG_Num;
    }

    public function IsItUnicode($Text)
    {

        $unicode = false;
        $str     = "ÏÌÍÎåÚÛÝÞËÕÖØßãäÊÇáÈíÓÔÙÒæÉìáÇÑÄÁÆÅáÅÃáÃÂáÂ¡º¿ÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÝÞßáãäå'©®÷×§æìíÜðñòóõöøú";

        for ($i = 0; $i <= strlen($str); $i++) {
            $strResult = substr($str, $i, 1);

            for ($R = 0; $R <= strlen($Text); $R++) {
                $msgResult = substr($Text, $R, 1);

                if ($strResult == $msgResult && $strResult)
                    $unicode = true;
            }
        }

        return $unicode;
    }

    public function setTextEncode($TextEncode)
    {
        $this->TextEncode = $TextEncode;
    }

    public function getTextEncode()
    {
        return $this->TextEncode;
    }
}