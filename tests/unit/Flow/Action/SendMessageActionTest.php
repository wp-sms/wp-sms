<?php

namespace WSms\Tests\Unit\Flow\Action;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Campaign\AudienceBatch;
use WSms\Campaign\AudienceResolver;
use WSms\Contact\Contracts\ListRepositoryInterface;
use WSms\Contact\Contracts\TagRepositoryInterface;
use WSms\Flow\Action\SendMessageAction;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\TemplateEngineInterface;
use WSms\Messaging\Gateway\GatewayRegistry;
use WSms\Messaging\MessageDispatcher;

class SendMessageActionTest extends TestCase
{
    private SendMessageAction $action;
    private MockObject&MessageDispatcher $dispatcher;
    private MockObject&GatewayRegistry $registry;
    private MockObject&TagRepositoryInterface $tagRepository;
    private MockObject&ListRepositoryInterface $listRepository;
    private MockObject&AudienceResolver $audienceResolver;
    private MockObject&TemplateEngineInterface $templateEngine;

    protected function setUp(): void
    {
        $this->dispatcher = $this->createMock(MessageDispatcher::class);
        $this->registry = $this->createMock(GatewayRegistry::class);
        $this->tagRepository = $this->createMock(TagRepositoryInterface::class);
        $this->listRepository = $this->createMock(ListRepositoryInterface::class);
        $this->audienceResolver = $this->createMock(AudienceResolver::class);
        $this->templateEngine = $this->createMock(TemplateEngineInterface::class);

        $this->action = new SendMessageAction(
            $this->dispatcher,
            $this->registry,
            $this->tagRepository,
            $this->listRepository,
            $this->audienceResolver,
            $this->templateEngine,
        );
    }

    public function testMetadata(): void
    {
        $this->assertSame('send_message', $this->action->getId());
        $this->assertSame('Send Message', $this->action->getName());
        $this->assertSame('WSMS', $this->action->getGroup());
    }

    public function testConfigSchemaHasExpectedFields(): void
    {
        $schema = $this->action->getConfigSchema();
        $this->assertArrayHasKey('gateway', $schema);
        $this->assertArrayHasKey('channel', $schema);
        $this->assertArrayHasKey('recipient_mode', $schema);
        $this->assertArrayHasKey('tag_id', $schema);
        $this->assertArrayHasKey('list_id', $schema);
        $this->assertArrayHasKey('to', $schema);
        $this->assertArrayHasKey('body', $schema);
        $this->assertArrayHasKey('subject', $schema);
        $this->assertTrue($schema['to']['template']);
        $this->assertTrue($schema['body']['template']);
        $this->assertTrue($schema['channel']['dynamic']);
        $this->assertTrue($schema['gateway']['dynamic']);
        $this->assertSame(['channel'], $schema['gateway']['dependsOn']);
    }

    public function testGatewayDefaultIsChannelDefault(): void
    {
        $schema = $this->action->getConfigSchema();
        $this->assertSame(SendMessageAction::DEFAULT_GATEWAY, $schema['gateway']['default']);
    }

    public function testRecipientModeSchemaHasEnumAndLabels(): void
    {
        $schema = $this->action->getConfigSchema();
        $this->assertSame(['custom', 'admin', 'tag', 'list'], $schema['recipient_mode']['enum']);
        $this->assertArrayHasKey('enumLabels', $schema['recipient_mode']);
        $this->assertSame('custom', $schema['recipient_mode']['default']);
    }

    public function testToFieldVisibleOnlyForCustomMode(): void
    {
        $schema = $this->action->getConfigSchema();
        $this->assertSame(
            ['show' => ['recipient_mode' => ['custom']]],
            $schema['to']['displayOptions'],
        );
    }

    public function testTagIdFieldVisibleOnlyForTagMode(): void
    {
        $schema = $this->action->getConfigSchema();
        $this->assertSame(
            ['show' => ['recipient_mode' => ['tag']]],
            $schema['tag_id']['displayOptions'],
        );
        $this->assertTrue($schema['tag_id']['dynamic']);
        $this->assertSame(['recipient_mode'], $schema['tag_id']['dependsOn']);
    }

    public function testListIdFieldVisibleOnlyForListMode(): void
    {
        $schema = $this->action->getConfigSchema();
        $this->assertSame(
            ['show' => ['recipient_mode' => ['list']]],
            $schema['list_id']['displayOptions'],
        );
        $this->assertTrue($schema['list_id']['dynamic']);
        $this->assertSame(['recipient_mode'], $schema['list_id']['dependsOn']);
    }

    public function testOutputSchemaIncludesSentCount(): void
    {
        $output = $this->action->getOutputSchema();
        $this->assertArrayHasKey('status', $output);
        $this->assertArrayHasKey('provider_id', $output);
        $this->assertArrayHasKey('sent_count', $output);
        $this->assertSame('integer', $output['sent_count']['type']);
    }

    public function testGetConfigOptionsForTagId(): void
    {
        $this->tagRepository->method('findAll')->willReturn([
            ['id' => 't1', 'name' => 'VIP'],
            ['id' => 't2', 'name' => 'Newsletter'],
        ]);

        $options = $this->action->getConfigOptions('tag_id');
        $this->assertCount(2, $options);
        $this->assertSame('t1', $options[0]['value']);
        $this->assertSame('VIP', $options[0]['label']);
    }

    public function testGetConfigOptionsForListId(): void
    {
        $this->listRepository->method('findAll')->willReturn([
            ['id' => 'l1', 'name' => 'Subscribers'],
        ]);

        $options = $this->action->getConfigOptions('list_id');
        $this->assertCount(1, $options);
        $this->assertSame('l1', $options[0]['value']);
        $this->assertSame('Subscribers', $options[0]['label']);
    }

    // --- Custom mode tests ---

    public function testExecuteCustomModeDefaultBehavior(): void
    {
        $this->dispatcher->expects($this->once())
            ->method('sendImmediate')
            ->willReturn(DeliveryResult::sent('prov-1'));

        $result = $this->action->execute(
            ['_execution_id' => 'exec-1'],
            ['channel' => 'sms', 'to' => '+1234', 'body' => 'Hello'],
        );

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->output['status']);
        $this->assertSame('prov-1', $result->output['provider_id']);
    }

    public function testExecuteCustomModeBackwardCompatNoRecipientMode(): void
    {
        $this->dispatcher->expects($this->once())
            ->method('sendImmediate')
            ->willReturn(DeliveryResult::sent('prov-2'));

        // No recipient_mode in config — defaults to 'custom'
        $result = $this->action->execute(
            [],
            ['channel' => 'sms', 'to' => '+9999', 'body' => 'Test'],
        );

        $this->assertTrue($result->success);
    }

    // --- Admin mode tests ---

    public function testExecuteAdminModeSmsUsesSitePhone(): void
    {
        // Stub get_option for wsms_auth_settings
        $GLOBALS['_test_options']['wsms_auth_settings'] = ['site_phone' => '+15551234'];

        $this->dispatcher->expects($this->once())
            ->method('sendImmediate')
            ->with(
                $this->callback(fn($msg) => $msg->getRecipient() === '+15551234'),
                $this->anything(),
            )
            ->willReturn(DeliveryResult::sent('prov-admin'));

        $result = $this->action->execute(
            ['_execution_id' => 'exec-2'],
            ['channel' => 'sms', 'recipient_mode' => 'admin', 'body' => 'Admin alert'],
        );

        $this->assertTrue($result->success);

        unset($GLOBALS['_test_options']['wsms_auth_settings']);
    }

    public function testExecuteAdminModeEmailUsesAdminEmail(): void
    {
        $GLOBALS['_test_options']['admin_email'] = 'admin@example.com';

        $this->dispatcher->expects($this->once())
            ->method('sendImmediate')
            ->with(
                $this->callback(fn($msg) => $msg->getRecipient() === 'admin@example.com'),
                $this->anything(),
            )
            ->willReturn(DeliveryResult::sent('prov-email'));

        $result = $this->action->execute(
            [],
            ['channel' => 'email', 'recipient_mode' => 'admin', 'body' => 'Email alert', 'subject' => 'Alert'],
        );

        $this->assertTrue($result->success);

        unset($GLOBALS['_test_options']['admin_email']);
    }

    public function testExecuteAdminModeFailsWhenNoPhoneConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [];

        $result = $this->action->execute(
            [],
            ['channel' => 'sms', 'recipient_mode' => 'admin', 'body' => 'Alert'],
        );

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Admin recipient not configured', $result->error);

        unset($GLOBALS['_test_options']['wsms_auth_settings']);
    }

    public function testExecuteAdminModeFailsForWebhookChannel(): void
    {
        $result = $this->action->execute(
            [],
            ['channel' => 'webhook', 'recipient_mode' => 'admin', 'body' => 'Alert'],
        );

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Admin recipient not configured', $result->error);
    }

    // --- Tag mode tests ---

    public function testExecuteTagModeSendsToAudience(): void
    {
        $this->tagRepository->method('find')
            ->with('tag-1')
            ->willReturn(['id' => 'tag-1', 'name' => 'VIP']);

        $batch = new AudienceBatch(
            recipients: [
                ['contact_id' => 'c1', 'recipient' => '+111', 'first_name' => 'Alice', 'last_name' => 'A', 'custom_fields' => []],
                ['contact_id' => 'c2', 'recipient' => '+222', 'first_name' => 'Bob', 'last_name' => 'B', 'custom_fields' => []],
            ],
            hasMore: false,
            totalCount: 0,
            lastId: 'c2',
        );

        $this->audienceResolver->expects($this->once())
            ->method('resolve')
            ->willReturn($batch);

        $this->templateEngine->expects($this->exactly(2))
            ->method('render')
            ->willReturnCallback(fn($tpl, $data) => str_replace('{{first_name}}', $data['first_name'], $tpl));

        $this->dispatcher->expects($this->exactly(2))
            ->method('sendQueued');

        $result = $this->action->execute(
            ['_execution_id' => 'exec-3'],
            ['channel' => 'sms', 'recipient_mode' => 'tag', 'tag_id' => 'tag-1', 'body' => 'Hi {{first_name}}'],
        );

        $this->assertTrue($result->success);
        $this->assertSame(2, $result->output['sent_count']);
    }

    public function testExecuteTagModeEmptyAudienceReturnsSuccess(): void
    {
        $this->tagRepository->method('find')
            ->with('tag-empty')
            ->willReturn(['id' => 'tag-empty', 'name' => 'Empty']);

        $batch = new AudienceBatch(recipients: [], hasMore: false, totalCount: 0, lastId: null);

        $this->audienceResolver->expects($this->once())
            ->method('resolve')
            ->willReturn($batch);

        $this->dispatcher->expects($this->never())->method('sendQueued');

        $result = $this->action->execute(
            [],
            ['channel' => 'sms', 'recipient_mode' => 'tag', 'tag_id' => 'tag-empty', 'body' => 'Hi'],
        );

        $this->assertTrue($result->success);
        $this->assertSame(0, $result->output['sent_count']);
    }

    public function testExecuteTagModeDeletedTag(): void
    {
        $this->tagRepository->method('find')->with('tag-deleted')->willReturn(null);

        $result = $this->action->execute(
            [],
            ['channel' => 'sms', 'recipient_mode' => 'tag', 'tag_id' => 'tag-deleted', 'body' => 'Hi'],
        );

        $this->assertFalse($result->success);
        $this->assertSame('Tag not found', $result->error);
    }

    public function testExecuteTagModeEmptyTagId(): void
    {
        $result = $this->action->execute(
            [],
            ['channel' => 'sms', 'recipient_mode' => 'tag', 'tag_id' => '', 'body' => 'Hi'],
        );

        $this->assertFalse($result->success);
        $this->assertSame('Tag ID is required', $result->error);
    }

    public function testExecuteTagModePersonalizesPerContact(): void
    {
        $this->tagRepository->method('find')
            ->willReturn(['id' => 't1', 'name' => 'Test']);

        $batch = new AudienceBatch(
            recipients: [
                ['contact_id' => 'c1', 'recipient' => '+111', 'first_name' => 'Alice', 'last_name' => 'Smith', 'custom_fields' => []],
            ],
            hasMore: false,
            totalCount: 0,
            lastId: 'c1',
        );

        $this->audienceResolver->method('resolve')->willReturn($batch);

        $renderedBody = '';
        $this->templateEngine->method('render')
            ->willReturnCallback(function ($tpl, $data) use (&$renderedBody) {
                $renderedBody = "Hi {$data['first_name']}";
                return $renderedBody;
            });

        $this->dispatcher->expects($this->once())
            ->method('sendQueued')
            ->with(
                $this->callback(fn($msg) => $msg->getBody() === 'Hi Alice'),
                $this->anything(),
            );

        $this->action->execute(
            [],
            ['channel' => 'sms', 'recipient_mode' => 'tag', 'tag_id' => 't1', 'body' => 'Hi {{first_name}}'],
        );
    }

    public function testExecuteTagModeSpecificGatewayPassedToSendQueued(): void
    {
        $this->tagRepository->method('find')
            ->willReturn(['id' => 't1', 'name' => 'Test']);

        $batch = new AudienceBatch(
            recipients: [
                ['contact_id' => 'c1', 'recipient' => '+111', 'first_name' => 'Alice', 'last_name' => 'A', 'custom_fields' => []],
            ],
            hasMore: false,
            totalCount: 0,
            lastId: 'c1',
        );

        $this->audienceResolver->method('resolve')->willReturn($batch);
        $this->templateEngine->method('render')->willReturnArgument(0);

        $this->dispatcher->expects($this->once())
            ->method('sendQueued')
            ->with(
                $this->anything(),
                'twilio',  // specific gateway, not null
            );

        $this->action->execute(
            [],
            ['channel' => 'sms', 'recipient_mode' => 'tag', 'tag_id' => 't1', 'gateway' => 'twilio', 'body' => 'Hi'],
        );
    }

    public function testExecuteTagModeDefaultGatewayPassesNull(): void
    {
        $this->tagRepository->method('find')
            ->willReturn(['id' => 't1', 'name' => 'Test']);

        $batch = new AudienceBatch(
            recipients: [
                ['contact_id' => 'c1', 'recipient' => '+111', 'first_name' => 'A', 'last_name' => 'B', 'custom_fields' => []],
            ],
            hasMore: false,
            totalCount: 0,
            lastId: 'c1',
        );

        $this->audienceResolver->method('resolve')->willReturn($batch);
        $this->templateEngine->method('render')->willReturnArgument(0);

        $this->dispatcher->expects($this->once())
            ->method('sendQueued')
            ->with(
                $this->anything(),
                null,  // default gateway passes null
            );

        $this->action->execute(
            [],
            ['channel' => 'sms', 'recipient_mode' => 'tag', 'tag_id' => 't1', 'gateway' => '__default__', 'body' => 'Hi'],
        );
    }

    // --- List mode tests ---

    public function testExecuteListModeStaticList(): void
    {
        $this->listRepository->method('find')
            ->with('list-1')
            ->willReturn(['id' => 'list-1', 'name' => 'Subscribers', 'type' => 'static', 'tag_id' => 'tag-sub']);

        $batch = new AudienceBatch(
            recipients: [
                ['contact_id' => 'c1', 'recipient' => '+111', 'first_name' => 'A', 'last_name' => 'B', 'custom_fields' => []],
            ],
            hasMore: false,
            totalCount: 0,
            lastId: 'c1',
        );

        $this->audienceResolver->expects($this->once())
            ->method('resolve')
            ->with(
                $this->callback(fn($a) => $a['sources'][0]['type'] === 'tags' && $a['sources'][0]['tag_ids'] === ['tag-sub']),
                'sms',
                500,
                null,
            )
            ->willReturn($batch);

        $this->templateEngine->method('render')->willReturnArgument(0);
        $this->dispatcher->expects($this->once())->method('sendQueued');

        $result = $this->action->execute(
            [],
            ['channel' => 'sms', 'recipient_mode' => 'list', 'list_id' => 'list-1', 'body' => 'Hi'],
        );

        $this->assertTrue($result->success);
        $this->assertSame(1, $result->output['sent_count']);
    }

    public function testExecuteListModeDynamicList(): void
    {
        $conditions = ['match' => 'all', 'conditions' => [['type' => 'attribute', 'field' => 'status', 'operator' => 'equals', 'value' => 'subscribed']]];

        $this->listRepository->method('find')
            ->with('list-dyn')
            ->willReturn(['id' => 'list-dyn', 'name' => 'Active', 'type' => 'dynamic', 'conditions' => $conditions]);

        $batch = new AudienceBatch(
            recipients: [
                ['contact_id' => 'c1', 'recipient' => '+111', 'first_name' => 'A', 'last_name' => 'B', 'custom_fields' => []],
            ],
            hasMore: false,
            totalCount: 0,
            lastId: 'c1',
        );

        $this->audienceResolver->expects($this->once())
            ->method('resolve')
            ->with(
                $this->callback(fn($a) => $a['sources'][0]['type'] === 'segment' && $a['sources'][0]['conditions'] === $conditions),
                $this->anything(),
                $this->anything(),
                $this->anything(),
            )
            ->willReturn($batch);

        $this->templateEngine->method('render')->willReturnArgument(0);
        $this->dispatcher->expects($this->once())->method('sendQueued');

        $result = $this->action->execute(
            [],
            ['channel' => 'sms', 'recipient_mode' => 'list', 'list_id' => 'list-dyn', 'body' => 'Hi'],
        );

        $this->assertTrue($result->success);
    }

    public function testExecuteListModeDeletedList(): void
    {
        $this->listRepository->method('find')->with('list-gone')->willReturn(null);

        $result = $this->action->execute(
            [],
            ['channel' => 'sms', 'recipient_mode' => 'list', 'list_id' => 'list-gone', 'body' => 'Hi'],
        );

        $this->assertFalse($result->success);
        $this->assertSame('List not found', $result->error);
    }

    public function testExecuteListModeStaticListNoTagId(): void
    {
        $this->listRepository->method('find')
            ->willReturn(['id' => 'list-no-tag', 'name' => 'Bad', 'type' => 'static', 'tag_id' => '']);

        $result = $this->action->execute(
            [],
            ['channel' => 'sms', 'recipient_mode' => 'list', 'list_id' => 'list-no-tag', 'body' => 'Hi'],
        );

        $this->assertFalse($result->success);
        $this->assertSame('List has no associated tag', $result->error);
    }

    public function testExecuteListModeEmptyListId(): void
    {
        $result = $this->action->execute(
            [],
            ['channel' => 'sms', 'recipient_mode' => 'list', 'list_id' => '', 'body' => 'Hi'],
        );

        $this->assertFalse($result->success);
        $this->assertSame('List ID is required', $result->error);
    }

    // --- Tag mode pagination test ---

    public function testExecuteTagModePaginatesMultipleBatches(): void
    {
        $this->tagRepository->method('find')
            ->willReturn(['id' => 't1', 'name' => 'Big']);

        $batch1 = new AudienceBatch(
            recipients: [
                ['contact_id' => 'c1', 'recipient' => '+111', 'first_name' => 'A', 'last_name' => 'B', 'custom_fields' => []],
            ],
            hasMore: true,
            totalCount: 0,
            lastId: 'c1',
        );

        $batch2 = new AudienceBatch(
            recipients: [
                ['contact_id' => 'c2', 'recipient' => '+222', 'first_name' => 'C', 'last_name' => 'D', 'custom_fields' => []],
            ],
            hasMore: false,
            totalCount: 0,
            lastId: 'c2',
        );

        $this->audienceResolver->expects($this->exactly(2))
            ->method('resolve')
            ->willReturnCallback(function ($audience, $channel, $batchSize, $cursor) use ($batch1, $batch2) {
                return $cursor === null ? $batch1 : $batch2;
            });

        $this->templateEngine->method('render')->willReturnArgument(0);
        $this->dispatcher->expects($this->exactly(2))->method('sendQueued');

        $result = $this->action->execute(
            [],
            ['channel' => 'sms', 'recipient_mode' => 'tag', 'tag_id' => 't1', 'body' => 'Hi'],
        );

        $this->assertTrue($result->success);
        $this->assertSame(2, $result->output['sent_count']);
    }

    // --- Existing tests ---

    public function testGetPlaceholdersForOrderCreated(): void
    {
        $placeholders = $this->action->getPlaceholders('woocommerce.order_created');
        $this->assertArrayHasKey('to', $placeholders);
        $this->assertArrayHasKey('body', $placeholders);
        $this->assertStringContainsString('{{customer.phone}}', $placeholders['to']);
    }

    public function testGetPlaceholdersForUserRegister(): void
    {
        $placeholders = $this->action->getPlaceholders('wordpress.user_register');
        $this->assertArrayHasKey('to', $placeholders);
        $this->assertArrayHasKey('body', $placeholders);
        $this->assertStringContainsString('{{user.email}}', $placeholders['to']);
    }

    public function testGetPlaceholdersForPostPublished(): void
    {
        $placeholders = $this->action->getPlaceholders('wordpress.post_published');
        $this->assertArrayHasKey('body', $placeholders);
        $this->assertStringContainsString('{{post_title}}', $placeholders['body']);
    }

    public function testGetPlaceholdersForUnknownTrigger(): void
    {
        $this->assertSame([], $this->action->getPlaceholders('unknown.trigger'));
    }

    public function testConfigSchemaHasMediaUrlField(): void
    {
        $schema = $this->action->getConfigSchema();

        $this->assertArrayHasKey('media_url', $schema);
        $this->assertSame('string', $schema['media_url']['type']);
        $this->assertTrue($schema['media_url']['template']);
        $this->assertSame(
            ['show' => ['channel' => ['sms', 'whatsapp']]],
            $schema['media_url']['displayOptions'],
        );
    }

    public function testBuildMediaMetaParsesCommaSeparatedUrls(): void
    {
        $reflection = new \ReflectionMethod($this->action, 'buildMediaMeta');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($this->action, [
            'media_url' => 'https://example.com/a.jpg, https://example.com/b.png',
        ]);

        $this->assertSame([
            'media_urls' => ['https://example.com/a.jpg', 'https://example.com/b.png'],
        ], $result);
    }

    public function testBuildMediaMetaReturnsEmptyForBlankUrl(): void
    {
        $reflection = new \ReflectionMethod($this->action, 'buildMediaMeta');
        $reflection->setAccessible(true);

        $this->assertSame([], $reflection->invoke($this->action, []));
        $this->assertSame([], $reflection->invoke($this->action, ['media_url' => '']));
        $this->assertSame([], $reflection->invoke($this->action, ['media_url' => '  ']));
    }

    public function testBuildMediaMetaHandlesSingleUrl(): void
    {
        $reflection = new \ReflectionMethod($this->action, 'buildMediaMeta');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($this->action, [
            'media_url' => 'https://example.com/image.jpg',
        ]);

        $this->assertSame([
            'media_urls' => ['https://example.com/image.jpg'],
        ], $result);
    }
}
