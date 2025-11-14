<?php

namespace Tests\Unit;

use App\Http\Requests\HybridCampaignRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use Illuminate\Validation\ValidationException;

class HybridCampaignRequestTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test validation for template-based campaign with valid data
     */
    public function test_template_campaign_validation_passes_with_valid_data(): void
    {
        $workspace = $this->createWorkspace();
        $template = $this->createTemplate($workspace);
        $contactGroup = $this->createContactGroup($workspace);

        $data = [
            'name' => 'Test Template Campaign',
            'campaign_type' => 'template',
            'template' => $template->uuid,
            'contacts' => $contactGroup->uuid,
            'preferred_provider' => 'webjs',
            'skip_schedule' => true,
            'header' => [
                'format' => 'TEXT',
                'text' => 'Test Header',
                'parameters' => []
            ],
            'body' => [
                'parameters' => [
                    [
                        'type' => 'text',
                        'text' => 'Test body content'
                    ]
                ]
            ],
            'footer' => [
                'text' => 'Test footer'
            ]
        ];

        $request = HybridCampaignRequest::create('/campaigns/hybrid', 'POST', $data);
        $this->assertTrue($request->validate());
    }

    /**
     * Test validation for direct message campaign with valid data
     */
    public function test_direct_campaign_validation_passes_with_valid_data(): void
    {
        $workspace = $this->createWorkspace();
        $contactGroup = $this->createContactGroup($workspace);

        $data = [
            'name' => 'Test Direct Campaign',
            'campaign_type' => 'direct',
            'contacts' => $contactGroup->uuid,
            'preferred_provider' => 'webjs',
            'skip_schedule' => true,
            'header_type' => 'text',
            'header_text' => 'Test Header',
            'body_text' => 'Test message body content',
            'footer_text' => 'Test footer'
        ];

        $request = HybridCampaignRequest::create('/campaigns/hybrid', 'POST', $data);
        $this->assertTrue($request->validate());
    }

    /**
     * Test validation fails for template campaign without required fields
     */
    public function test_template_campaign_validation_fails_without_required_fields(): void
    {
        $data = [
            'name' => 'Test Template Campaign',
            'campaign_type' => 'template',
            'contacts' => 'invalid-uuid',
            'skip_schedule' => true
        ];

        $this->expectException(ValidationException::class);

        $request = HybridCampaignRequest::create('/campaigns/hybrid', 'POST', $data);
        $request->validate();
    }

    /**
     * Test validation fails for direct campaign without body text
     */
    public function test_direct_campaign_validation_fails_without_body_text(): void
    {
        $data = [
            'name' => 'Test Direct Campaign',
            'campaign_type' => 'direct',
            'contacts' => 'invalid-uuid',
            'skip_schedule' => true,
            'header_type' => 'text',
            'header_text' => 'Test Header'
            // Missing required body_text
        ];

        $this->expectException(ValidationException::class);

        $request = HybridCampaignRequest::create('/campaigns/hybrid', 'POST', $data);
        $request->validate();
    }

    /**
     * Test validation fails for invalid campaign type
     */
    public function test_validation_fails_for_invalid_campaign_type(): void
    {
        $data = [
            'name' => 'Test Campaign',
            'campaign_type' => 'invalid_type',
            'skip_schedule' => true
        ];

        $this->expectException(ValidationException::class);

        $request = HybridCampaignRequest::create('/campaigns/hybrid', 'POST', $data);
        $request->validate();
    }

    /**
     * Test validation fails for invalid provider
     */
    public function test_validation_fails_for_invalid_provider(): void
    {
        $workspace = $this->createWorkspace();
        $contactGroup = $this->createContactGroup($workspace);

        $data = [
            'name' => 'Test Campaign',
            'campaign_type' => 'direct',
            'contacts' => $contactGroup->uuid,
            'preferred_provider' => 'invalid_provider',
            'skip_schedule' => true,
            'body_text' => 'Test body content'
        ];

        $this->expectException(ValidationException::class);

        $request = HybridCampaignRequest::create('/campaigns/hybrid', 'POST', $data);
        $request->validate();
    }

    /**
     * Test validation for media file in direct campaign
     */
    public function test_direct_campaign_validation_with_media_file(): void
    {
        $workspace = $this->createWorkspace();
        $contactGroup = $this->createContactGroup($workspace);

        $file = UploadedFile::fake()->image('test-image.jpg', 800, 600)->size(1024);

        $data = [
            'name' => 'Test Direct Campaign with Media',
            'campaign_type' => 'direct',
            'contacts' => $contactGroup->uuid,
            'preferred_provider' => 'webjs',
            'skip_schedule' => true,
            'header_type' => 'image',
            'header_media' => $file,
            'body_text' => 'Test message body with image header'
        ];

        $request = HybridCampaignRequest::create('/campaigns/hybrid', 'POST', $data);
        $this->assertTrue($request->validate());
    }

    /**
     * Test validation fails for oversized media file
     */
    public function test_direct_campaign_validation_fails_for_oversized_media(): void
    {
        $workspace = $this->createWorkspace();
        $contactGroup = $this->createContactGroup($workspace);

        $file = UploadedFile::fake()->create('large-video.mp4', 20000); // 20MB, exceeds 16MB limit

        $data = [
            'name' => 'Test Direct Campaign with Large Media',
            'campaign_type' => 'direct',
            'contacts' => $contactGroup->uuid,
            'preferred_provider' => 'webjs',
            'skip_schedule' => true,
            'header_type' => 'video',
            'header_media' => $file,
            'body_text' => 'Test message body with video header'
        ];

        $this->expectException(ValidationException::class);

        $request = HybridCampaignRequest::create('/campaigns/hybrid', 'POST', $data);
        $request->validate();
    }

    /**
     * Test validation for button configuration
     */
    public function test_direct_campaign_validation_with_buttons(): void
    {
        $workspace = $this->createWorkspace();
        $contactGroup = $this->createContactGroup($workspace);

        $data = [
            'name' => 'Test Direct Campaign with Buttons',
            'campaign_type' => 'direct',
            'contacts' => $contactGroup->uuid,
            'preferred_provider' => 'webjs',
            'skip_schedule' => true,
            'header_type' => 'text',
            'body_text' => 'Test message body with buttons',
            'buttons' => [
                [
                    'type' => 'reply',
                    'text' => 'Option 1'
                ],
                [
                    'type' => 'reply',
                    'text' => 'Option 2'
                ]
            ]
        ];

        $request = HybridCampaignRequest::create('/campaigns/hybrid', 'POST', $data);
        $this->assertTrue($request->validate());
    }

    /**
     * Test validation fails for too many reply buttons
     */
    public function test_direct_campaign_validation_fails_for_too_many_reply_buttons(): void
    {
        $workspace = $this->createWorkspace();
        $contactGroup = $this->createContactGroup($workspace);

        $data = [
            'name' => 'Test Direct Campaign with Too Many Buttons',
            'campaign_type' => 'direct',
            'contacts' => $contactGroup->uuid,
            'preferred_provider' => 'webjs',
            'skip_schedule' => true,
            'header_type' => 'text',
            'body_text' => 'Test message body with too many buttons',
            'buttons' => [
                ['type' => 'reply', 'text' => 'Option 1'],
                ['type' => 'reply', 'text' => 'Option 2'],
                ['type' => 'reply', 'text' => 'Option 3'],
                ['type' => 'reply', 'text' => 'Option 4'] // Exceeds limit of 3
            ]
        ];

        $this->expectException(ValidationException::class);

        $request = HybridCampaignRequest::create('/campaigns/hybrid', 'POST', $data);
        $request->validate();
    }

    /**
     * Test validation for scheduled campaigns
     */
    public function test_scheduled_campaign_validation_with_future_date(): void
    {
        $workspace = $this->createWorkspace();
        $contactGroup = $this->createContactGroup($workspace);

        $data = [
            'name' => 'Test Scheduled Campaign',
            'campaign_type' => 'direct',
            'contacts' => $contactGroup->uuid,
            'preferred_provider' => 'webjs',
            'skip_schedule' => false,
            'scheduled_at' => now()->addHours(2)->toIso8601String(),
            'body_text' => 'Test scheduled message'
        ];

        $request = HybridCampaignRequest::create('/campaigns/hybrid', 'POST', $data);
        $this->assertTrue($request->validate());
    }

    /**
     * Test validation fails for past schedule date
     */
    public function test_scheduled_campaign_validation_fails_for_past_date(): void
    {
        $workspace = $this->createWorkspace();
        $contactGroup = $this->createContactGroup($workspace);

        $data = [
            'name' => 'Test Scheduled Campaign',
            'campaign_type' => 'direct',
            'contacts' => $contactGroup->uuid,
            'preferred_provider' => 'webjs',
            'skip_schedule' => false,
            'scheduled_at' => now()->subHour()->toIso8601String(), // Past date
            'body_text' => 'Test scheduled message'
        ];

        $this->expectException(ValidationException::class);

        $request = HybridCampaignRequest::create('/campaigns/hybrid', 'POST', $data);
        $request->validate();
    }

    // Helper methods for creating test data
    private function createWorkspace()
    {
        return \App\Models\Workspace::factory()->create();
    }

    private function createTemplate($workspace)
    {
        return \App\Models\Template::factory()->create([
            'workspace_id' => $workspace->id,
            'status' => 'APPROVED'
        ]);
    }

    private function createContactGroup($workspace)
    {
        return \App\Models\ContactGroup::factory()->create([
            'workspace_id' => $workspace->id
        ]);
    }
}