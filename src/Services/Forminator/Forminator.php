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

    /**
     * Forms that have already been handled in this request, keyed by form ID.
     *
     * A form submitted with AJAX enabled fires after_save_entry; the same form with AJAX
     * turned off fires after_handle_submit instead. We listen to both so the integration
     * works either way, and this guards against the rare case where one request triggers
     * both, so a recipient is never texted twice.
     *
     * @var array<int, bool>
     */
    private static $handledForms = [];

    public function init()
    {
        add_action("forminator_form_draft_after_save_entry", array($this, 'handle_sms'), 10, 2);
        add_action("forminator_form_after_save_entry", array($this, 'handle_sms'), 10, 2);

        // A form with AJAX submission turned off reloads the page instead of posting in the
        // background, and Forminator runs that through a different path that never fires
        // after_save_entry. The entry is still saved and the admin email still goes out, so
        // it looks exactly like a working form with a broken SMS. Listening here as well
        // means the SMS is sent whether or not the form uses AJAX.
        add_action("forminator_form_after_handle_submit", array($this, 'handle_sms'), 10, 2);

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
        // Forminator fires this event for every submission attempt, including one it rejected
        // for a missing or invalid field. Nothing is saved in that case, so sending would text
        // a visitor who never got through the form, and charge for it.
        if (!self::submissionSucceeded($res)) {
            Logger::log(sprintf('Forminator rejected the submission for form %d, so no SMS was sent.', $form), 'info');

            return;
        }

        // We listen to both the AJAX and the page-reload submission events. They do not fire
        // together today, but guarding here means a future change or an odd setup can never
        // text the same recipient twice for one submission.
        if (isset(self::$handledForms[$form])) {
            return;
        }
        self::$handledForms[$form] = true;

        $sms_options = Option::getOptions();
        $this->set_data();

        // Record the form that was actually submitted. Settings are stored per form ID, so a
        // site with several similar forms can easily have SMS set up on one form while
        // visitors fill in another. Without this line that mismatch is invisible: the entry
        // saves, the email notification goes out, and nothing explains the silent Outbox.
        $isConfigured = !empty($sms_options['forminator_notify_enable_form_' . $form])
            || !empty($sms_options['forminator_notify_enable_field_form_' . $form]);

        Logger::log(
            $isConfigured
                ? sprintf('Forminator submission received for form %d, which has SMS enabled.', $form)
                : sprintf('Forminator submission received for form %d, but no SMS is enabled for that form. Check that the form you set up in WP SMS is the one on the page.', $form),
            $isConfigured ? 'info' : 'warning'
        );

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

    /**
     * Whether Forminator actually accepted and saved this submission.
     *
     * Older Forminator releases pass a response we cannot read, so anything that is not an
     * explicit failure counts as a success. That keeps working sites working.
     *
     * @param mixed $res Forminator response for the submission.
     *
     * @return bool
     */
    private static function submissionSucceeded($res)
    {
        if (is_array($res) && array_key_exists('success', $res)) {
            return (bool) $res['success'];
        }

        if (is_object($res) && isset($res->success)) {
            return (bool) $res->success;
        }

        return true;
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
