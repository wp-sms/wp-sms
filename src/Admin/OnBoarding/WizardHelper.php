<?php

namespace WP_SMS\Admin\OnBoarding;

if (!defined('ABSPATH')) exit;

class WizardHelper
{
    public static function slugToClassName($slug)
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $slug)));
    }

    public static function generateStepUrl($stepSlug, $wizardSlug)
    {
        return admin_url("admin.php?page=wp-sms&path={$wizardSlug}&step={$stepSlug}");
    }

    public static function generateNextStepUrl($currentSlug, $wizardSlug)
    {
        return admin_url("admin.php?page=wp-sms&path={$wizardSlug}&step={$currentSlug}&action=next");
    }

    public static function generatePreviousStepUrl($currentSlug, $wizardSlug)
    {
        return admin_url("admin.php?page=wp-sms&path={$wizardSlug}&step={$currentSlug}&action=previous");
    }

    public static function redirectToStep($wizardSlug, $stepSlug)
    {
        $url = self::generateStepUrl($stepSlug, $wizardSlug);
        wp_redirect($url);
        exit;
    }
}