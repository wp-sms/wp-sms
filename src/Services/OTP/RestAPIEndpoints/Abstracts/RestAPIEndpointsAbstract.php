<?php

namespace WP_SMS\Services\OTP\RestAPIEndpoints\Abstracts;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_SMS\Services\OTP\AuthChannel\OTP\OtpService;
use WP_SMS\Services\OTP\Models\AuthEventModel;
use WP_SMS\Services\OTP\Security\RateLimiter;
use WP_SMS\Utils\DateUtils;
use WP_SMS\Services\OTP\Helpers\ChannelSettingsHelper;

abstract class RestAPIEndpointsAbstract
{
    /** @var OtpService */
    protected $otpService;
    
    /** @var RateLimiter */
    protected $rateLimiter;

    /** @var ChannelSettingsHelper */
    protected $channelSettingsHelper;

    public function __construct()
    {
        $this->otpService = new OtpService();
        $this->rateLimiter = new RateLimiter();
        $this->channelSettingsHelper = new ChannelSettingsHelper();
    }

    /**
     * Initialize the service
     */
    public function init(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    /**
     * Register REST API routes for OTP endpoints.
     * This method should be implemented by all subclasses.
     */
    abstract public function registerRoutes(): void;

    /**
     * Get client IP address from request headers
     *
     * @param WP_REST_Request $request
     * @return string
     */
    protected function getClientIp(WP_REST_Request $request): string
    {
        // Try different headers in order of preference
        $ipKeys = [
            'X-Forwarded-For',
            'X-Real-IP', 
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($ipKeys as $key) {
            $ip = $request->get_header($key);
            if ($ip) {
                // Handle comma-separated IPs (from proxies)
                $ips = explode(',', $ip);
                $ip = trim($ips[0]);
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Check rate limits for identifier and IP
     *
     * @param string $identifier
     * @param string $ip
     * @param string $action
     * @return bool|WP_Error
     */
    protected function checkRateLimits(string $identifier, string $ip, string $action = 'default'): bool|WP_Error
    {
        $rateKeyIdentifier = $action . ':identifier:' . md5($identifier);
        $rateKeyIp = $action . ':ip:' . md5($ip);

        if (!$this->rateLimiter->isAllowed($rateKeyIdentifier) || !$this->rateLimiter->isAllowed($rateKeyIp)) {
            return new WP_Error('rate_limited', __('Too many requests. Please try again later.', 'wp-sms'), ['status' => 429]);
        }

        return true;
    }

    /**
     * Increment rate limit counters
     *
     * @param string $identifier
     * @param string $ip
     * @param string $action
     * @return void
     */
    protected function incrementRateLimits(string $identifier, string $ip, string $action = 'default'): void
    {
        $rateKeyIdentifier = $action . ':identifier:' . md5($identifier);
        $rateKeyIp = $action . ':ip:' . md5($ip);

        $this->rateLimiter->increment($rateKeyIdentifier);
        $this->rateLimiter->increment($rateKeyIp);
    }

    /**
     * Log authentication event
     *
     * @param string $flowId
     * @param string $eventType
     * @param string $result
     * @param string $channel
     * @param string $ip
     * @param int|null $attempts
     * @param array $additionalData
     * @return void
     */
    protected function logAuthEvent(string $flowId, string $eventType, string $result, string $channel, string $ip, ?int $attempts = null, array $additionalData = []): void
    {
        try {
            $data = [
                'event_id' => wp_generate_uuid4(),
                'flow_id' => $flowId,
                'timestamp_utc' => DateUtils::getCurrentUtcDateTime(),
                'user_id' => null,
                'channel' => $channel,
                'event_type' => $eventType,
                'result' => $result,
                'client_ip_masked' => $ip,
                'retention_days' => 30,
            ];

            if ($attempts !== null) {
                $data['attempt_count'] = $attempts;
            }

            // Merge additional data
            $data = array_merge($data, $additionalData);

            AuthEventModel::insert($data);
        } catch (\Exception $e) {
            error_log("[WP-SMS] Failed to log auth event: " . $e->getMessage());
        }
    }

    /**
     * Create error response
     *
     * @param string $error
     * @param string $message
     * @param int $status
     * @param array $additionalData
     * @return WP_REST_Response
     */
    protected function createErrorResponse(string $error, string $message, int $status = 400, array $additionalData = []): WP_REST_Response
    {
        $response = [
            'error' => $error,
            'message' => $message
        ];

        if (!empty($additionalData)) {
            $response['data'] = $additionalData;
        }

        return new WP_REST_Response($response, $status);
    }

    /**
     * Create success response
     *
     * @param array $data
     * @param string $message
     * @param int $status
     * @return WP_REST_Response
     */
    protected function createSuccessResponse(array $data, string $message = 'Success', int $status = 200): WP_REST_Response
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data
        ];

        return new WP_REST_Response($response, $status);
    }

    /**
     * Validate identifier (phone number, email, etc.)
     *
     * @param mixed $value
     * @param WP_REST_Request $request
     * @param string $param
     * @return bool|WP_Error
     */
    public function validateIdentifier($value, $request, $param)
    {
        if (empty($value)) {
            return new WP_Error('invalid_identifier', __('Identifier is required', 'wp-sms'), ['status' => 400]);
        }

        // Basic identifier validation - can be phone number, email, or other identifier
        if (strlen($value) < 3) {
            return new WP_Error('invalid_identifier', __('Identifier must be at least 3 characters long', 'wp-sms'), ['status' => 400]);
        }

        return true;
    }

    /**
     * Validate phone number
     *
     * @param mixed $value
     * @param WP_REST_Request $request
     * @param string $param
     * @return bool|WP_Error
     */
    public function validatePhoneNumber($value, $request, $param)
    {
        if (empty($value)) {
            return new WP_Error('invalid_phone', __('Phone number is required', 'wp-sms'), ['status' => 400]);
        }

        // Basic phone number validation
        if (!preg_match('/^\+?[1-9]\d{1,14}$/', $value)) {
            return new WP_Error('invalid_phone', __('Invalid phone number format', 'wp-sms'), ['status' => 400]);
        }

        return true;
    }

    /**
     * Validate email address
     *
     * @param mixed $value
     * @param WP_REST_Request $request
     * @param string $param
     * @return bool|WP_Error
     */
    public function validateEmail($value, $request, $param)
    {
        if (empty($value)) {
            return new WP_Error('invalid_email', __('Email is required', 'wp-sms'), ['status' => 400]);
        }

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return new WP_Error('invalid_email', __('Invalid email format', 'wp-sms'), ['status' => 400]);
        }

        return true;
    }

    /**
     * Determine the best channel based on identifier type
     *
     * @param string $identifier
     * @param array $channelSettings
     * @return string
     */
    protected function determineChannel(string $identifier, array $channelSettings): string
    {
        // Check if identifier is an email
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return isset($channelSettings['channels']['email']) ? 'email' : 'sms';
        }
        
        // Check if identifier looks like a phone number
        if (preg_match('/^\+?[1-9]\d{1,14}$/', $identifier)) {
            return isset($channelSettings['channels']['sms']) ? 'sms' : 'email';
        }
        
        // Default to first available channel
        $availableChannels = array_keys($channelSettings['channels'] ?? []);
        return $availableChannels[0] ?? 'sms';
    }

    /**
     * Extract and validate identifier from request
     *
     * @param WP_REST_Request $request
     * @return array|WP_Error
     */
    protected function extractAndValidateIdentifier(WP_REST_Request $request): array|WP_Error
    {
        $phone = $request->get_param('phone');
        $email = $request->get_param('email');
        $identifier = $request->get_param('identifier');

        // If identifier is provided, use it
        if ($identifier) {
            return [
                'value' => $identifier,
                'type' => filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone',
                'channel' => filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'sms',
                'phone' => filter_var($identifier, FILTER_VALIDATE_EMAIL) ? null : $identifier,
                'email' => filter_var($identifier, FILTER_VALIDATE_EMAIL) ? $identifier : null
            ];
        }

        // Fallback to separate phone/email parameters
        if (!$phone && !$email) {
            return new WP_Error('invalid_identifier', __('Phone number, email, or identifier is required.', 'wp-sms'), ['status' => 400]);
        }

        return [
            'value' => $phone ?: $email,
            'type' => $phone ? 'phone' : 'email',
            'channel' => $phone ? 'sms' : 'email',
            'phone' => $phone,
            'email' => $email
        ];
    }

    /**
     * Handle exceptions and return appropriate error response
     *
     * @param \Exception $e
     * @param string $context
     * @return WP_REST_Response
     */
    protected function handleException(\Exception $e, string $context = 'operation'): WP_REST_Response
    {
        // Log the error for debugging
        error_log("[WP-SMS] Error in {$context}: " . $e->getMessage());
        
        // Use the exception's code if it's a valid HTTP status code, otherwise default to 500
        $statusCode = $e->getCode();
        if ($statusCode < 100 || $statusCode >= 600) {
            $statusCode = 500;
        }
        
        // Return error response using exception message and code
        return $this->createErrorResponse(
            'operation_failed',
            $e->getMessage(),
            $statusCode
        );
    }
}

