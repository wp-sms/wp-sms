<?php

namespace WSms\Container;

use WSms\Contact\ContactExporter;
use WSms\Contact\ContactImporter;
use WSms\Contact\ContactRepository;
use WSms\Contact\ListRepository;
use WSms\Contact\SegmentEvaluator;
use WSms\Contact\TagRepository;
use WSms\Database\Connection;

defined('ABSPATH') || exit;

class ContactServiceProvider implements ServiceProvider
{
    public function register(ServiceContainer $container): void
    {
        $container->register('contact.repository', fn($c) => new ContactRepository($c->get(Connection::class)));
        $container->register('contact.segment_evaluator', fn($c) => new SegmentEvaluator($c->get(Connection::class)));
        $container->register('contact.importer', fn($c) => new ContactImporter($c->get('contact.repository'), $c->get(Connection::class)));
        $container->register('contact.exporter', fn($c) => new ContactExporter($c->get('contact.repository')));
        $container->register('contact.tag_repository', fn($c) => new TagRepository($c->get(Connection::class)));
        $container->register('contact.list_repository', fn($c) => new ListRepository($c->get(Connection::class)));
    }

    public function boot(ServiceContainer $container): void
    {
        // Auto-sync hooks are now managed by ContactSourceServiceProvider
    }
}
