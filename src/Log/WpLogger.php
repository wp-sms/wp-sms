<?php

namespace WSms\Log;

use WSms\Dependencies\Psr\Log\AbstractLogger;
use WSms\Dependencies\Psr\Log\LogLevel;

defined('ABSPATH') || exit;

/**
 * PSR-3 compatible logger using WordPress error_log().
 *
 * Follows PSR-3 spec: placeholder interpolation via {key} syntax,
 * exception context via the 'exception' key, and level filtering.
 * Can be swapped for Monolog with a 1-line container change.
 */
class WpLogger extends AbstractLogger
{
    private const LEVEL_MAP = [
        LogLevel::EMERGENCY => 7,
        LogLevel::ALERT     => 6,
        LogLevel::CRITICAL  => 5,
        LogLevel::ERROR     => 4,
        LogLevel::WARNING   => 3,
        LogLevel::NOTICE    => 2,
        LogLevel::INFO      => 1,
        LogLevel::DEBUG     => 0,
    ];

    public function __construct(
        private readonly string $channel = 'wsms',
        private readonly string $minLevel = LogLevel::DEBUG,
    ) {
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if ((self::LEVEL_MAP[$level] ?? 0) < (self::LEVEL_MAP[$this->minLevel] ?? 0)) {
            return;
        }

        $interpolated = $this->interpolate((string) $message, $context);

        // PSR-3 §1.3: exception context should be in the 'exception' key.
        $exceptionTrace = '';
        if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
            $e = $context['exception'];
            $exceptionTrace = sprintf(' [%s: %s at %s:%d]',
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
            );
            // Don't json_encode the exception object — it's not serializable.
            unset($context['exception']);
        }

        $contextJson = $context ? ' ' . wp_json_encode($context) : '';

        $formatted = sprintf('[%s] %s.%s: %s%s%s',
            gmdate('Y-m-d H:i:s'),
            $this->channel,
            strtoupper($level),
            $interpolated,
            $exceptionTrace,
            $contextJson,
        );

        error_log($formatted);
    }

    /**
     * PSR-3 §1.2: Placeholder names correspond to context keys.
     * Brace-delimited: "User {username} logged in" + ['username' => 'Alice']
     */
    private function interpolate(string $message, array $context): string
    {
        $replacements = [];
        foreach ($context as $key => $val) {
            if (is_scalar($val) || $val instanceof \Stringable) {
                $replacements['{' . $key . '}'] = (string) $val;
            }
        }
        return strtr($message, $replacements);
    }
}
