<?php

namespace WP_SMS;

// @deprecated Legacy shim — prevents fatal errors in old add-ons.

class Newsletter
{
    public static function getGroups()
    {
        return [];
    }

    public static function getSubscribers($groups = [], $verified = false)
    {
        return [];
    }

    public static function getSubscriberByMobile($phoneNumber)
    {
        return null;
    }

    public static function addSubscriber($source, $phoneNumber, $groupId = null)
    {
        return false;
    }

    public static function deleteSubscriberByNumber($phoneNumber, $groupId = null)
    {
        return false;
    }

    public static function __callStatic($name, $arguments)
    {
        return null;
    }
}
