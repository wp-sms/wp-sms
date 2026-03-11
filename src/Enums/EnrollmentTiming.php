<?php

namespace WSms\Enums;

enum EnrollmentTiming: string
{
    case OnRegistration = 'on_registration';
    case GracePeriod = 'grace_period';
    case Voluntary = 'voluntary';
}
