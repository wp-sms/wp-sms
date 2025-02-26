<?php

namespace WP_SMS\Admin\OnBoarding;

class StepFactory
{
    /**
     * The base namespace where all step classes are located.
     */
    const BASE_NAMESPACE = 'WP_SMS\\Admin\\OnBoarding\\Steps\\';

    /**
     * Creates an instance of a step using only the step name.
     *
     * @param string $stepName The step class name without the namespace.
     * @return StepAbstract
     * @throws \Exception If the class does not exist or is not a valid step.
     */
    public static function create($stepName, WizardManager $wizard)
    {
        $className = self::BASE_NAMESPACE . $stepName;

        if (!class_exists($className)) {
            throw new \Exception("Step class '{$className}' not found.");
        }

        $step = new $className($wizard);

        if (!$step instanceof StepAbstract) {
            throw new \Exception("The class '{$className}' must extend StepAbstract.");
        }

        return $step;
    }
}
