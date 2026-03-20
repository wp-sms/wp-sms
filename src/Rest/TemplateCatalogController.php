<?php

namespace WSms\Rest;

use WSms\Messaging\Catalog\TemplateCatalogException;
use WSms\Messaging\Catalog\TemplateCatalogManager;
use WSms\Messaging\Catalog\TemplateMapping;

defined('ABSPATH') || exit;

class TemplateCatalogController extends Controller
{
    public function __construct(
        private readonly TemplateCatalogManager $catalogManager,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/gateways/(?P<id>[a-z_]+)/templates', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleFetchTemplates'],
            'permission_callback' => [$this, 'canManage'],
        ]);

        register_rest_route(self::NAMESPACE, '/gateways/(?P<id>[a-z_]+)/templates/refresh', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleRefreshTemplates'],
            'permission_callback' => [$this, 'canManage'],
        ]);

        register_rest_route(self::NAMESPACE, '/gateways/(?P<id>[a-z_]+)/template-mappings', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleGetMappings'],
            'permission_callback' => [$this, 'canManage'],
        ]);

        register_rest_route(self::NAMESPACE, '/gateways/(?P<id>[a-z_]+)/template-mappings/(?P<type>[a-z_]+)', [
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'handleSaveMapping'],
                'permission_callback' => [$this, 'canManage'],
                'args'                => [
                    'provider_template_id' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'language'             => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'variable_map'         => ['required' => true, 'type' => 'object'],
                ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'handleRemoveMapping'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/gateways/(?P<id>[a-z_]+)/template-mappings/verify', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleVerifyMappings'],
            'permission_callback' => [$this, 'canManage'],
        ]);
    }

    public function handleFetchTemplates(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->fetchTemplatesFromGateway($request->get_param('id'), forceRefresh: false);
    }

    public function handleRefreshTemplates(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->fetchTemplatesFromGateway($request->get_param('id'), forceRefresh: true);
    }

    public function handleGetMappings(\WP_REST_Request $request): \WP_REST_Response
    {
        $gatewayId = $request->get_param('id');
        $mappings = $this->catalogManager->getMappingsForGateway($gatewayId);

        return new \WP_REST_Response(array_map(fn($m) => $m->toArray(), $mappings));
    }

    public function handleSaveMapping(\WP_REST_Request $request): \WP_REST_Response
    {
        $gatewayId = $request->get_param('id');
        $templateType = $request->get_param('type');
        $providerTemplateId = $request->get_param('provider_template_id');
        $variableMap = $request->get_param('variable_map') ?? [];
        $language = $request->get_param('language') ?? 'en';

        // Look up the provider template to cache its name and body
        $providerTemplateName = '';
        $providerTemplateBody = '';
        $variableCount = 0;

        try {
            $templates = $this->catalogManager->getTemplates($gatewayId);
            foreach ($templates as $template) {
                if ($template->id === $providerTemplateId) {
                    $providerTemplateName = $template->name;
                    $providerTemplateBody = $template->bodyText;
                    $variableCount = $template->variableCount;
                    break;
                }
            }
        } catch (TemplateCatalogException) {
            // Continue without cached data — mapping still valid
        }

        // Validate: all provider variable positions must be mapped
        $mappedPositions = array_values($variableMap);
        for ($i = 1; $i <= $variableCount; $i++) {
            if (!in_array((string) $i, $mappedPositions, true)) {
                return new \WP_REST_Response([
                    'error'   => 'incomplete_mapping',
                    'message' => sprintf('Provider variable {{%d}} is not mapped.', $i),
                ], 400);
            }
        }

        $mapping = new TemplateMapping(
            templateType: $templateType,
            providerTemplateId: $providerTemplateId,
            gatewayId: $gatewayId,
            language: $language,
            variableMap: $variableMap,
            providerTemplateName: $providerTemplateName,
            providerTemplateBody: $providerTemplateBody,
            lastVerifiedAt: time(),
        );

        $this->catalogManager->saveMapping($mapping);

        return new \WP_REST_Response($mapping->toArray());
    }

    public function handleRemoveMapping(\WP_REST_Request $request): \WP_REST_Response
    {
        $gatewayId = $request->get_param('id');
        $templateType = $request->get_param('type');

        $this->catalogManager->removeMapping($templateType, $gatewayId);

        return new \WP_REST_Response(['success' => true]);
    }

    public function handleVerifyMappings(\WP_REST_Request $request): \WP_REST_Response
    {
        $gatewayId = $request->get_param('id');

        $result = $this->catalogManager->verifyMappings($gatewayId);

        return new \WP_REST_Response([
            'valid' => array_map(fn($m) => $m->toArray(), $result['valid']),
            'stale' => array_map(fn($m) => $m->toArray(), $result['stale']),
        ]);
    }

    private function fetchTemplatesFromGateway(string $gatewayId, bool $forceRefresh): \WP_REST_Response
    {
        if (!$this->catalogManager->gatewaySupportsTemplates($gatewayId)) {
            return new \WP_REST_Response([
                'error'   => 'not_supported',
                'message' => 'This gateway does not support template catalog.',
            ], 400);
        }

        try {
            $templates = $this->catalogManager->getTemplates($gatewayId, $forceRefresh);
        } catch (TemplateCatalogException $e) {
            return new \WP_REST_Response([
                'error'   => 'fetch_failed',
                'message' => $e->getMessage(),
            ], 502);
        }

        return new \WP_REST_Response(array_map(fn($t) => $t->toArray(), $templates));
    }
}
