<?php

namespace WP_SMS\Admin\OnBoarding;

use WP_SMS\Components\View;
use WP_SMS\Utils\Request;

class WizardManager
{
    private $steps = [];
    private $currentStep = null;
    private $title;
    private $slug;

    public function __construct($title, $slug)
    {
        $this->title = $title;
        $this->slug  = $slug;
    }

    public function setup()
    {
        $this->setCurrent();
        $this->enforceURL();
        add_action('admin_menu', [$this, 'registerPage']);

        if (Request::has('action') && $this->isOnboarding()) {
            $this->handle();
        }
    }

    public function registerPage()
    {
        add_dashboard_page(
            __($this->title, 'veronalabs-onboarding'),
            __($this->title, 'veronalabs-onboarding'),
            'manage_options',
            $this->slug,
            [$this, 'render']
        );
    }

    public function add(StepAbstract $step)
    {
        $this->steps[$step->getSlug()] = $step;
    }

    /**
     * @throws \Exception
     */
    public function render()
    {
        $data = [
            'current'  => $this->currentStep->getSlug(),
            'previous' => $this->getPrevious(),
            'next'     => $this->getNext(),
            'ctas'     => $this->getCTAs(),
            'index'    => $this->getStepIndex() + 1
        ];

        $step = $this->currentStep;
        View::load('templates/layout/onboarding/header', $data);
        $step->render($data);
        View::load('templates/layout/onboarding/footer', $data);
    }

    private function getNext()
    {
        $keys         = array_keys($this->steps);
        $currentIndex = array_search($this->currentStep->getSlug(), $keys);
        return $keys[$currentIndex + 1] ?? null;

    }

    private function getPrevious()
    {
        $keys         = array_keys($this->steps);
        $currentIndex = array_search($this->currentStep->getSlug(), $keys);
        return $keys[$currentIndex - 1] ?? null;
    }

    private function getStepIndex()
    {
        $keys = array_keys($this->steps);
        return array_search($this->currentStep->getSlug(), $keys);
    }

    private function setCurrent()
    {
        $currentStep       = isset($_GET['step']) ? sanitize_text_field($_GET['step']) : null;
        $this->currentStep = $currentStep && isset($this->steps[$currentStep]) ? $this->steps[$currentStep] : reset($this->steps);
    }

    private function isOnboarding()
    {
        return Request::has('page') && Request::get('page') == $this->slug;
    }

    private function getCTAs()
    {
        $CTAs = [];

        if (!empty($this->getPrevious())) {
            $CTAs['back'] = [
                'url' => WizardHelper::generatePreviousStepUrl($this->currentStep->getSlug(), $this->slug),
                'text' => __('Previous', 'wp-sms')
            ];
        }

        if (!empty($this->getNext())) {
            $CTAs['next'] = [
                'url' => WizardHelper::generateNextStepUrl($this->currentStep->getSlug(), $this->slug),
                'text' => __('Next', 'wp-sms')
            ];
        }

        if (method_exists($this->currentStep, 'getCTAs')) {
            $stepCTAs = array_merge($CTAs, $this->currentStep->getCTAs());
        }

        return apply_filters("wp_sms_{$this->slug}_onboarding_ctas", $CTAs);
    }

    private function enforceURL()
    {
        if (!$this->isOnboarding() || empty($this->steps)) {
            return;
        }

        // Ensure a valid current step
        if (!$this->currentStep || !isset($this->steps[$this->currentStep->getSlug()])) {
            $this->currentStep = reset($this->steps);
        }

        // Ensure 'step' exists in URL and matches the current step
        if (!Request::get('step')) {
            $firstIncompleteStep = null;

            // Find the first incomplete step
            foreach ($this->steps as $step) {
                if (!$step->isCompleted()) {
                    $firstIncompleteStep = $step;
                    break;
                }
            }

            $currentSlug = $this->currentStep->getSlug();

            // Redirect if the first incomplete step is different from the current step
            if ($firstIncompleteStep && $currentSlug !== $firstIncompleteStep->getSlug()) {
                WizardHelper::redirectToStep($this->slug, $firstIncompleteStep->getSlug());
                exit;
            }
            WizardHelper::redirectToStep($this->slug, $currentSlug);
            exit;
        }
    }

    private function handle()
    {
        switch (Request::get('action')) {
            case 'next':
                if ($this->process())
                    WizardHelper::redirectToStep($this->slug, $this->getNext());
            case 'previous':
                WizardHelper::redirectToStep($this->slug, $this->getPrevious());
        }
    }

    private function process()
    {
        return $this->currentStep->process() && $this->currentStep->isCompleted();
    }
}