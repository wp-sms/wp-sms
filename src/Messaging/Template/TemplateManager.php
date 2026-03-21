<?php

namespace WSms\Messaging\Template;

use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TemplateEngineInterface;
use WSms\Messaging\Template\Contracts\ChannelRendererInterface;
use WSms\Messaging\Template\Contracts\TemplateDefinitionInterface;
use WSms\Messaging\Template\Contracts\TemplateStorageInterface;
use WSms\Messaging\Template\Contracts\ToggleableTemplateInterface;
use WSms\Messaging\Template\ValueObjects\ChannelContent;
use WSms\Messaging\Template\ValueObjects\RenderedMessage;
use WSms\Support\IpResolver;

defined('ABSPATH') || exit;

class TemplateManager
{
    /** @var array<string, TemplateDefinitionInterface> */
    private array $templates = [];

    /** @var array<string, ChannelRendererInterface> */
    private array $renderers = [];

    /** @var \Closure(): string */
    private \Closure $logoUrlResolver;

    public function __construct(
        private readonly TemplateStorageInterface $storage,
        private readonly TemplateEngineInterface $engine,
        ?\Closure $logoUrlResolver = null,
    ) {
        $this->logoUrlResolver = $logoUrlResolver ?? static fn () => '';
    }

    public function registerTemplate(TemplateDefinitionInterface $template): void
    {
        $this->templates[$template->getId()] = $template;
    }

    public function registerRenderer(ChannelRendererInterface $renderer): void
    {
        $this->renderers[$renderer->getChannel()] = $renderer;
    }

    public function render(string $templateId, string $channel, array $variables, array $context = []): RenderedMessage
    {
        $template = $this->getTemplate($templateId);

        if (!in_array($channel, $template->getSupportedChannels(), true)) {
            throw new \InvalidArgumentException(
                sprintf('Channel "%s" is not supported by template "%s".', $channel, $templateId)
            );
        }

        if (!isset($this->renderers[$channel])) {
            throw new \InvalidArgumentException(
                sprintf('No renderer registered for channel "%s".', $channel)
            );
        }

        $variables = $this->injectCommonVariables($variables, $template);
        $context = $this->injectCommonContext($context);
        $content = $this->resolveContent($template, $channel);
        $substituted = $this->substituteVariables($content, $variables);

        $this->warnMissingRequired($template, $variables);

        return $this->renderers[$channel]->render($substituted, array_merge($context, $variables));
    }

    public function renderToMessage(
        string $templateId,
        string $channel,
        string $recipient,
        array $variables,
        array $messageMeta = [],
    ): MessageInterface {
        $rendered = $this->render($templateId, $channel, $variables);

        // Enrich meta for provider-side template resolution (WhatsApp requires pre-approved templates)
        if ($channel === 'whatsapp') {
            $messageMeta['template_type'] = $templateId;
            $messageMeta['template_variables'] = $variables;
        }

        return $rendered->toMessage($channel, $recipient, $messageMeta);
    }

    /**
     * @return array<string, TemplateDefinitionInterface>
     */
    public function getTemplates(): array
    {
        return $this->templates;
    }

    public function getTemplate(string $templateId): TemplateDefinitionInterface
    {
        if (!isset($this->templates[$templateId])) {
            throw new \InvalidArgumentException(
                sprintf('Template "%s" not found.', $templateId)
            );
        }

        return $this->templates[$templateId];
    }

    public function getTemplateForEditing(string $templateId): array
    {
        $template = $this->getTemplate($templateId);
        $defaults = $template->getDefaults();
        $channels = [];

        foreach ($template->getSupportedChannels() as $channel) {
            $override = $this->storage->getOverride($templateId, $channel);
            $default = $defaults[$channel] ?? null;

            $channels[$channel] = [
                'default'  => $default?->toArray(),
                'override' => $override?->toArray(),
                'current'  => ($override ?? $default)?->toArray(),
            ];
        }

        return [
            'id'          => $template->getId(),
            'label'       => $template->getLabel(),
            'description' => $template->getDescription(),
            'variables'   => array_map(fn ($v) => $v->toArray(), $template->getVariables()),
            'channels'    => $channels,
            'toggleable'  => $this->isToggleable($templateId),
            'enabled'     => $this->isEnabled($templateId),
        ];
    }

    public function isToggleable(string $templateId): bool
    {
        return $this->getTemplate($templateId) instanceof ToggleableTemplateInterface;
    }

    public function isEnabled(string $templateId): bool
    {
        $template = $this->getTemplate($templateId);

        if (!$template instanceof ToggleableTemplateInterface) {
            return true;
        }

        $stored = $this->storage->isEnabled($templateId);

        return $stored ?? $template->isEnabledByDefault();
    }

    /**
     * Get channels visible in admin UI: intersection of template's supported channels and enabled channels.
     *
     * @return array<string>
     */
    public function getVisibleChannels(string $templateId, array $enabledChannels): array
    {
        $template = $this->getTemplate($templateId);

        return array_values(array_intersect($template->getSupportedChannels(), $enabledChannels));
    }

    private function resolveContent(TemplateDefinitionInterface $template, string $channel): ChannelContent
    {
        $override = $this->storage->getOverride($template->getId(), $channel);

        if ($override !== null) {
            return $override;
        }

        $defaults = $template->getDefaults();

        return $defaults[$channel] ?? new ChannelContent(body: '');
    }

    private function substituteVariables(ChannelContent $content, array $variables): ChannelContent
    {
        return new ChannelContent(
            body: $this->engine->render($content->body, $variables),
            subject: $content->subject !== null ? $this->engine->render($content->subject, $variables) : null,
            cta: $content->cta !== null ? $this->engine->render($content->cta, $variables) : null,
            ctaUrl: $content->ctaUrl !== null ? $this->engine->render($content->ctaUrl, $variables) : null,
        );
    }

    private function injectCommonContext(array $context): array
    {
        if (!isset($context['logo_url'])) {
            $context['logo_url'] = ($this->logoUrlResolver)();
        }

        return $context;
    }

    private function injectCommonVariables(array $variables, TemplateDefinitionInterface $template): array
    {
        $declared = $template->getVariables();

        if (isset($declared['site_name']) && !isset($variables['site_name'])) {
            $variables['site_name'] = get_bloginfo('name');
        }

        if (isset($declared['site_url']) && !isset($variables['site_url'])) {
            $variables['site_url'] = get_site_url();
        }

        if (isset($declared['ip_warning']) && !isset($variables['ip_warning'])) {
            $ip = IpResolver::resolve();
            $variables['ip_warning'] = $ip !== ''
                ? sprintf(
                    __('This was requested from IP: %s. If you did not request this, your password may have been compromised.', 'wp-sms'),
                    $ip,
                )
                : '';
        }

        return $variables;
    }

    private function warnMissingRequired(TemplateDefinitionInterface $template, array $variables): void
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        foreach ($template->getVariables() as $name => $definition) {
            if ($definition->required && !isset($variables[$name])) {
                error_log(sprintf(
                    '[WP-SMS] Template "%s": required variable "%s" was not provided.',
                    $template->getId(),
                    $name,
                ));
            }
        }
    }
}
