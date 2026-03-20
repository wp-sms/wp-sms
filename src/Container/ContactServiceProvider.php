<?php

namespace WSms\Container;

use WSms\Contact\ContactExporter;
use WSms\Contact\ContactImporter;
use WSms\Contact\ContactRepository;
use WSms\Contact\ListRepository;
use WSms\Contact\SegmentEvaluator;
use WSms\Contact\TagRepository;

defined('ABSPATH') || exit;

class ContactServiceProvider implements ServiceProvider
{
    public function register(ServiceContainer $container): void
    {
        $container->register('contact.repository', fn() => new ContactRepository());
        $container->register('contact.segment_evaluator', fn() => new SegmentEvaluator());
        $container->register('contact.importer', fn($c) => new ContactImporter($c->get('contact.repository')));
        $container->register('contact.exporter', fn($c) => new ContactExporter($c->get('contact.repository')));
        $container->register('contact.tag_repository', fn() => new TagRepository());
        $container->register('contact.list_repository', fn() => new ListRepository());
    }

    public function boot(ServiceContainer $container): void
    {
        // Auto-sync hooks are now managed by ContactSourceServiceProvider
    }
}
