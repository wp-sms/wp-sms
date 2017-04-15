<?php
    require_once 'baseManager.class.php';
    
    class Blacklist {
        public $type;
        public $identifier;
        public $id;
    }
    class accountManager extends baseManager {

        public static function accountStats () {
            authenticationManager::ensureLogin();
            $curl = parent::getGetCurl(BaseManager::$baseURL.'/account/stats');
            $result = curl_exec($curl);
            return("$result\n");
            curl_close($curl);
        }
        
        public static function accountBlacklists ($blacklist) {
            authenticationManager::ensureLogin();
            if (!$blacklist->type || !$blacklist->type == 'unsubscribers' && !$blacklist->type == 'bounces') {
                die('Error: You need to specify a Type: unsubscribers OR bounces');
            }
            $curl = parent::getGetCurl(BaseManager::$baseURL."/$blacklist->type/default/contacts");
            $result = curl_exec($curl);
            echo("$result\n");
            curl_close($curl);    
        }
        
        public static function accountBlacklistsAdd ($blacklist) {
            authenticationManager::ensureLogin();
            if (!$blacklist->type || !$blacklist->type == 'unsubscribers' && !$blacklist->type == 'bounces') {
                die('Error: You need to specify a Type: unsubscribers OR bounces');
            }
            if (!$blacklist->identifier) {
                die('Error: You need to specify a contact identifier');
            }
            $type = $blacklist->type; unset($blacklist->type);
            $curl = parent::getPostCurl(BaseManager::$baseURL."/$type/default/contacts", json_encode($blacklist));
            $result = curl_exec($curl);
            echo("$result\n");
            curl_close($curl);    
        }
        
        public static function accountBlacklistsDel ($blacklist) {
            authenticationManager::ensureLogin();
            if (!$blacklist->type || !$blacklist->type == 'unsubscribers' && !$blacklist->type == 'bounces') {
                die('Error: You need to specify a Type: unsubscribers OR bounces');
            }
            if (!$blacklist->identifier) {
                die('Error: You need to specify a contact identifier by $blacklist->identifier = \'+33600000000\'');
            }
            $curl = parent::getDeleteCurl(BaseManager::$baseURL."/$type/default/contacts?identifier=".urlencode($blacklist->identifier));
            $result = curl_exec($curl);
            echo("$result\n");
            curl_close($curl);    
        }        
    }
?>
