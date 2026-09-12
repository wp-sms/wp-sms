<?php

namespace WP_SMS\Controller;

use Exception;
use WP_SMS\Option;
use WP_SMS\Services\Subscriber\SubscriberUtil;

if (!defined('ABSPATH')) exit;

class PublicSubscribeAjax extends AjaxControllerAbstract
{
    protected $action = 'wp_sms_subscribe';
    /**
     * Public endpoint: visitors subscribe to the SMS newsletter without logging in.
     *
     * @var string|null
     */
    protected $capability = null;
    public $requiredFields = [
        'name',
        'mobile',
    ];

    protected function run()
    {
        // Check GDPR consent if enabled
        if (Option::getOption('gdpr_compliance') === '1' && !$this->get('gdpr_consent')) {
            throw new Exception(esc_html__('Please accept the privacy checkbox to continue.', 'wp-sms'));
        }

        $name           = $this->get('name');
        $number         = $this->get('mobile');
        $customFields   = $this->get('custom_fields');
        $group_id       = $this->get('group_id', 0);
        $groups_enabled = Option::getOption('newsletter_form_groups');

        //  If admin enabled groups and user did not select any group, then return error
        if ($groups_enabled && !$group_id) {
            throw new Exception(esc_html__('Please select a specific group.', 'wp-sms'));
        }

        $result = SubscriberUtil::subscribe($name, $number, $group_id, $customFields);

        if (is_wp_error($result)) {
            $payload = self::formatValidationError($result);

            if (is_array($payload)) {
                wp_send_json_error($payload, 400);
            }

            throw new Exception($payload);
        }

        return wp_send_json_success($result);
    }

    /**
     * Turn a subscription validation error into what the form's JavaScript renders.
     *
     * The message is sent as plain text, not HTML-escaped: the frontend puts it in
     * the page with jQuery's .text(), which escapes on its own. Escaping here as
     * well used to double it, so an apostrophe reached the visitor as &#039;.
     *
     * @param \WP_Error $error
     *
     * @return string|array The message alone, or message plus validated actions.
     */
    public static function formatValidationError(\WP_Error $error)
    {
        $message   = (string) $error->get_error_message();
        $errorData = $error->get_error_data();
        $actions   = self::sanitizeValidationActions(
            is_array($errorData) && isset($errorData['actions']) ? $errorData['actions'] : array()
        );

        if ($actions) {
            return array(
                'message' => $message,
                'actions' => $actions,
            );
        }

        return $message;
    }

    /**
     * Validate actions attached to a subscription validation WP_Error.
     *
     * Developers can return an error from `wp_sms_mobile_number_validity` with
     * structured action data:
     *
     *     new \WP_Error('code', 'Plain message', array(
     *         'actions' => array(
     *             array('label' => 'Text START', 'href' => 'sms:+155****4567?body=START', 'type' => 'sms'),
     *         ),
     *     ));
     *
     * @param mixed $actions Candidate actions.
     *
     * @return array
     */
    public static function sanitizeValidationActions($actions)
    {
        if (!is_array($actions)) {
            return array();
        }

        $sanitized = array();

        foreach ($actions as $action) {
            if (
                !is_array($action) ||
                !isset($action['label'], $action['href']) ||
                !is_scalar($action['label']) ||
                !is_scalar($action['href'])
            ) {
                continue;
            }

            $label  = sanitize_text_field((string) $action['label']);
            $href   = trim((string) $action['href']);
            $scheme = strtolower((string) wp_parse_url($href, PHP_URL_SCHEME));

            if ($label === '' || !in_array($scheme, array('sms', 'mailto', 'https'), true)) {
                continue;
            }

            $href = esc_url_raw($href, array('sms', 'mailto', 'https'));

            if ($href === '') {
                continue;
            }

            $cleanAction = array(
                'label' => $label,
                'href'  => $href,
                'type'  => $scheme === 'sms' ? 'sms' : 'link',
            );

            if (isset($action['target']) && is_string($action['target']) && in_array($action['target'], array('_blank', '_self'), true)) {
                $cleanAction['target'] = $action['target'];
            }

            $allowedRel = array('nofollow', 'noopener', 'noreferrer', 'sponsored', 'ugc');
            $rel        = isset($action['rel']) && is_string($action['rel'])
                ? preg_split('/\s+/', strtolower(sanitize_text_field($action['rel'])))
                : array();
            $rel        = array_values(array_unique(array_intersect((array) $rel, $allowedRel)));

            if (isset($cleanAction['target']) && $cleanAction['target'] === '_blank') {
                $rel = array_values(array_unique(array_merge($rel, array('noopener', 'noreferrer'))));
            }

            if ($rel) {
                $cleanAction['rel'] = implode(' ', $rel);
            }

            $sanitized[] = $cleanAction;
        }

        return $sanitized;
    }
}
