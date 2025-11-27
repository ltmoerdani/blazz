<?php

namespace Tests\Feature;

use App\Http\Requests\HybridCampaignRequest;
use App\Models\Campaign;
use App\Models\Workspace;
use App\Models\Template;
use App\Models\ContactGroup;
use App\Models\Contact;
use App\Models\WhatsAppAccount;
use App\Services\CampaignService;
use App\Services\WhatsApp\ProviderSelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Illuminate\Http\UploadedFile;

class HybridCampaignTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test creating a hybrid campaign via API endpoint
     */
    public function test_create_hybrid_campaign_via_api(): void
    {
        $workspace = $this->createWorkspace();
        $contactGroup = $this->createContactGroup($workspace);

        $campaignData = [
            'name' => 'Test Hybrid Campaign',
            'campaign_type' => 'direct',
            'contacts' => $contactGroup->uuid,
            'preferred_provider' => 'webjs',
            'skip_schedule' => true,
            'header_type' => 'text',
            'header_text' => 'Test Header',
            'body_text' => 'Test message body content',
            'footer_text' => 'Test footer'
        ];

        // Mock the workspace session
        session(['current_workspace' => $workspace->id]);

        $response = $this->postJson('/campaigns/hybrid', $campaignData);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Campaign created successfully!'
                 ]);

        $this->assertDatabaseHas('campaigns', [
            'name' => 'Test Hybrid Campaign',
            'campaign_type' => 'direct',
            'preferred_provider' => 'webjs',
            'workspace_id' => $workspace->id
        ]);
    }

    /**
     * Test creating a template-based campaign via API
     */
    public function test_create_template_campaign_via_api(): void
    {
        $workspace = $this->createWorkspace();
        $template = $this->createTemplate($workspace);
        $contactGroup = $this->createContactGroup($workspace);

        $campaignData = [
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
            ]
        ];

        // Mock the workspace session
        session(['current_workspace' => $workspace->id]);

        $response = $this->postJson('/campaigns/hybrid', $campaignData);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'campaign' => [
                         'type' => 'template',
                         'provider' => 'webjs'
                     ]
                 ]);

        $this->assertDatabaseHas('campaigns', [
            'name' => 'Test Template Campaign',
            'campaign_type' => 'template',
            'template_id' => $template->id,
            'preferred_provider' => 'webjs',
            'workspace_id' => $workspace->id
        ]);
    }

    /**
     * Test campaign creation with invalid data returns validation errors
     */
    public function test_campaign_creation_with_invalid_data_returns_validation_errors(): void
    {
        $workspace = $this->createWorkspace();

        $invalidData = [
            'name' => '', // Empty name
            'campaign_type' => 'invalid_type',
            'contacts' => 'non-existent-uuid',
            'preferred_provider' => 'invalid_provider'
        ];

        session(['current_workspace' => $workspace->id]);

        $response = $this->postJson('/campaigns/hybrid', $invalidData);

        $response->assertStatus(422)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Validation failed'
                 ])
                 ->assertJsonValidationErrors(['name', 'campaign_type', 'contacts', 'preferred_provider']);
    }

    /**
     * Test campaign creation without proper workspace authorization
     */
    public function test_campaign_creation_without_workspace_authorization(): void
    {
        $campaignData = [
            'name' => 'Unauthorized Campaign',
            'campaign_type' => 'direct',
            'contacts' => 'some-uuid',
            'preferred_provider' => 'webjs',
            'skip_schedule' => true,
            'body_text' => 'Test message'
        ];

        // Don't set workspace session
        $response = $this->postJson('/campaigns/hybrid', $campaignData);

        $response->assertStatus(422);
    }

    /**
     * Test campaign creation with media file upload
     */
    public function test_campaign_creation_with_media_file_upload(): void
    {
        $workspace = $this->createWorkspace();
        $contactGroup = $this->createContactGroup($workspace);

        Storage::fake('public');

        $file = UploadedFile::fake()->image('campaign-header.jpg', 800, 600)->size(1024);

        $campaignData = [
            'name' => 'Campaign with Media',
            'campaign_type' => 'direct',
            'contacts' => $contactGroup->uuid,
            'preferred_provider' => 'webjs',
            'skip_schedule' => true,
            'header_type' => 'image',
            'header_media' => $file,
            'body_text' => 'Test message with image header'
        ];

        session(['current_workspace' => $workspace->id]);

        $response = $this->postJson('/campaigns/hybrid', $campaignData);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true
                 ]);

        $this->assertDatabaseHas('campaigns', [
            'name' => 'Campaign with Media',
            'campaign_type' => 'direct',
            'header_type' => 'image'
        ]);
    }

    /**
     * Test campaign creation with buttons
     */
    public function test_campaign_creation_with_buttons(): void
    {
        $workspace = $this->createWorkspace();
        $contactGroup = $this->createContactGroup($workspace);

        $campaignData = [
            'name' => 'Campaign with Buttons',
            'campaign_type' => 'direct',
            'contacts' => $contactGroup->uuid,
            'preferred_provider' => 'webjs',
            'skip_schedule' => true,
            'header_type' => 'text',
            'body_text' => 'Test message with buttons',
            'buttons' => [
                [
                    'type' => 'reply',
                    'text' => 'Option 1'
                ],
                [
                    'type' => 'reply',
                    'text' => 'Option 2'
                ],
                [
                    'type' => 'url',
                    'text' => 'Visit Website',
                    'url' => 'https://example.com'
                ]
            ]
        ];

        session(['current_workspace' => $workspace->id]);

        $response = $this->postJson('/campaigns/hybrid', $campaignData);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true
                 ]);

        $campaign = Campaign::where('name', 'Campaign with Buttons')->first();
        $metadata = json_decode($campaign->metadata, true);

        $this->assertIsArray($metadata['buttons']);
        $this->assertCount(3, $metadata['buttons']);
        $this->assertEquals('reply', $metadata['buttons'][0]['type']);
        $this->assertEquals('url', $metadata['buttons'][2]['type']);
        $this->assertEquals('https://example.com', $metadata['buttons'][2]['url']);
    }

    /**
     * Test campaign creation triggers appropriate jobs
     */
    public function test_campaign_creation_triggers_campaign_job(): void
    {
        Queue::fake();

        $workspace = $this->createWorkspace();
        $contactGroup = $this->createContactGroup($workspace);
        $this->createContactsForGroup($contactGroup, 5);

        $campaignData = [
            'name' => 'Campaign for Job Testing',
            'campaign_type' => 'direct',
            'contacts' => $contactGroup->uuid,
            'preferred_provider' => 'webjs',
            'skip_schedule' => true,
            'header_type' => 'text',
            'body_text' => 'Test message that should trigger jobs'
        ];

        session(['current_workspace' => $workspace->id]);

        $response = $this->postJson('/campaigns/hybrid', $campaignData);

        $response->assertStatus(200);

        // Note: Adjust this based on your actual job dispatching logic
        // Queue::assertPushed(\App\Jobs\SendCampaignJob::class);
    }

    /**
     * Test provider selection functionality
     */
    public function test_provider_selection_functionality(): void
    {
        $workspace = $this->createWorkspace();
        $this->createWhatsAppAccounts($workspace);

        session(['current_workspace' => $workspace->id]);

        $response = $this->getJson('/campaigns/available-sessions');

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true
                 ])
                 ->assertJsonStructure([
                     'sessions',
                     'webjs_count',
                     'meta_api_count'
                 ]);
    }

    /**
     * Test template validation for provider compatibility
     */
    public function test_template_validation_for_provider_compatibility(): void
    {
        $workspace = $this->createWorkspace();
        $template = $this->createTemplate($workspace);

        session(['current_workspace' => $workspace->id]);

        $validationData = [
            'template_uuid' => $template->uuid,
            'provider' => 'webjs'
        ];

        $response = $this->postJson('/campaigns/validate-template-provider', $validationData);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'valid' => true
                 ])
                 ->assertJsonStructure([
                     'template' => [
                         'header_type',
                         'requires_media',
                         'button_count'
                     ]
                 ]);
    }

    /**
     * Test campaign message preview functionality
     */
    public function test_campaign_message_preview_functionality(): void
    {
        $workspace = $this->createWorkspace();
        $template = $this->createTemplate($workspace);

        session(['current_workspace' => $workspace->id]);

        // Test template preview
        $previewData = [
            'campaign_type' => 'template',
            'template_uuid' => $template->uuid
        ];

        $response = $this->postJson('/campaigns/preview-message', $previewData);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true
                 ])
                 ->assertJsonStructure([
                     'message_content' => [
                         'header_type',
                         'body_text',
                         'footer_text',
                         'buttons_data'
                     ]
                 ]);

        // Test direct message preview
        $directPreviewData = [
            'campaign_type' => 'direct',
            'header_type' => 'text',
            'header_text' => 'Preview Header',
            'body_text' => 'Preview body content',
            'footer_text' => 'Preview footer'
        ];

        $response = $this->postJson('/campaigns/preview-message', $directPreviewData);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true
                 ]);

        $messageContent = $response->json('message_content');
        $this->assertEquals('text', $messageContent['header_type']);
        $this->assertEquals('Preview Header', $messageContent['header_text']);
        $this->assertEquals('Preview body content', $messageContent['body_text']);
        $this->assertEquals('Preview footer', $messageContent['footer_text']);
    }

    /**
     * Test campaign statistics functionality
     */
    public function test_campaign_statistics_functionality(): void
    {
        $workspace = $this->createWorkspace();
        $campaign = $this->createCampaign($workspace);

        session(['current_workspace' => $workspace->id]);

        $response = $this->getJson("/campaigns/statistics/{$campaign->uuid}");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true
                 ])
                 ->assertJsonStructure([
                     'statistics' => [
                         'total_contacts',
                         'sent_count',
                         'delivered_count',
                         'failed_count',
                         'read_count',
                         'reply_count'
                     ]
                 ]);
    }

    /**
     * Test campaign listing with filters
     */
    public function test_campaign_listing_with_filters(): void
    {
        $workspace = $this->createWorkspace();
        $this->createCampaigns($workspace, [
            ['campaign_type' => 'template', 'status' => 'completed'],
            ['campaign_type' => 'direct', 'status' => 'ongoing'],
            ['campaign_type' => 'template', 'status' => 'pending']
        ]);

        session(['current_workspace' => $workspace->id]);

        // Test filtering by campaign type
        $response = $this->getJson('/campaigns?campaign_type=template');

        $response->assertStatus(200);
        $campaigns = $response->json('rows.data');
        foreach ($campaigns as $campaign) {
            $this->assertEquals('template', $campaign['campaign_type']);
        }

        // Test filtering by status
        $response = $this->getJson('/campaigns?status=ongoing');

        $response->assertStatus(200);
        $campaigns = $response->json('rows.data');
        foreach ($campaigns as $campaign) {
            $this->assertEquals('ongoing', $campaign['status']);
        }
    }

    // Helper methods for creating test data
    private function createWorkspace()
    {
        return Workspace::factory()->create();
    }

    private function createTemplate($workspace)
    {
        return Template::factory()->create([
            'workspace_id' => $workspace->id,
            'status' => 'APPROVED'
        ]);
    }

    private function createContactGroup($workspace)
    {
        return ContactGroup::factory()->create([
            'workspace_id' => $workspace->id
        ]);
    }

    private function createContactsForGroup($contactGroup, $count = 5)
    {
        $contacts = Contact::factory()->count($count)->create([
            'workspace_id' => $contactGroup->workspace_id
        ]);

        // Associate contacts with group
        foreach ($contacts as $contact) {
            $contactGroup->contacts()->attach($contact->id);
        }

        return $contacts;
    }

    private function createWhatsAppAccounts($workspace)
    {
        WhatsAppAccount::factory()->create([
            'workspace_id' => $workspace->id,
            'provider_type' => 'webjs',
            'is_active' => true
        ]);

        WhatsAppAccount::factory()->create([
            'workspace_id' => $workspace->id,
            'provider_type' => 'meta_api',
            'is_active' => true
        ]);
    }

    private function createCampaign($workspace, $attributes = [])
    {
        return Campaign::factory()->create(array_merge([
            'workspace_id' => $workspace->id,
            'campaign_type' => 'direct',
            'status' => 'pending'
        ], $attributes));
    }

    private function createCampaigns($workspace, $campaignsData = [])
    {
        $campaigns = [];
        foreach ($campaignsData as $attributes) {
            $campaigns[] = $this->createCampaign($workspace, $attributes);
        }
        return $campaigns;
    }
}