<?php

namespace WSms\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WSms\Auth\SettingsRepository;
use WSms\Exception\NotFoundException;
use WSms\Exception\ValidationException;
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
            'permission_callback' => $this->canViewSection('identity'),
        ]);

        register_rest_route(self::NAMESPACE, '/auth/admin/templates/(?P<id>[a-z_]+)', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'handleGet'],
                'permission_callback' => $this->canViewSection('identity'),
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'handleSave'],
                'permission_callback' => $this->canManageSection('identity'),
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
            'permission_callback' => $this->canManageSection('identity'),
            'args'                => [
                'channel' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/admin/templates/(?P<id>[a-z_]+)/toggle', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleToggle'],
            'permission_callback' => $this->canManageSection('identity'),
            'args'                => [
                'enabled' => ['required' => true, 'type' => 'boolean'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/admin/templates/preview', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handlePreview'],
            'permission_callback' => $this->canManageSection('identity'),
            'args'                => [
                'template_id' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'channel'     => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
    }

    public function handleList(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () {
            $enabledChannels = $this->getEnabledChannels();
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

                $channelTemplateInfo = $this->buildChannelTemplateInfo($template->getId(), $visibleChannels);
                $entry['channel_template_info'] = $channelTemplateInfo;

                if (isset($channelTemplateInfo['whatsapp'])) {
                    $entry['whatsapp_gateway_id'] = $channelTemplateInfo['whatsapp']['gateway_id'];
                    $entry['whatsapp_mapping'] = $channelTemplateInfo['whatsapp']['mapping'];
                }

                $templates[] = $entry;
            }

            return new WP_REST_Response($templates);
        });
    }

    public function handleGet(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $id = $request->get_param('id');

            try {
                $editData = $this->templateManager->getTemplateForEditing($id);
            } catch (\InvalidArgumentException $e) {
                throw NotFoundException::entity('Template', $id);
            }

            $enabledChannels = $this->getEnabledChannels();
            $visibleChannels = $this->templateManager->getVisibleChannels($id, $enabledChannels);
            $editData['visible_channels'] = $visibleChannels;

            $channelTemplateInfo = $this->buildChannelTemplateInfo($id, $visibleChannels);
            $editData['channel_template_info'] = $channelTemplateInfo;

            if (isset($channelTemplateInfo['whatsapp'])) {
                $editData['whatsapp_gateway_id'] = $channelTemplateInfo['whatsapp']['gateway_id'];
                $editData['whatsapp_mapping'] = $channelTemplateInfo['whatsapp']['mapping'];
            }

            return new WP_REST_Response($editData);
        });
    }

    public function handleSave(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $id = $request->get_param('id');
            $channel = $request->get_param('channel');

            try {
                $template = $this->templateManager->getTemplate($id);
            } catch (\InvalidArgumentException $e) {
                throw NotFoundException::entity('Template', $id);
            }

            if (!in_array($channel, $template->getSupportedChannels(), true)) {
                throw ValidationException::field('channel', sprintf('Channel "%s" is not supported by this template.', $channel));
            }

            $body = $request->get_param('body');

            if (empty(trim($body))) {
                throw ValidationException::field('body', __('Template body cannot be empty.', 'wp-sms'));
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

            return $this->ok();
        });
    }

    public function handleReset(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $id = $request->get_param('id');
            $channel = $request->get_param('channel');

            try {
                $this->templateManager->getTemplate($id);
            } catch (\InvalidArgumentException $e) {
                throw NotFoundException::entity('Template', $id);
            }

            $this->storage->saveOverride($id, $channel, null);

            return $this->ok();
        });
    }

    public function handleToggle(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $id = $request->get_param('id');
            $enabled = (bool) $request->get_param('enabled');

            try {
                $template = $this->templateManager->getTemplate($id);
            } catch (\InvalidArgumentException $e) {
                throw NotFoundException::entity('Template', $id);
            }

            if (!$template instanceof ToggleableTemplateInterface) {
                throw ValidationException::field('template', __('This template cannot be toggled.', 'wp-sms'));
            }

            $this->storage->setEnabled($id, $enabled);

            return new WP_REST_Response(['success' => true, 'enabled' => $enabled]);
        });
    }

    public function handlePreview(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $id = $request->get_param('template_id');
            $channel = $request->get_param('channel');

            try {
                $template = $this->templateManager->getTemplate($id);
            } catch (\InvalidArgumentException $e) {
                throw NotFoundException::entity('Template', $id);
            }

            if (!in_array($channel, $template->getSupportedChannels(), true)) {
                throw ValidationException::field('channel', sprintf('Channel "%s" is not supported by this template.', $channel));
            }

            // Build example variables from definitions.
            $variables = [];
            foreach ($template->getVariables() as $name => $definition) {
                $variables[$name] = $definition->example;
            }

            $rendered = $this->templateManager->render($id, $channel, $variables);

            return new WP_REST_Response([
                'subject' => $rendered->subject,
                'body'    => $rendered->body,
                'meta'    => $rendered->meta,
            ]);
        });
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

    private function buildChannelTemplateInfo(string $templateId, array $visibleChannels): array
    {
        if (!$this->catalogManager) {
            return [];
        }

        $info = [];

        foreach ($visibleChannels as $ch) {
            $gatewayId = $this->catalogManager->getDefaultCatalogGatewayId($ch);
            if ($gatewayId) {
                $info[$ch] = [
                    'gateway_id'   => $gatewayId,
                    'mapping'      => $this->catalogManager->resolveMapping($templateId, $gatewayId)?->toArray(),
                    'capabilities' => $this->catalogManager->getTemplateCapabilities($gatewayId),
                ];
            }
        }

        return $info;
    }
}
