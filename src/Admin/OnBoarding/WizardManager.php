<?php

namespace WP_SMS\Admin\OnBoarding;

use WP_SMS\Components\Assets;
use WP_SMS\Components\View;
use WP_SMS\Utils\Request;
use WP_SMS\Notice\NoticeManager;

class WizardManager
{
    private $steps = array();
    private $currentStep;
    private $title;
    public $slug;

    public function __construct($title, $slug)
    {
        $this->title = $title;
        $this->slug  = $slug;

    }

    public function setup()
    {
        if (!$this->isOnboarding()) {
            return;
        }

        $this->setCurrent();
        $this->enforceURL();
        add_action('admin_enqueue_scripts', array($this, 'enqueueScripts'));
        add_filter('admin_title', array($this, 'modifyOnboardingTitle'), 10, 2);

        if (Request::has('action')) {
            $this->handle();
        }

        add_filter('wp_sms_send_sms_page_content', function ($content, $args) {
            $this->render();
        }, 10, 2);
    }

    public function modifyOnboardingTitle($admin_title, $title)
    {
        if ($this->isOnboarding()) {
            $wizardTitle = $this->title;

            $stepTitle = method_exists($this->currentStep, 'getTitle') ? $this->currentStep->getTitle() : '';

            $customTitle = $stepTitle;
            if ($stepTitle) {
                $customTitle .= ' › ' . $wizardTitle;
            }

            return sprintf('%s › %s', $customTitle, get_bloginfo('name'));
        }

        return $admin_title;
    }

    public function enqueueScripts()
    {
        $localization = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('wp_sms_test_gateway_status'),
            'step'     => Request::get('step'),
            'slug'     => $this->slug,
            'next_url' => WizardHelper::generateNextStepUrl($this->currentStep->getSlug(), $this->slug),
            'prev_url' => WizardHelper::generatePreviousStepUrl($this->currentStep->getSlug(), $this->slug),
        );

        Assets::style('onboarding-style', 'css/onboarding.min.css');
        Assets::script('onboarding-script', 'js/onboarding.min.js', array('jquery', 'wpsms-select2'), $localization);
    }

    public function add(StepAbstract $step)
    {
        $this->steps[$step->getSlug()] = $step;
    }

    public function render()
    {
        $data = array(
            'current'  => $this->currentStep->getSlug(),
            'previous' => $this->getPrevious(),
            'next'     => WizardHelper::generateStepUrl($this->getNext(), $this->slug),
            'ctas'     => $this->getCTAs(),
            'index'    => $this->getStepIndex() + 1,
            'steps'    => $this->getStepsData(),
            'slug'     => $this->slug,
            'is_last'  => $this->isLastStep()
        );

        if (method_exists($this->currentStep, 'extraData')) {
            $data['extra'] = $this->currentStep->extraData();
        }

        View::load('templates/layout/onboarding/header', $data);
        $this->currentStep->render($data);
        View::load('templates/layout/onboarding/footer', $data);
    }

    private function getNext()
    {
        return $this->getAdjacentStep(1);
    }

    private function getPrevious()
    {
        return $this->getAdjacentStep(-1);
    }

    private function getStepIndex()
    {
        return array_search($this->currentStep->getSlug(), array_keys($this->steps));
    }

    private function setCurrent()
    {
        $stepSlug          = Request::get('step');
        $this->currentStep = ($stepSlug && isset($this->steps[$stepSlug])) ? $this->steps[$stepSlug] : reset($this->steps);
    }

    public function isOnboarding()
    {
        return Request::get('page') === 'wp-sms' && Request::get('path') === $this->slug;
    }

    private function getCTAs()
    {
        $CTAs = array();

        if ($prev = $this->getPrevious()) {
            $CTAs['back'] = array('url' => WizardHelper::generatePreviousStepUrl($this->currentStep->getSlug(), $this->slug), 'text' => __('Back', 'wp-sms'));
        }

        if ($next = $this->getNext()) {
            $CTAs['next'] = array('url' => WizardHelper::generateNextStepUrl($this->currentStep->getSlug(), $this->slug), 'text' => __('Continue', 'wp-sms'));
        }

        if (method_exists($this->currentStep, 'getCTAs')) {
            $CTAs = array_merge($CTAs, $this->currentStep->getCTAs());
        }

        return apply_filters("wp_sms_{$this->slug}_onboarding_ctas", $CTAs);
    }

    private function enforceURL()
    {
        if (!Request::get('step')) {
            WizardHelper::redirectToStep($this->slug, $this->currentStep->getSlug());
        }
    }

    public function isLastStep()
    {
        $keys         = array_keys($this->steps);
        $lastStepSlug = end($keys);
        return $this->currentStep->getSlug() === $lastStepSlug;
    }

    private function handle()
    {
        $action = Request::get('action');

        if ($action === 'next') {
            $this->processAndRedirect();
        } elseif ($action === 'previous') {
            WizardHelper::redirectToStep($this->slug, $this->getPrevious());
        }
    }

    private function processAndRedirect()
    {
        $errors = $this->process();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!empty($errors) && is_array($errors)) {
                $noticeManager = NoticeManager::getInstance();
                foreach ($errors as $errorArray) {
                    foreach ($errorArray as $errorItem) {
                        $noticeManager->registerNotice('wizard_error_' . uniqid(), $errorItem, false);
                    }
                }
            } else {
                WizardHelper::redirectToStep($this->slug, $this->getNext());
            }
        } else {
            wp_redirect(remove_query_arg('action', WizardHelper::generateStepUrl($this->currentStep->getSlug(), $this->slug)));
            exit;
        }
    }

    private function process()
    {
        return $this->currentStep->process() ?: $this->currentStep->isCompleted();
    }

    private function getAdjacentStep($offset)
    {
        $keys  = array_keys($this->steps);
        $index = array_search($this->currentStep->getSlug(), $keys) + $offset;
        return isset($keys[$index]) ? $keys[$index] : null;
    }

    private function getStepsData()
    {
        $steps = $this->steps;
        foreach ($steps as $slug => $object) {
            $stepsData[] = [
                'title' => $object->title,
                'url'   => \WP_SMS\Admin\OnBoarding\WizardHelper::generateStepUrl($slug, $this->slug)
            ];
        }

        return $stepsData;
    }
}
