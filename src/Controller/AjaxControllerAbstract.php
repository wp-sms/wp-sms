<?php

namespace WP_SMS\Controller;

abstract class AjaxControllerAbstract
{
    protected $action;
    public $request;
    public $wp;
    public $user;
    public $requiredFields = array();

    abstract protected function run();

    public function __construct()
    {
        global $wp;
        $this->wp      = $wp;
        $this->request = $_REQUEST;

        if ($this->isLoggedIn()) {
            $this->user = wp_get_current_user();
        }
    }

    public static function boot()
    {
        try {

            $class  = self::getClassName();

            /**
             * @var $action AjaxControllerAbstract
             */
            $action = new $class;

            // Check CSRF
            if (!wp_verify_nonce($action->get('_nonce'), $action->action)) {
                throw new \Exception(esc_html__('Access denied.', 'wp-sms'));
            }

            // Check required parameters
            if ($action->requiredFields) {
                foreach ($action->requiredFields as $item) {
                    if ($action->get($item) == null) {
                        // translators: %s: Field name
                        throw new \Exception(sprintf(esc_html__('Field %s is required.', 'wp-sms'), ucfirst($item)));
                    }
                }
            }

            $action->run();
            die();

        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage(), 400);
        }
    }

    public static function listen($public = true)
    {
        $actionName = self::getActionName();
        $className  = Self::getClassName();

        add_action("wp_ajax_{$actionName}", [$className, 'boot']);

        if ($public) {
            add_action("wp_ajax_nopriv_{$actionName}", [$className, 'boot']);
        }
    }

    public static function getClassName()
    {
        return get_called_class();
    }

    public static function getActionName()
    {
        return self::action();
    }

    public static function formURL()
    {
        return admin_url('/admin-ajax.php');
    }

    public static function action()
    {
        $class      = Self::getClassName();
        $reflection = new \ReflectionClass($class);
        $action     = $reflection->newInstanceWithoutConstructor();

        if (!isset($action->action)) {
            // translators: %s: Class name
            throw new Exception(sprintf(esc_html__('Public property %s not provided', 'wp-sms'), esc_html($class)));
        }

        return $action->action;
    }

    /**
     * @param array $params
     *
     * @return string
     */
    public static function url($params = array())
    {
        $action = (new static())->action;

        $params = http_build_query(array_merge(array(
            'action' => $action,
            '_nonce' => wp_create_nonce($action),
        ), $params));

        return admin_url('/admin-ajax.php') . '?' . $params;
    }

    public function isLoggedIn()
    {
        return is_user_logged_in();
    }

    public function has($key)
    {
        if (isset($this->request[$key])) {
            return true;
        }

        return false;
    }

    /**
     * @param $key
     * @param null $default
     *
     * @return mixed|string|null
     */
    public function get($key, $default = null)
    {
        if ($this->has($key)) {

            if (is_array($this->request[$key])) {
                return wp_sms_sanitize_array($this->request[$key]);
            }

            return sanitize_text_field($this->request[$key]);
        }

        return $default;
    }

    /**
     * @param null $requestType
     *
     * @return bool|mixed [type]
     */
    public function requestType($requestType = null)
    {
        if (!is_null($requestType)) {

            if (is_array($requestType)) {
                return in_array($_SERVER['REQUEST_METHOD'], array_map('strtoupper', $requestType));
            }

            return ($_SERVER['REQUEST_METHOD'] === strtoupper($requestType));
        }

        return $_SERVER['REQUEST_METHOD'];
    }
}
