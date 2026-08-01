<?php

namespace WP_SMS\Services\Forminator;

use Forminator_API;
use WP_SMS\Components\Logger;
use WP_SMS\Notification\NotificationFactory;
use WP_SMS\Option;

if (!defined('ABSPATH')) exit;

class Forminator
{
    private $data;

    public function init()
    {
        add_action("forminator_form_draft_after_save_entry", array($this, 'handle_sms'), 10, 2);
        add_action("forminator_form_after_save_entry", array($this, 'handle_sms'), 10, 2);

        // Forminator builds the hook name from the submission status, so a submission
        // it treats as spam or abandoned fires a different event and never reaches
        // handle_sms(). Not sending those is intentional, but the skip is otherwise
        // invisible: the entry is saved and the email notification still goes out
        // while the Outbox stays empty, which looks exactly like a broken integration.
        add_action("forminator_form_spam_after_save_entry", array($this, 'log_skipped_sms'), 10, 2);
        add_action("forminator_form_abandoned_after_save_entry", array($this, 'log_skipped_sms'), 10, 2);
    }

    /**
     * Records that a submission was skipped because Forminator flagged it, and why.
     *
     * @param int $form Form ID.
     * @param array $res Forminator response.
     * @return void
     */
    public function log_skipped_sms($form, $res)
    {
        $sms_options = Option::getOptions();

        // Only report forms that were actually set up to send. Without this, spam on
        // any unrelated form would fill the log with lines the site owner cannot act on.
        $isConfigured = !empty($sms_options['forminator_notify_enable_form_' . $form])
            || !empty($sms_options['forminator_notify_enable_field_form_' . $form]);

        if (!$isConfigured) {
            return;
        }

        $status = strpos((string)current_action(), '_spam_') !== false ? 'spam' : 'abandoned';

        Logger::log(
            sprintf('Forminator submission for form %d was flagged as %s by Forminator, so no SMS was sent.', $form, $status),
            'warning'
        );
    }

    public function handle_sms($form, $res)
    {
        $sms_options = Option::getOptions();
        $this->set_data();

        $forminatorNotification = NotificationFactory::getForminator($form, $this->data);

        /**
         * Send SMS to the specific number or subscribers' group
         */
        if (isset($sms_options['forminator_notify_enable_form_' . $form]) &&
            isset($sms_options['forminator_notify_message_form_' . $form])
        ) {

            $forminatorNotification->send(
                $sms_options['forminator_notify_message_form_' . $form],
                explode(',', $sms_options['forminator_notify_receiver_form_' . $form])
            );
        }

        if (isset($sms_options['forminator_notify_enable_field_form_' . $form]) &&
            isset($sms_options['forminator_notify_message_field_form_' . $form])
        ) {
            $receiverField = $sms_options['forminator_notify_receiver_field_form_' . $form] ?? '';

            if ($receiverField && isset($this->data[$receiverField])) {
                $forminatorNotification->send(
                    $sms_options['forminator_notify_message_field_form_' . $form],
                    $this->data[$receiverField]
                );
            } else {
                Logger::log(sprintf('Forminator receiver field "%s" was not found for form %d.', $receiverField, $form), 'warning');
            }
        }
    }

    private function set_data()
    {
        $submittedData = $_POST;

        if (class_exists('Forminator_CForm_Front_Action') &&
            property_exists('Forminator_CForm_Front_Action', 'prepared_data') &&
            !empty(\Forminator_CForm_Front_Action::$prepared_data) &&
            is_array(\Forminator_CForm_Front_Action::$prepared_data)
        ) {
            $submittedData = \Forminator_CForm_Front_Action::$prepared_data;
        }

        $this->data = [];

        foreach (wp_sms_sanitize_array($submittedData) as $index => $key) {
            if (is_array($key)) {
                $this->data[$index] = implode(', ', $key);
            } else {
                $this->data[$index] = $key;
            }
        }
    }

    public static function formFields($form)
    {
        $form_fields = Forminator_API::get_form_fields($form);
        $fields      = [];

        foreach ($form_fields as $field) {
            $fields[$field->slug] = $field->raw['field_label'];
        }

        return $fields;
    }
}
