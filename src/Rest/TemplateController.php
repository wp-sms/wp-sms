<?php

namespace WSms\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WSms\Auth\SettingsRepository;
use WSms\Messaging\Catalog\TemplateCatalogManager;
use WSms\Messaging\Template\Contracts\TemplateStorageInterface;
use WSms\Messaging\Template\Contracts\ToggleableTemplateInterface;
use WSms\Messaging\Template\TemplateManager;
use WSms\Messaging\Template\ValueObjects\ChannelContent;

defined('ABSPATH') || exit;

class TemplateController extends Controller
{
    public function __construct(
        private TemplateManager $templateManager,
        private TemplateStorageInterface $storage,
        private SettingsRepository $settingsRepo,
        private ?TemplateCatalogManager $catalogManager = null,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/auth/admin/templates', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleList'],
            'permission_callback' => [$this, 'canManage'],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/admin/templates/(?P<id>[a-z_]+)', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'handleGet'],
                'permission_callback' => [$this, 'canManage'],
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'handleSave'],
                'permission_callback' => [$this, 'canManage'],
                'args'                => [
                    'channel'  => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'body'     => ['required' => true, 'type' => 'string'],
                    'subject'  => ['required' => false, 'type' => ['string', 'null']],
                    'cta'      => ['required' => false, 'type' => ['string', 'null']],
                    'cta_url'  => ['required' => false, 'type' => ['string', 'null']],
                    'enabled'  => ['required' => false, 'type' => 'boolean'],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/admin/templates/(?P<id>[a-z_]+)/reset', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleReset'],
            'permission_callback' => [$this, 'canManage'],
            'args'                => [
                'channel' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/admin/templates/(?P<id>[a-z_]+)/toggle', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleToggle'],
            'permission_callback' => [$this, 'canManage'],
            'args'                => [
                'enabled' => ['required' => true, 'type' => 'boolean'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/admin/templates/preview', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handlePreview'],
            'permission_callback' => [$this, 'canManage'],
            'args'                => [
                'template_id' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'channel'     => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
    }

    public function handleList(WP_REST_Request $request): WP_REST_Response
    {
        $enabledChannels = $this->getEnabledChannels();
        $whatsappGatewayId = $this->catalogManager
            ? $this->catalogManager->getDefaultCatalogGatewayId('whatsapp')
            : null;
        $templates = [];

        foreach ($this->templateManager->getTemplates() as $template) {
            $visibleChannels = $this->templateManager->getVisibleChannels($template->getId(), $enabledChannels);
            $editData = $this->templateManager->getTemplateForEditing($template->getId());

            $entry = [
                'id'               => $template->getId(),
                'label'            => $template->getLabel(),
                'description'      => $template->getDescription(),
                'supported_channels' => $template->getSupportedChannels(),
                'visible_channels' => $visibleChannels,
                'variables'        => $editData['variables'],
                'channels'         => $editData['channels'],
                'toggleable'       => $editData['toggleable'],
                'enabled'          => $editData['enabled'],
            ];

            if ($whatsappGatewayId && in_array('whatsapp', $visibleChannels, true)) {
                $entry['whatsapp_gateway_id'] = $whatsappGatewayId;
                $mapping = $this->catalogManager->resolveMapping($template->getId(), $whatsappGatewayId);
                $entry['whatsapp_mapping'] = $mapping?->toArray();
            }

            $templates[] = $entry;
        }

        return new WP_REST_Response($templates);
    }

    public function handleGet(WP_REST_Request $request): WP_REST_Response
    {
        $id = $request->get_param('id');

        try {
            $editData = $this->templateManager->getTemplateForEditing($id);
        } catch (\InvalidArgumentException $e) {
            return new WP_REST_Response(['success' => false, 'error' => 'not_found', 'message' => $e->getMessage()], 404);
        }

        $enabledChannels = $this->getEnabledChannels();
        $editData['visible_channels'] = $this->templateManager->getVisibleChannels($id, $enabledChannels);

        if ($this->catalogManager && in_array('whatsapp', $editData['visible_channels'], true)) {
            $whatsappGatewayId = $this->catalogManager->getDefaultCatalogGatewayId('whatsapp');
            if ($whatsappGatewayId) {
                $editData['whatsapp_gateway_id'] = $whatsappGatewayId;
                $mapping = $this->catalogManager->resolveMapping($id, $whatsappGatewayId);
                $editData['whatsapp_mapping'] = $mapping?->toArray();
            }
        }

        return new WP_REST_Response($editData);
    }

    public function handleSave(WP_REST_Request $request): WP_REST_Response
    {
        $id = $request->get_param('id');
        $channel = $request->get_param('channel');

        try {
            $template = $this->templateManager->getTemplate($id);
        } catch (\InvalidArgumentException $e) {
            return new WP_REST_Response(['success' => false, 'error' => 'not_found', 'message' => $e->getMessage()], 404);
        }

        if (!in_array($channel, $template->getSupportedChannels(), true)) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'unsupported_channel',
                'message' => sprintf('Channel "%s" is not supported by this template.', $channel),
            ], 400);
        }

        $body = $request->get_param('body');

        if (empty(trim($body))) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'empty_body',
                'message' => __('Template body cannot be empty.', 'wp-sms'),
            ], 400);
        }

        $content = new ChannelContent(
            body: $body,
            subject: $request->get_param('subject'),
            cta: $request->get_param('cta'),
            ctaUrl: $request->get_param('cta_url'),
        );

        $defaults = $template->getDefaults();
        $defaultContent = $defaults[$channel] ?? null;

        if ($defaultContent !== null && $content->equals($defaultContent)) {
            $this->storage->saveOverride($id, $channel, null);
        } else {
            $this->storage->saveOverride($id, $channel, $content);
        }

        $enabled = $request->get_param('enabled');
        if ($enabled !== null && $template instanceof ToggleableTemplateInterface) {
            $this->storage->setEnabled($id, $enabled);
        }

        return new WP_REST_Response(['success' => true]);
    }

    public function handleReset(WP_REST_Request $request): WP_REST_Response
    {
        $id = $request->get_param('id');
        $channel = $request->get_param('channel');

        try {
            $this->templateManager->getTemplate($id);
        } catch (\InvalidArgumentException $e) {
            return new WP_REST_Response(['success' => false, 'error' => 'not_found', 'message' => $e->getMessage()], 404);
        }

        $this->storage->saveOverride($id, $channel, null);

        return new WP_REST_Response(['success' => true]);
    }

    public function handleToggle(WP_REST_Request $request): WP_REST_Response
    {
        $id = $request->get_param('id');
        $enabled = (bool) $request->get_param('enabled');

        try {
            $template = $this->templateManager->getTemplate($id);
        } catch (\InvalidArgumentException $e) {
            return new WP_REST_Response(['success' => false, 'error' => 'not_found', 'message' => $e->getMessage()], 404);
        }

        if (!$template instanceof ToggleableTemplateInterface) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'not_toggleable',
                'message' => __('This template cannot be toggled.', 'wp-sms'),
            ], 400);
        }

        $this->storage->setEnabled($id, $enabled);

        return new WP_REST_Response(['success' => true, 'enabled' => $enabled]);
    }

    public function handlePreview(WP_REST_Request $request): WP_REST_Response
    {
        $id = $request->get_param('template_id');
        $channel = $request->get_param('channel');

        try {
            $template = $this->templateManager->getTemplate($id);
        } catch (\InvalidArgumentException $e) {
            return new WP_REST_Response(['success' => false, 'error' => 'not_found', 'message' => $e->getMessage()], 404);
        }

        if (!in_array($channel, $template->getSupportedChannels(), true)) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'unsupported_channel',
                'message' => sprintf('Channel "%s" is not supported by this template.', $channel),
            ], 400);
        }

        // Build example variables from definitions.
        $variables = [];
        foreach ($template->getVariables() as $name => $definition) {
            $variables[$name] = $definition->example;
        }

        $rendered = $this->templateManager->render($id, $channel, $variables);

        return new WP_REST_Response([
            'subject' => $rendered->subject,
            'body'    => wp_kses_post($rendered->body),
            'meta'    => $rendered->meta,
        ]);
    }

    /**
     * Determine which delivery channels are currently enabled in auth settings.
     *
     * @return array<string>
     */
    private function getEnabledChannels(): array
    {
        $settings = $this->settingsRepo->all();
        $enabled = ['email']; // Email is always available.

        if (!empty($settings['phone']['enabled'])) {
            $deliveryChannel = $settings['phone']['delivery_channel'] ?? 'sms';
            $enabled[] = $deliveryChannel; // 'sms' or 'whatsapp'
        }

        if (!empty($settings['telegram']['enabled'])) {
            $enabled[] = 'telegram';
        }

        return $enabled;
    }
}
