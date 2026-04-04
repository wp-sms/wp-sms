<?php

namespace WSms\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WSms\Exception\NotFoundException;
use WSms\Exception\ValidationException;
use WSms\Messaging\Catalog\ProviderTemplate;
use WSms\Messaging\Catalog\TemplateCatalogException;
use WSms\Messaging\Catalog\TemplateCatalogManager;
use WSms\Messaging\Catalog\TemplateMapping;
use WSms\Messaging\Catalog\TemplateStatus;

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
            'permission_callback' => $this->canViewSection('channels'),
        ]);

        register_rest_route(self::NAMESPACE, '/gateways/(?P<id>[a-z_]+)/templates/refresh', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleRefreshTemplates'],
            'permission_callback' => $this->canManageSection('channels'),
        ]);

        register_rest_route(self::NAMESPACE, '/gateways/(?P<id>[a-z_]+)/templates/manual', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'handleCreateManualTemplate'],
                'permission_callback' => $this->canManageSection('channels'),
                'args'                => [
                    'template_id' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'name'        => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'body_text'   => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'],
                    'language'    => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'category'    => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'variables'   => ['required' => false, 'type' => 'array'],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/gateways/(?P<id>[a-z_]+)/templates/manual/(?P<tid>[a-zA-Z0-9_\-]+)', [
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'handleUpdateManualTemplate'],
                'permission_callback' => $this->canManageSection('channels'),
                'args'                => [
                    'name'      => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'body_text' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'],
                    'language'  => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'category'  => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'variables' => ['required' => false, 'type' => 'array'],
                ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'handleDeleteManualTemplate'],
                'permission_callback' => $this->canManageSection('channels'),
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/gateways/(?P<id>[a-z_]+)/template-capabilities', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleGetCapabilities'],
            'permission_callback' => $this->canViewSection('channels'),
        ]);

        register_rest_route(self::NAMESPACE, '/gateways/(?P<id>[a-z_]+)/template-mappings', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleGetMappings'],
            'permission_callback' => $this->canViewSection('channels'),
        ]);

        register_rest_route(self::NAMESPACE, '/gateways/(?P<id>[a-z_]+)/template-mappings/(?P<type>[a-z_]+)', [
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'handleSaveMapping'],
                'permission_callback' => $this->canManageSection('channels'),
                'args'                => [
                    'provider_template_id' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'language'             => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'variable_map'         => ['required' => true, 'type' => 'object'],
                    'source'               => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'regulatory_meta'      => ['required' => false, 'type' => 'object'],
                ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'handleRemoveMapping'],
                'permission_callback' => $this->canManageSection('channels'),
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/gateways/(?P<id>[a-z_]+)/template-mappings/verify', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleVerifyMappings'],
            'permission_callback' => $this->canManageSection('channels'),
        ]);
    }

    public function handleFetchTemplates(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            return $this->fetchTemplatesFromGateway($request->get_param('id'), forceRefresh: false);
        });
    }

    public function handleRefreshTemplates(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            return $this->fetchTemplatesFromGateway($request->get_param('id'), forceRefresh: true);
        });
    }

    public function handleGetMappings(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $gatewayId = $request->get_param('id');
            $mappings = $this->catalogManager->getMappingsForGateway($gatewayId);

            return $this->ok(array_map(fn($m) => $m->toArray(), $mappings));
        });
    }

    public function handleSaveMapping(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $gatewayId = $request->get_param('id');
            $templateType = $request->get_param('type');
            $providerTemplateId = $request->get_param('provider_template_id');
            $variableMap = $request->get_param('variable_map') ?? [];
            $language = $request->get_param('language') ?? 'en';

            $source = $request->get_param('source') ?? 'catalog';
            $regulatoryMeta = $request->get_param('regulatory_meta') ?? [];

            // Look up the provider template to cache its name and body
            $providerTemplateName = '';
            $providerTemplateBody = '';
            $providerTemplate = null;

            try {
                $templates = $this->catalogManager->getTemplates($gatewayId);
                foreach ($templates as $template) {
                    if ($template->id === $providerTemplateId) {
                        $providerTemplateName = $template->name;
                        $providerTemplateBody = $template->bodyText;
                        $providerTemplate = $template;
                        break;
                    }
                }
            } catch (TemplateCatalogException) {
                // Continue without cached data — mapping still valid
            }

            // Validate variable mapping against the provider template
            if ($providerTemplate) {
                $variables = $providerTemplate->getVariables();
                $mappedValues = array_values($variableMap);

                foreach ($variables as $variable) {
                    $key = $variable['key'];
                    if (!in_array($key, $mappedValues, true)) {
                        $label = $variable['label'] ?? $key;
                        throw ValidationException::field(
                            'variable_map',
                            sprintf('Provider variable "%s" is not mapped.', $label),
                        );
                    }
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
                source: $source,
                regulatoryMeta: $regulatoryMeta,
            );

            $this->catalogManager->saveMapping($mapping);

            return $this->ok($mapping->toArray());
        });
    }

    public function handleRemoveMapping(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $gatewayId = $request->get_param('id');
            $templateType = $request->get_param('type');

            $this->catalogManager->removeMapping($templateType, $gatewayId);

            return $this->ok();
        });
    }

    public function handleVerifyMappings(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $gatewayId = $request->get_param('id');

            $result = $this->catalogManager->verifyMappings($gatewayId);

            return $this->ok([
                'valid' => array_map(fn($m) => $m->toArray(), $result['valid']),
                'stale' => array_map(fn($m) => $m->toArray(), $result['stale']),
            ]);
        });
    }

    public function handleGetCapabilities(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $gatewayId = $request->get_param('id');
            $capabilities = $this->catalogManager->getTemplateCapabilities($gatewayId);

            if ($capabilities === null) {
                return $this->ok([
                    'supports_templates' => false,
                    'fetchable' => false,
                    'variable_style' => null,
                    'required_channels' => [],
                ]);
            }

            return $this->ok($capabilities);
        });
    }

    public function handleCreateManualTemplate(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $gatewayId = $request->get_param('id');

            if (!$this->catalogManager->gatewaySupportsTemplates($gatewayId)) {
                throw ValidationException::field('gateway', 'This gateway does not support templates.');
            }

            $templateId = $request->get_param('template_id');

            if (empty($templateId)) {
                throw ValidationException::field('template_id', 'Template ID is required.');
            }

            $name = $request->get_param('name');
            $bodyText = $request->get_param('body_text');
            $language = $request->get_param('language') ?? 'en';
            $category = $request->get_param('category') ?? 'utility';
            $variables = $request->get_param('variables') ?? [];

            $variableCount = count($variables);

            $template = new ProviderTemplate(
                id: $templateId,
                name: $name,
                language: $language,
                category: $category,
                status: TemplateStatus::Approved,
                bodyText: $bodyText,
                variableCount: $variableCount,
                providerMeta: [],
                variables: $variables,
                source: 'manual',
            );

            $this->catalogManager->saveManualTemplate($gatewayId, $template);

            return $this->ok($template->toArray());
        });
    }

    public function handleUpdateManualTemplate(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $gatewayId = $request->get_param('id');
            $templateId = $request->get_param('tid');

            $existing = null;
            foreach ($this->catalogManager->getManualTemplates($gatewayId) as $t) {
                if ($t->id === $templateId) {
                    $existing = $t;
                    break;
                }
            }

            if (!$existing) {
                throw NotFoundException::entity('Manual template', $templateId);
            }

            $template = new ProviderTemplate(
                id: $templateId,
                name: $request->get_param('name') ?? $existing->name,
                language: $request->get_param('language') ?? $existing->language,
                category: $request->get_param('category') ?? $existing->category,
                status: $existing->status,
                bodyText: $request->get_param('body_text') ?? $existing->bodyText,
                variableCount: count($request->get_param('variables') ?? $existing->variables),
                providerMeta: $existing->providerMeta,
                variables: $request->get_param('variables') ?? $existing->variables,
                source: 'manual',
            );

            $this->catalogManager->saveManualTemplate($gatewayId, $template);

            return $this->ok($template->toArray());
        });
    }

    public function handleDeleteManualTemplate(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $gatewayId = $request->get_param('id');
            $templateId = $request->get_param('tid');

            $this->catalogManager->removeManualTemplate($gatewayId, $templateId);

            return $this->ok();
        });
    }

    private function fetchTemplatesFromGateway(string $gatewayId, bool $forceRefresh): WP_REST_Response
    {
        if (!$this->catalogManager->gatewaySupportsTemplates($gatewayId)) {
            throw ValidationException::field('gateway', 'This gateway does not support templates.');
        }

        try {
            $templates = $this->catalogManager->getTemplates($gatewayId, $forceRefresh);
        } catch (TemplateCatalogException $e) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'fetch_failed',
                'message' => $e->getMessage(),
            ], 502);
        }

        return $this->ok(array_map(fn($t) => $t->toArray(), $templates));
    }
}
