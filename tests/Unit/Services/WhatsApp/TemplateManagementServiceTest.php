<?php

namespace Tests\Unit\Services\WhatsApp;

use Tests\TestCase;
use App\Services\WhatsApp\TemplateManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use App\Models\workspace;

class TemplateManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    private TemplateManagementService $service;
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

        $this->service = new TemplateManagementService(
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
        $this->assertInstanceOf(TemplateManagementService::class, $this->service);
        $this->assertEquals('test_token', $this->service->getAccessToken());
        $this->assertEquals('v18.0', $this->service->getApiVersion());
    }

    /** @test */
    public function it_syncs_templates_successfully()
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'data' => [
                    [
                        'name' => 'welcome_template',
                        'status' => 'APPROVED',
                        'category' => 'MARKETING',
                        'language' => 'en'
                    ],
                    [
                        'name' => 'appointment_reminder',
                        'status' => 'APPROVED',
                        'category' => 'UTILITY',
                        'language' => 'en'
                    ]
                ]
            ])
        ]);

        $result = $this->service->syncTemplates();

        $this->assertTrue($result->success);
        $this->assertCount(2, $result->data);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'message_templates');
        });
    }

    /** @test */
    public function it_handles_template_sync_errors()
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => [
                    'message' => 'Invalid access token',
                    'type' => 'OAuthException'
                ]
            ], 401)
        ]);

        $result = $this->service->syncTemplates();

        $this->assertFalse($result->success);
        $this->assertStringContains('Invalid access token', $result->error);
    }

    /** @test */
    public function it_creates_template_with_valid_data()
    {
        $templateData = [
            'name' => 'test_template',
            'category' => 'MARKETING',
            'language' => 'en',
            'components' => [
                [
                    'type' => 'body',
                    'text' => 'Hello {{1}}! Your order {{2}} is ready.'
                ]
            ]
        ];

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'id' => 'new_template_id',
                'status' => 'PENDING'
            ])
        ]);

        $result = $this->service->createTemplate((object) $templateData);

        $this->assertTrue($result->success);
        $this->assertEquals('new_template_id', $result->data['id']);
    }

    /** @test */
    public function it_validates_template_name_format()
    {
        $invalidNames = ['Invalid Name', 'invalid-name', '123invalid', str_repeat('a', 100)];

        foreach ($invalidNames as $name) {
            $templateData = [
                'name' => $name,
                'category' => 'MARKETING',
                'language' => 'en',
                'components' => [
                    ['type' => 'body', 'text' => 'Test message']
                ]
            ];

            $result = $this->service->createTemplate((object) $templateData);
            $this->assertFalse($result->success, "Template name '{$name}' should be invalid");
        }
    }

    /** @test */
    public function it_deletes_template_successfully()
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'success' => true
            ])
        ]);

        $result = $this->service->deleteTemplate('test_template_name');

        $this->assertTrue($result->success);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'test_template_name') &&
                   $request->method() === 'DELETE';
        });
    }

    protected function tearDown(): void
    {
        Http::assertNothingSent();
        parent::tearDown();
    }
}