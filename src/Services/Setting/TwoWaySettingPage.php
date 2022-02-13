<?php

namespace WPSmsTwoWay\Services\Setting;

use WPSmsTwoWay\Services\Gateway\GatewayManager;
use WPSmsTwoWay\Services\Webhook\Webhook;

class TwoWaySettingPage
{
    /**
     * Gateway manager instance
     *
     * @var WPSmsTwoWay\Services\Gateway\GatewayManager
     */
    private $gateway;

    /**
     * Setting data to be passed to wpsms settings api
     *
     * @var array
     */
    private $fields;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setGateway();
        $this->addStatusSection();
    }

    /**
     * Set active gateway
     *
     * @return void
     */
    private function setGateway()
    {
        $this->gateway = WPSmsTwoWay()->getPlugin()->get(GatewayManager::class)->getCurrentGateway();
    }

    /**
     * Export Two-Way's setting data to be used in wpsms settings page
     *
     * @return array
     */
    public function exportFields()
    {
        return $this->fields;
    }

    /**
     * Add a new field to the settings page
     *
     * @param string $fieldName
     * @param array $data
     * @return void
     */
    private function addField(string $fieldName, array $data)
    {
        $this->fields[$fieldName] = $data;
    }

    /**
     * Set status section fields
     *
     * @return void
     */
    private function addStatusSection()
    {
        $this->addField('status', [
            'id' => 'status',
            'name' => __('Status', 'wp-sms-two-way'),
            'type' => 'header'
        ]);
        $this->addField('current_gateway', [
            'id' => 'current_gateway',
            'name' => __('Current Gateway', 'wp-sms-two-way'),
            'type' => 'html',
            'options' => $this->renderCurrentGatewayNameField(),
        ]);
        $this->addField('support_status', [
            'id' => 'support_status',
            'name' => __('Support Status', 'wp-sms-two-way'),
            'type' => 'html',
            'options' => $this->renderGatewayStatusField(),
        ]);

        /*================================ CONDITIONAL FIELDS ==============================*/

        if (!$this->gateway->isSupported) {
            return;
        }
        
        $this->addField('register_type', [
            'id' => 'register_type',
            'name' => __('Webhook Register Type', 'wp-sms-two-way'),
            'type' => 'html',
            'options' => $this->renderRegisterTypeField()
        ]);

        switch ($this->gateway->getRegisterType()) {
            case 'api':
                $this->addField('registration_status', [
                    'id' => 'registration_status',
                    'name' => __('Webhook Registration Status', 'wp-sms-two-way'),
                    'type' => 'html',
                    'options' => $this->renderRegistrationStatusField()
                ]);
                break;
            case 'panel':
                $this->addField('webhook_url', [
                    'id' => 'webhook_url',
                    'name' => __('Webhook URL', 'wp-sms-two-way'),
                    'type' => 'html',
                    'options' => $this->renderWebhookUrlFiled()
                ]);
                if ($this->gateway->getPanelUrl()) {
                    $this->addField('registration_panel_url', [
                        'id' => 'registration_panel_url',
                        'name' => __('Registration panel URL', 'wp-sms-two-way'),
                        'type' => 'html',
                        'options' => $this->renderRegistrationPanelField()
                    ]);
                }
                break;
        }
    }


    /**========================================================================
     *                           Render functions
     *========================================================================**/

    /**
     * Render name of the active gateway
     *
     * @return string html
     */
    public function renderCurrentGatewayNameField()
    {
        $gatewayName = ucfirst($this->gateway->name) ?? __('Not Set', 'wp-sms-two-way');
        return "<p class='two-way-gateway-name'><strong>{$gatewayName}</strong></p>";
    }

    /**
     * Render two-way support status of the active gateway
     *
     * @return string html
     */
    public function renderGatewayStatusField()
    {
        if ($this->gateway->isSupported) {
            return "<p class='two-way-gateway-supported'><span class='dashicons dashicons-yes'></span>".__('Supported', 'wp-sms-two-way')."</p>";
        }
        return "<p class='two-way-gateway-not-supported'><span class='dashicons dashicons-no'></span>".__('Not Supported', 'wp-sms-two-way')."</p>";
    }

    /**
     * Render registration type field of the active gateway
     *
     * @return string html
     */
    public function renderRegisterTypeField()
    {
        $type = ucfirst($this->gateway->getRegisterType());
        return "<p>".__($type, 'wp-sms-two-way')."</p>";
    }

    /**
     * Render registration status field
     *
     * @return string html
     */
    public function renderRegistrationStatusField()
    {
        //to do...
    }

    /**
     * Render gateway's panel URL
     *
     * @return string html
     */
    public function renderRegistrationPanelField()
    {
        $url = esc_url($this->gateway->getPanelUrl());
        return "<a href='{$url}'>{$url}</a>";
    }

    /**
     * Render webhook URL field
     *
     * @return string
     */
    public function renderWebhookUrlFiled()
    {
        $webhookUrl = esc_url(WPSmsTwoWay()->getPlugin()->get(webhook::class)->getUrl());

        $webhookField = "<div id='two-way-webhook-url-field'><div><span>{$webhookUrl}</span></div></div>";
        $copyButton  = '<button class="" id="two-way-copy-webhook-btn" type="button">'.__('Copy', 'wp-sms-two-way').'</button>';
        $resetButton = '<button class="" id="two-way-reset-token-btn" type="button">'.__('Reset Token', 'wp-sms-two-way').'</button>';

        return
            $webhookField
            .$copyButton
            .$resetButton;
    }
}
