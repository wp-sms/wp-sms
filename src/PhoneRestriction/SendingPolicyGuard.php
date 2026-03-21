<?php

namespace WSms\PhoneRestriction;

defined('ABSPATH') || exit;

class SendingPolicyGuard
{
    public function __construct(
        private readonly CountryResolver $countryResolver,
        private readonly RestrictionSettings $settings,
    ) {
    }

    /**
     * Check if a phone number is allowed for the given context.
     *
     * @param string $e164Phone The phone number in E.164 format
     * @param string $context   'auth' or 'messaging'
     */
    public function check(string $e164Phone, string $context = 'messaging'): RestrictionResult
    {
        $resolved = $this->countryResolver->resolve($e164Phone);
        $country  = $resolved['country'];

        // Can't determine country — fail open
        if ($country === null) {
            return RestrictionResult::allowed();
        }

        // Check country restriction
        if ($this->settings->isRestrictionEnabled($context)) {
            $mode   = $this->settings->getMode($context);
            $list   = $this->settings->getAllowedCountries($context);
            $inList = in_array($country, $list, true);

            $blocked = ($mode === 'block') ? $inList : !$inList;

            if ($blocked) {
                return RestrictionResult::blocked(
                    'country_blocked',
                    __('This phone number cannot be used for verification.', 'wp-sms'),
                    $country,
                );
            }
        }

        // Check number type blocking (requires enhanced DB)
        $numberType = $resolved['number_type'];

        if ($numberType !== null && $this->settings->isNumberTypeBlockingEnabled()) {
            $blockedTypes = $this->settings->getBlockedNumberTypes();

            if (in_array($numberType, $blockedTypes, true)) {
                return RestrictionResult::blocked(
                    $numberType,
                    __('This phone number cannot be used for verification.', 'wp-sms'),
                    $country,
                    $numberType,
                );
            }
        }

        return RestrictionResult::allowed($country, $numberType);
    }

    public function isAllowedForAuth(string $e164Phone): RestrictionResult
    {
        return $this->check($e164Phone, 'auth');
    }

    public function isAllowedForMessaging(string $e164Phone): RestrictionResult
    {
        return $this->check($e164Phone, 'messaging');
    }
}
