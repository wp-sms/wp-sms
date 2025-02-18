<?php

namespace WP_SMS\Admin\OnBoarding;

use WP_SMS\Components\View;
use WP_SMS\Utils\Request;
use WP_SMS\Notice\NoticeManager;

class WizardManager
{
    private $steps = array();
    private $currentStep;
    private $title;
    private $slug;

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

        if (Request::has('action')) {
            $this->handle();
        }

        add_filter('wp_sms_send_sms_page_content', function ($content, $args) {
            $this->render();
        }, 10, 2);
    }

    public function enqueueScripts()
    {
        wp_enqueue_style('wp-sms-onboarding-style', WP_SMS_URL . 'assets/css/main.min.css', array(), '1.0.0');
        wp_enqueue_script('wp-sms-onboarding-script', WP_SMS_URL . 'assets/js/main.js', array('jquery', 'wpsms-select2'), '1.0.0', true);
    }

    public function add(StepAbstract $step)
    {
        $this->steps[$step->getSlug()] = $step;
    }

    public function render()
    {
        echo '<style>
            #wpadminbar, #adminmenu, #wpfooter, #adminmenuback, #screen-meta-links { display: none !important; }
            #wpcontent, #wpbody, #wpwrap { margin: 0 !important; padding: 0 !important; overflow: hidden; }
            #wpbody-content { padding-bottom: 0; }
            .wpsms-onboarding { width: 100vw; display: flex; flex-direction: column; justify-content: center; align-items: center; }
        </style>';

        $data = array(
            'current'  => $this->currentStep->getSlug(),
            'previous' => $this->getPrevious(),
            'next'     => $this->getNext(),
            'ctas'     => $this->getCTAs(),
            'index'    => $this->getStepIndex() + 1
        );

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

    private function isOnboarding()
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
        if (empty($this->steps) || !$this->currentStep->isCompleted()) {
            foreach ($this->steps as $step) {
                if (!$step->isCompleted()) {
                    WizardHelper::redirectToStep($this->slug, $step->getSlug());
                    return;
                }
            }
        }

        if (!Request::get('step')) {
            WizardHelper::redirectToStep($this->slug, $this->currentStep->getSlug());
        }
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
}
