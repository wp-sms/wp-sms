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
        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);

        if (Request::has('action') && $this->isOnboarding()) {
            $this->handle();
        }
    }

    public function enqueueScripts()
    {
        if (!Request::get('page') || Request::get('page') !== $this->slug) {
            return;
        }
        wp_enqueue_style(
            'wp-sms-onboarding-style',
            WP_SMS_URL . 'assets/css/onboarding.min.css',
            [],
            '1.0.0'
        );
        wp_enqueue_script(
            'wp-sms-onboarding-script',
            WP_SMS_URL . 'assets/js/onboarding.min.js',
            ['jquery', 'wpsms-select2'], // Ensure jQuery is loaded as a dependency
            '1.0.0',
            true
        );
    }


    public function registerPage()
    {
        add_dashboard_page(
            __($this->title, 'wp-sms'),
            __($this->title, 'wp-sms'),
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
                'url'  => WizardHelper::generatePreviousStepUrl($this->currentStep->getSlug(), $this->slug),
                'text' => __('Back', 'wp-sms')
            ];
        }

        if (!empty($this->getNext())) {
            $CTAs['next'] = [
                'url'  => WizardHelper::generateNextStepUrl($this->currentStep->getSlug(), $this->slug),
                'text' => __('Continue', 'wp-sms')
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
                $errors = $this->process();
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    if (is_array($errors) && !empty($errors)) {
                        // Use NoticeManager to display errors
                        $noticeManager = \WP_SMS\Notice\NoticeManager::getInstance();

                        foreach ($errors as $field => $errorArray) {
                            foreach ($errorArray as $errorItem) {
                                $noticeManager->registerNotice('wizard_error_' . uniqid(), $errorItem, false);
                            }
                        }
                    } else {
                        // Redirect to the next step
                        WizardHelper::redirectToStep($this->slug, $this->getNext());
                    }
                } else {
                    $redirectUrl = remove_query_arg('action', WizardHelper::generateStepUrl($this->currentStep->getSlug(), $this->slug));
                    wp_redirect($redirectUrl);
                    exit;
                }
                break;
            case 'previous':
                WizardHelper::redirectToStep($this->slug, $this->getPrevious());
                break;
        }
    }


    private function process()
    {
        $result = $this->currentStep->process();
        if (is_array($result) && !empty($result)) {
            return $result;
        }

        return $this->currentStep->isCompleted();
    }

}