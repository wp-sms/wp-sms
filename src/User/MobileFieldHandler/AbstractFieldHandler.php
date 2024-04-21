<?php

namespace WP_SMS\User\MobileFieldHandler;

abstract class AbstractFieldHandler
{
    abstract public function register();

    abstract public function getMobileNumberByUserId($userId);

    abstract public function getUserMobileFieldName();
}
