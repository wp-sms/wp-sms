<?php
    require_once 'authenticationManager.class.php';
    require_once 'accountManager.class.php';
    require_once 'messagesManager.class.php';
    require_once 'campaignsManager.class.php';
    require_once 'listsManager.class.php';

    // cURL Verify
    
    function _isCurl(){
        return function_exists('curl_version');
    }

    if ( ! _iscurl()) {
        die ('cURL is NOT installed !'); 
    }	

    class baseManager {

        
        protected static $baseURL = 'https://api.primotexto.com/v2/';
        protected static $CURLOPT_SSL_VERIFYPEER = 'false';
        protected static $CURLOPT_PROXY = '';
        
        
        private static function getCurlWithApiKey($url) {
            $curl = curl_init($url);
            if (isSet(baseManager::$CURLOPT_PROXY)) {
                curl_setopt($curl, CURLOPT_PROXY, baseManager::$CURLOPT_PROXY);
            }
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, baseManager::$CURLOPT_SSL_VERIFYPEER);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'X-Primotexto-ApiKey: '.authenticationManager::getApiKey(),
            ));
            return $curl;
        }
        protected static function getPutCurl($url, $post_fields) {
            $curl = self::getCurlWithApiKey($url);
            if (isSet(baseManager::$CURLOPT_PROXY)) {
                curl_setopt($curl, CURLOPT_PROXY, baseManager::$CURLOPT_PROXY);
            }
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, baseManager::$CURLOPT_SSL_VERIFYPEER);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($curl, CURLOPT_POSTFIELDS, $post_fields);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            return $curl;
        }
	protected static function getPostCurl($url, $post_fields) {
            $curl = self::getCurlWithApiKey($url);
            if (isSet(baseManager::$CURLOPT_PROXY)) {
                curl_setopt($curl, CURLOPT_PROXY, baseManager::$CURLOPT_PROXY);
            }
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, baseManager::$CURLOPT_SSL_VERIFYPEER);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($curl, CURLOPT_POSTFIELDS, $post_fields);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            return $curl;
        }
	public static function getGetCurl($url) {
            $curl = self::getCurlWithApiKey($url);
            if (isSet(baseManager::$CURLOPT_PROXY)) {
                curl_setopt($curl, CURLOPT_PROXY, baseManager::$CURLOPT_PROXY);
            }
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, baseManager::$CURLOPT_SSL_VERIFYPEER);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            return $curl;
        }
        protected static function getDeleteCurl($url) {
            $curl = self::getCurlWithApiKey($url);
            if (isSet(baseManager::$CURLOPT_PROXY)) {
                curl_setopt($curl, CURLOPT_PROXY, baseManager::$CURLOPT_PROXY);
            }
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, baseManager::$CURLOPT_SSL_VERIFYPEER);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            return $curl;
        }
    }
?>
