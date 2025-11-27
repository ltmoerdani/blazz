<?php

namespace Tests\Unit\Services\WhatsApp;

use Tests\TestCase;
use App\Services\WhatsApp\MessageSendingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use App\Models\Contact;
use App\Models\workspace;

class MessageSendingServiceTest extends TestCase
{
    use RefreshDatabase;

    private MessageSendingService $service;
    private workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = workspace::factory()->create([
            'meta_token' => 'test_token',
            'meta_version' => 'v18.0',
            'meta_app_id' => 'test_app_id',
            'meta_phone_number_id' => 'test_phone_id',
            'meta_waba_id' => 'test_waba_id'
        ]);

        $this->service = new MessageSendingService(
            $this->workspace->meta_token,
            $this->workspace->meta_version,
            $this->workspace->meta_app_id,
            $this->workspace->meta_phone_number_id,
            $this->workspace->meta_waba_id,
            $this->workspace->id
        );
    }

    /** @test */
    public function it_can_be_instantiated_with_workspace_credentials()
    {
        $this->assertInstanceOf(MessageSendingService::class, $this->service);
        $this->assertEquals('test_token', $this->service->getAccessToken());
        $this->assertEquals('v18.0', $this->service->getApiVersion());
    }

    /** @test */
    public function it_sends_text_message_successfully()
    {
        $contact = Contact::factory()->create([
            'workspace_id' => $this->workspace->id,
            'phone' => '+1234567890'
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'test_message_id']]
            ])
        ]);

        $result = $this->service->sendMessage($contact->uuid, 'Test message');

        $this->assertTrue($result->success);
        $this->assertEquals('test_message_id', $result->data['message_id']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'messages') &&
                   json_decode($request->body())->messaging_product === 'whatsapp';
        });
    }

    /** @test */
    public function it_handles_api_errors_gracefully()
    {
        $contact = Contact::factory()->create([
            'workspace_id' => $this->workspace->id
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => [
                    'message' => 'Invalid recipient',
                    'type' => 'https://developers.facebook.com/docs/whatsapp/api/errors'
                ]
            ], 400)
        ]);

        $result = $this->service->sendMessage($contact->uuid, 'Test message');

        $this->assertFalse($result->success);
        $this->assertStringContains('Invalid recipient', $result->error);
    }

    /** @test */
    public function it_sends_media_message_with_correct_parameters()
    {
        $contact = Contact::factory()->create([
            'workspace_id' => $this->workspace->id
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'media_message_id']]
            ])
        ]);

        $result = $this->service->sendMedia(
            $contact->uuid,
            'image',
            'test_image.jpg',
            'https://example.com/image.jpg',
            'amazon'
        );

        $this->assertTrue($result->success);
        $this->assertEquals('media_message_id', $result->data['message_id']);
    }

    /** @test */
    public function it_validates_required_parameters()
    {
        // Test empty contact UUID
        $result = $this->service->sendMessage('', 'Test message');
        $this->assertFalse($result->success);
        $this->assertStringContains('Contact UUID is required', $result->error);

        // Test empty message content
        $contact = Contact::factory()->create();
        $result = $this->service->sendMessage($contact->uuid, '');
        $this->assertFalse($result->success);
        $this->assertStringContains('Message content is required', $result->error);
    }

    /** @test */
    public function it_sends_template_messages_with_correct_structure()
    {
        $contact = Contact::factory()->create([
            'workspace_id' => $this->workspace->id
        ]);

        $template = [
            'name' => 'welcome_template',
            'language' => ['code' => 'en'],
            'components' => [
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => 'John Doe']
                    ]
                ]
            ]
        ];

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'template_message_id']]
            ])
        ]);

        $result = $this->service->sendTemplateMessage($contact->uuid, $template);

        $this->assertTrue($result->success);
        $this->assertEquals('template_message_id', $result->data['message_id']);
    }

    protected function tearDown(): void
    {
        Http::assertNothingSent();
        parent::tearDown();
    }
}