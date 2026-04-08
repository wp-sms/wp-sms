<?php

namespace WP_SMS\Notification;

use WP_Error;
use WP_SMS\Components\Logger;
use WP_SMS\Helper;

if (!defined('ABSPATH')) exit;

class Notification
{
    protected $variables = [];
    protected $optIn = true;

    /**
     * Stores the processed message after variable replacement
     * @var string|null
     */
    protected $parsedMessage = null;

    /**
     * Stores the processed variables after replacement
     * @var array
     */
    protected $parsedVariables = [];

    /**
     * Stores the original message passed to getOutputMessage
     * to detect if re-processing is needed
     * @var string|null
     */
    protected $parsedMessageOriginal = null;

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

        // Chokepoint normalization: this catches every form-plugin integration (CF7, Forminator,
        // Formidable, etc.) and any future caller that forgets to normalize. Short codes are
        // passed through unchanged. Note: third-party code listening on the wp_sms_to filter
        // and downstream hooks will receive canonical (E.164) values rather than raw input.
        // Failures are recorded so the dashboard "Recent normalization failures" panel can
        // surface silent integration failures to the admin.
        $source = apply_filters('wp_sms_normalization_source', 'unknown', $to, $message);
        $normalized = [];
        foreach ($to as $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $result = Helper::tryNormalizeToE164($value);
            if (!$result['success']) {
                Helper::recordNormalizationFailure($value, $source, $result['reason']);
            }
            $normalized[] = $result['value'];
        }
        $to = $normalized;

        if (!$this->optIn) {
            $optOutError = new WP_Error('opt-out', __('This number has opted out of receiving SMS notifications.', 'wp-sms'));
            if (is_callable([$this, 'failed'])) {
                $this->failed($to, $optOutError);
            }

            return $optOutError;
        }

        $this->processMessage($message);

        $finalMessage     = $this->parsedMessage;
        $messageVariables = $this->parsedVariables;

        $response = wp_sms_send($to, $finalMessage, $isFlash, $senderId, $mediaUrls, $messageVariables);

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

    /**
     * Get the final output message after processing all variables
     *
     * @param string $message The message template
     * @return string Processed message with variables replaced
     */
    public function getOutputMessage($message)
    {
        if ($this->parsedMessage === null || $this->parsedMessageOriginal !== $message) {
            $this->processMessage($message);
            $this->parsedMessageOriginal = $message;
        }

        return $this->parsedMessage;
    }

    public function printVariables()
    {
        $output = [];

        foreach ($this->variables as $key => $value) {
            if (is_string($value) && substr($value, 0, 3) === 'get') {
                $value = substr($value, 3);
                $value = preg_replace('/([A-Z])/', ' $1', $value);
                $value = trim($value);
            }

            if (!empty($value)) {
                $output[] = esc_html($value) . ': <code>' . esc_html($key) . '</code>';
            } else {
                $output[] = '<code>' . esc_html($key) . '</code>';
            }
        }

        return implode(' ', $output);
    }

    public function getVariables()
    {
        return $this->variables;
    }

    /**
     * Process the message and replace all registered variables including meta variables
     *
     * @param string $message The message template to process
     * @return void
     */
    protected function processMessage($message)
    {
        if (empty($message)) {
            $this->parsedMessage   = '';
            $this->parsedVariables = [];
            return;
        }

        $variables    = apply_filters('wp_sms_output_variables', $this->variables, $message);
        $finalMessage = $message;

        $replacedVars = [];

        foreach ($variables as $variable => $callBack) {
            $pos = strpos($message, $variable);
            if ($pos === false) continue;

            if (is_callable([$this, $callBack])) {
                try {
                    if (method_exists($this, $callBack)) {
                        $reflection = new \ReflectionMethod($this, $callBack);
                        if ($reflection->getNumberOfRequiredParameters() === 0) {
                            $replacement = $this->$callBack();
                        } else {
                            Logger::log("Skipping variable '{$variable}' because '{$callBack}' requires arguments.", 'warning');
                            continue;
                        }
                    } else {
                        $replacement = $this->$callBack();
                    }
                } catch (\Throwable $e) {
                    Logger::log('Variable replacement error: ' . $e->getMessage(), 'error');
                    continue;
                }
            } else {
                $replacement = $callBack;
            }

            if (is_array($replacement)) {
                $replacement = implode(', ', $replacement);
            }

            $cleanKey = trim($variable, '%');

            $replacedVars[$cleanKey] = [
                'value' => (string)$replacement,
                'pos'   => $pos,
            ];

            $finalMessage = str_replace($variable, (string)$replacement, $finalMessage);
        }

        preg_match_all("/%order_(meta|item_meta)_(.+?)%/", $message, $matches);
        $metaHandlers = [
            'meta'      => 'getMeta',
            'item_meta' => 'getItemMeta',
        ];

        foreach ($matches[0] as $index => $metaVariable) {
            $metaType = $matches[1][$index];
            $metaKey  = $matches[2][$index];

            if (!isset($metaHandlers[$metaType])) {
                Logger::log("Handler method for meta type '{$metaType}' not found.", 'warning');
                continue;
            }

            $handlerMethod = $metaHandlers[$metaType];
            if (!method_exists($this, $handlerMethod)) {
                Logger::log("Handler method '{$handlerMethod}' not found.", 'warning');
                continue;
            }

            try {
                $metaValue = $this->$handlerMethod($metaKey);

                if ($metaValue !== null) {
                    if (is_array($metaValue)) {
                        $metaValue = implode(', ', $metaValue);
                    }

                    $cleanKey = trim($metaVariable, '%');

                    $pos = strpos($message, $metaVariable);
                    if ($pos === false) continue;

                    $replacedVars[$cleanKey] = [
                        'value' => (string)$metaValue,
                        'pos'   => $pos,
                    ];

                    $finalMessage = str_replace($metaVariable, (string)$metaValue, $finalMessage);
                } else {
                    Logger::log("Meta value for '{$metaVariable}' is null or not found.", 'warning');
                }
            } catch (\Throwable $e) {
                Logger::log(json_encode([
                    'error'     => $e->getMessage(),
                    'meta_type' => $metaType,
                    'meta_key'  => $metaKey,
                    'variable'  => $metaVariable,
                ]), 'error');
            }
        }

        uasort($replacedVars, function ($a, $b) {
            return $a['pos'] <=> $b['pos'];
        });
        $orderedVars = [];
        foreach ($replacedVars as $k => $info) {
            $orderedVars[$k] = $info['value'];
        }

        $this->parsedMessage   = apply_filters('wp_sms_output_variables_message', $finalMessage, $message, $variables);
        $this->parsedVariables = $orderedVars;
    }

    /**
     * Get the array of processed variables after replacement
     *
     * @return array Key-value array of replaced variables
     */
    public function getOutputVariables()
    {
        return $this->parsedVariables;
    }
}
