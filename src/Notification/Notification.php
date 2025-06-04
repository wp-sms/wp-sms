<?php

namespace WP_SMS\Notification;

use WP_Error;

class Notification
{
    protected $variables = [];
    protected $optIn = true;

    /**
     * @param $message
     * @param $to
     * @param array $mediaUrls
     * @param bool $isFlash
     * @param bool $senderId
     * @return string|WP_Error
     */
    public function send($message, $to, $mediaUrls = [], $isFlash = false, $senderId = false)
    {
        // Backward compatibility
        if (!is_array($to)) {
            $to = explode(',', $to);
        }

        if (!$this->optIn) {
            if (is_callable([$this, 'failed'])) {
                $this->failed($to, new WP_Error('opt-out', __('This number has opted out of receiving SMS notifications.', 'wp-sms')));
            }

            return;
        }

        $response = wp_sms_send($to, $this->getOutputMessage($message), $isFlash, $senderId, $mediaUrls);

        /**
         * If response is true, call success method
         */
        if (is_wp_error($response) && is_callable([$this, 'failed'])) {
            $this->failed($to, $response);
        } elseif (is_callable([$this, 'success'])) {
            $this->success($to);
        }

        // Return response
        return $response;
    }

    public function getOutputMessage($message)
    {
        if (empty($message)) {
            return $message;
        }

        /**
         * Filters the variables to replace in the message content
         *
         * @param array $variables Array containing message variables parsed from the argument.
         * @param string $content Default message content before replacing variables.
         *
         * @since 5.7.6
         *
         */
        $variables    = apply_filters('wp_sms_output_variables', $this->variables, $message);
        $finalMessage = $message;

        foreach ($variables as $variable => $callBack) {

            // First replace regular variables
            if (strpos($finalMessage, $variable) !== false) {
                $replacement = '';

                // Replace variable with callback
                if (is_callable([$this, $callBack])) {
                    try {
                        $reflection = new \ReflectionMethod($this, $callBack);
                        if ($reflection->getNumberOfRequiredParameters() === 0) {
                            $replacement = $this->$callBack();
                        } else {
                            \WP_SMS::log("Skipping variable '{$variable}' because '{$callBack}' requires arguments.", 'warning');
                            continue;
                        }
                    } catch (\Throwable $e) {
                        \WP_SMS::log('Variable replacement error: ' . $e->getMessage(), 'error');
                        continue;
                    }
                } else {
                    $replacement = $callBack;
                }

                if (is_array($replacement)) {
                    $replacement = implode(', ', $replacement);
                }

                $finalMessage = str_replace($variable, (string)$replacement, $finalMessage);
            }
        }

        // Replace meta variables
        preg_match_all("/%order_(meta|item_meta)_(.+?)%/", $finalMessage, $matches);

        // Map meta types to their corresponding retrieval methods
        $metaHandlers = [
            'meta'      => 'getMeta',
            'item_meta' => 'getItemMeta',
        ];

        foreach ($matches[0] as $index => $metaVariable) {
            $metaType = $matches[1][$index]; // 'meta' OR 'item_meta'
            $metaKey  = $matches[2][$index]; // key name

            // Retrieve value using corresponding handler method, if available
            if (isset($metaHandlers[$metaType]) && method_exists($this, $metaHandlers[$metaType])) {
                $handlerMethod = $metaHandlers[$metaType];

                try {
                    $metaValue = $this->$handlerMethod($metaKey);

                    if ($metaValue !== null) {
                        if (is_array($metaValue)) {
                            $metaValue = implode(', ', $metaValue);
                        }

                        $finalMessage = str_replace($metaVariable, (string)$metaValue, $finalMessage);
                    } else {
                        \WP_SMS::log("Meta value for '{$metaVariable}' is null or not found.", 'warning');
                    }
                } catch (\Throwable $e) {
                    \WP_SMS::log(json_encode([
                        'error'     => $e->getMessage(),
                        'meta_type' => $metaType,
                        'meta_key'  => $metaKey,
                        'variable'  => $metaVariable,
                    ]), 'error');
                }
            } else {
                \WP_SMS::log("Handler method for meta type '{$metaType}' not found.", 'warning');
            }
        }

        /**
         * Filters the final message content after replacing variables
         *
         * @param string $message Message content after replacing variables.
         * @param string $content Default message content before replacing variables.
         * @param array $variables Array containing message variables parsed from the argument.
         *
         * @since 5.7.6
         *
         */
        return apply_filters('wp_sms_output_variables_message', $finalMessage, $message, $variables);
    }

    public function printVariables()
    {
        return "<code>" . implode("</code> <code>", array_keys($this->variables)) . "</code>";
    }

    public function getVariables()
    {
        return $this->variables;
    }
}
