<?php
    require_once 'baseManager.class.php';
    
    class PTContact {
        public $id=null;
        public $listId=null;
        public $identifier=null;
        public $attributes=null;
    }
    
    class PTList {
        public $id=null;
        public $name=null;
    }
    
    class PTField {
        public $id=null;
        public $listId=null;
        public $name=null;
        public $type=null;
        public $format=null;
        public $value=null;
        
    }
    
    class ListsManager extends BaseManager {
        
        public static function addList ($newList) {
            authenticationManager::ensureLogin();
            if (!$newList->name) {
                die('Please specify a list name');
            } else {
            $curl = parent::getPostCurl(BaseManager::$baseURL."/lists", json_encode($newList));
            $result = curl_exec($curl);
            return $result;
            echo "$result\n";
            curl_close($curl);
            }
        }
        
        public static function getLists () {
            authenticationManager::ensureLogin();
            $curl = parent::getGetCurl(BaseManager::$baseURL.'/lists');
            $result = curl_exec($curl);
            echo "$result\n";
            return $result;
            curl_close($curl);
        }
        
        public static function getList ($listId) {
            authenticationManager::ensureLogin();
            $curl = parent::getGetCurl(BaseManager::$baseURL."/lists/$listId/contacts");
            $result = curl_exec($curl);
            echo "$result\n";
            return $result;
            curl_close($curl);
        }
        
        public static function delList ($listId) {
            authenticationManager::ensureLogin();
            $curl = parent::getDeleteCurl(BaseManager::$baseURL."/lists/$listId");
            $result = curl_exec($curl);
            echo "$result\n";
            return $result;
            curl_close($curl);
        }
        
        public static function addField ($newField) {
            authenticationManager::ensureLogin();
            if ( $newField->type != "STRING" && $newField->type != "DATE" && $newField->type != "NUMBER") {
                die("Error: Please choose a type between: STRING, DATE OR NUMBER.\n");
            } else if (! $newField->format) {
                unset($newField->format);
            }
            $listId = $newField->listId;
            unset($newField->id);unset($newField->listId);unset($newField->value);
            $curl = parent::getPostCurl(BaseManager::$baseURL."/lists/$listId/fields", json_encode($newField));
            $result = curl_exec($curl);
            return $result;
            curl_close($curl);
        }
        
        public static function delField ($listId, $fieldId) {
            authenticationManager::ensureLogin();
            $curl = parent::getDeleteCurl(BaseManager::$baseURL."/lists/$listId/fields/$fieldId");
            $result = curl_exec($curl);
            echo "$result\n";
            return $result;
            curl_close($curl);
        }

        public static function getFields ($listId) {
            authenticationManager::ensureLogin();
            $curl = parent::getGetCurl(BaseManager::$baseURL."/lists/$listId/fields");
            $result = curl_exec($curl);
            echo "$result\n";
            return $result;
            curl_close($curl);
        }
        
        public static function addContact($newContact) {
            authenticationManager::ensureLogin();
            if (!$newContact->attributes) {
                unset($newContact->attributes);
            }
            $listId = $newContact->listId;unset($newContact->listId);
            $curl = parent::getPostCurl(BaseManager::$baseURL."/lists/$listId/contacts", json_encode($newContact));
            $result = curl_exec($curl);
            echo "$result\n";
            return $result;
            curl_close($curl);
        }
        
        public static function delContact ($newContact) {
            authenticationManager::ensureLogin();
            if (! $newContact->listId) {
                die('Error: Please define a listId to act.');
            } 
            if ($newContact->id) {
                $request = '/lists/'.$newContact->listId.'/contacts/'.$newContact->id;
            } elseif ($newContact->identifier) {
                $request = '/lists/'.$newContact->listId.'/contacts?identifier='.urlencode($newContact->identifier);
            } else {
                die('Error: Please choose between id OR identifier to delete contact.');
            }
            $curl = parent::getDeleteCurl(BaseManager::$baseURL.$request);
            $result = curl_exec($curl);
            echo "$result\n";
            return $result;
            curl_close($curl);
        }
        
        public static function getContacts($listId) {
            authenticationManager::ensureLogin();
            $curl = parent::getGetCurl(BaseManager::$baseURL.'/lists/'.$listId.'/contacts');
            $result = curl_exec($curl);
            echo "$result\n";
            return $result;
            curl_close($curl);
        }
    }
?>
