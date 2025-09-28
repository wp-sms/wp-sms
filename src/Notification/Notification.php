<?php

namespace WP_SMS\Notification;

use WP_Error;

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

        if (!$this->optIn) {
            if (is_callable([$this, 'failed'])) {
                $this->failed($to, new WP_Error('opt-out', __('This number has opted out of receiving SMS notifications.', 'wp-sms')));
            }

            return;
        }

        $this->processMessage($message);

        $finalMessage   = $this->parsedMessage;
        $variablesArray = $this->parsedVariables;

        $response = wp_sms_send($to, $finalMessage, $isFlash, $senderId, $mediaUrls, $variablesArray);

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
        return "<code>" . implode("</code> <code>", array_keys($this->variables)) . "</code>";
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
            if (strpos($finalMessage, $variable) === false) continue;

            $replacement = '';

            if (is_callable([$this, $callBack])) {
                try {
                    if (method_exists($this, $callBack)) {
                        $reflection = new \ReflectionMethod($this, $callBack);
                        if ($reflection->getNumberOfRequiredParameters() === 0) {
                            $replacement = $this->$callBack();
                        } else {
                            \WP_SMS::log("Skipping variable '{$variable}' because '{$callBack}' requires arguments.", 'warning');
                            continue;
                        }
                    } else {
                        $replacement = $this->$callBack();
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

            $cleanKey                = trim($variable, '%');
            $replacedVars[$cleanKey] = (string)$replacement;
            $finalMessage            = str_replace($variable, (string)$replacement, $finalMessage);
        }

        preg_match_all("/%order_(meta|item_meta)_(.+?)%/", $finalMessage, $matches);
        $metaHandlers = [
            'meta'      => 'getMeta',
            'item_meta' => 'getItemMeta',
        ];

        foreach ($matches[0] as $index => $metaVariable) {
            $metaType = $matches[1][$index];
            $metaKey  = $matches[2][$index];

            if (!isset($metaHandlers[$metaType])) {
                \WP_SMS::log("Handler method for meta type '{$metaType}' not found.", 'warning');
                continue;
            }

            $handlerMethod = $metaHandlers[$metaType];
            if (!method_exists($this, $handlerMethod)) {
                \WP_SMS::log("Handler method '{$handlerMethod}' not found.", 'warning');
                continue;
            }

            try {
                $metaValue = $this->$handlerMethod($metaKey);

                if ($metaValue !== null) {
                    if (is_array($metaValue)) {
                        $metaValue = implode(', ', $metaValue);
                    }

                    $cleanKey                = trim($metaVariable, '%');
                    $replacedVars[$cleanKey] = (string)$metaValue;
                    $finalMessage            = str_replace($metaVariable, (string)$metaValue, $finalMessage);
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
        }

        $this->parsedMessage   = apply_filters('wp_sms_output_variables_message', $finalMessage, $message, $variables);
        $this->parsedVariables = $replacedVars;
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
