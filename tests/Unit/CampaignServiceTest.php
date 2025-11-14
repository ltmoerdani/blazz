<?php

namespace Tests\Unit;

use App\Services\CampaignService;
use App\Http\Requests\HybridCampaignRequest;
use App\Models\Campaign;
use App\Models\Workspace;
use App\Models\Template;
use App\Models\ContactGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CampaignServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private CampaignService $campaignService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->campaignService = new CampaignService();
    }

    /**
     * Test creating a template-based campaign
     */
    public function test_create_template_based_campaign(): void
    {
        $workspace = $this->createWorkspace();
        $template = $this->createTemplate($workspace);
        $contactGroup = $this->createContactGroup($workspace);

        $requestData = [
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

        $request = new HybridCampaignRequest($requestData);

        // Mock session for workspace context
        session(['current_workspace' => $workspace->id]);

        $campaign = $this->campaignService->createHybridCampaign($request);

        $this->assertInstanceOf(Campaign::class, $campaign);
        $this->assertEquals('Test Template Campaign', $campaign->name);
        $this->assertEquals('template', $campaign->campaign_type);
        $this->assertEquals('webjs', $campaign->preferred_provider);
        $this->assertEquals($workspace->id, $campaign->workspace_id);
        $this->assertEquals('pending', $campaign->status);
    }

    /**
     * Test creating a direct message campaign
     */
    public function test_create_direct_message_campaign(): void
    {
        $workspace = $this->createWorkspace();
        $contactGroup = $this->createContactGroup($workspace);

        $requestData = [
            'name' => 'Test Direct Campaign',
            'campaign_type' => 'direct',
            'contacts' => $contactGroup->uuid,
            'preferred_provider' => 'meta_api',
            'skip_schedule' => true,
            'header_type' => 'text',
            'header_text' => 'Test Header',
            'body_text' => 'Test message body content',
            'footer_text' => 'Test footer',
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

        $request = new HybridCampaignRequest($requestData);

        // Mock session for workspace context
        session(['current_workspace' => $workspace->id]);

        $campaign = $this->campaignService->createHybridCampaign($request);

        $this->assertInstanceOf(Campaign::class, $campaign);
        $this->assertEquals('Test Direct Campaign', $campaign->name);
        $this->assertEquals('direct', $campaign->campaign_type);
        $this->assertEquals('meta_api', $campaign->preferred_provider);
        $this->assertEquals('Test message body content', $campaign->body_text);
        $this->assertEquals('Test Header', $campaign->header_text);
        $this->assertEquals('Test footer', $campaign->footer_text);
    }

    /**
     * Test creating a scheduled campaign
     */
    public function test_create_scheduled_campaign(): void
    {
        $workspace = $this->createWorkspace();
        $template = $this->createTemplate($workspace);
        $contactGroup = $this->createContactGroup($workspace);

        $scheduledTime = now()->addHours(2);

        $requestData = [
            'name' => 'Test Scheduled Campaign',
            'campaign_type' => 'template',
            'template' => $template->uuid,
            'contacts' => $contactGroup->uuid,
            'preferred_provider' => 'webjs',
            'skip_schedule' => false,
            'scheduled_at' => $scheduledTime->toIso8601String(),
            'header' => [
                'format' => 'TEXT',
                'text' => 'Scheduled Header',
                'parameters' => []
            ],
            'body' => [
                'parameters' => [
                    [
                        'type' => 'text',
                        'text' => 'Scheduled body content'
                    ]
                ]
            ]
        ];

        $request = new HybridCampaignRequest($requestData);

        // Mock session for workspace context
        session(['current_workspace' => $workspace->id]);

        $campaign = $this->campaignService->createHybridCampaign($request);

        $this->assertEquals('scheduled', $campaign->status);
        $this->assertEquals($scheduledTime->format('Y-m-d H:i:s'), $campaign->scheduled_at->format('Y-m-d H:i:s'));
    }

    /**
     * Test campaign metadata is properly stored
     */
    public function test_campaign_metadata_is_properly_stored(): void
    {
        $workspace = $this->createWorkspace();
        $contactGroup = $this->createContactGroup($workspace);

        $requestData = [
            'name' => 'Test Campaign with Metadata',
            'campaign_type' => 'direct',
            'contacts' => $contactGroup->uuid,
            'preferred_provider' => 'webjs',
            'skip_schedule' => true,
            'header_type' => 'text',
            'header_text' => 'Test Header',
            'body_text' => 'Test message body content',
            'footer_text' => 'Test footer',
            'buttons' => [
                [
                    'type' => 'reply',
                    'text' => 'Option 1'
                ]
            ]
        ];

        $request = new HybridCampaignRequest($requestData);

        // Mock session for workspace context
        session(['current_workspace' => $workspace->id]);

        $campaign = $this->campaignService->createHybridCampaign($request);

        $metadata = json_decode($campaign->metadata, true);

        $this->assertIsArray($metadata);
        $this->assertEquals('text', $metadata['header_type']);
        $this->assertEquals('Test Header', $metadata['header_text']);
        $this->assertEquals('Test message body content', $metadata['body_text']);
        $this->assertEquals('Test footer', $metadata['footer_text']);
        $this->assertIsArray($metadata['buttons']);
        $this->assertCount(1, $metadata['buttons']);
        $this->assertEquals('reply', $metadata['buttons'][0]['type']);
    }

    /**
     * Test campaign with WhatsApp session assignment
     */
    public function test_campaign_with_whatsapp_session_assignment(): void
    {
        $workspace = $this->createWorkspace();
        $contactGroup = $this->createContactGroup($workspace);
        $whatsappSession = $this->createWhatsAppSession($workspace);

        $requestData = [
            'name' => 'Test Campaign with Session',
            'campaign_type' => 'direct',
            'contacts' => $contactGroup->uuid,
            'preferred_provider' => 'webjs',
            'whatsapp_session_id' => $whatsappSession->id,
            'skip_schedule' => true,
            'header_type' => 'text',
            'body_text' => 'Test message body content'
        ];

        $request = new HybridCampaignRequest($requestData);

        // Mock session for workspace context
        session(['current_workspace' => $workspace->id]);

        $campaign = $this->campaignService->createHybridCampaign($request);

        $this->assertEquals($whatsappSession->id, $campaign->whatsapp_session_id);
    }

    /**
     * Test campaign created with default provider when not specified
     */
    public function test_campaign_uses_default_provider_when_not_specified(): void
    {
        $workspace = $this->createWorkspace();
        $contactGroup = $this->createContactGroup($workspace);

        $requestData = [
            'name' => 'Test Campaign Default Provider',
            'campaign_type' => 'direct',
            'contacts' => $contactGroup->uuid,
            // preferred_provider not specified
            'skip_schedule' => true,
            'header_type' => 'text',
            'body_text' => 'Test message body content'
        ];

        $request = new HybridCampaignRequest($requestData);

        // Mock session for workspace context
        session(['current_workspace' => $workspace->id]);

        $campaign = $this->campaignService->createHybridCampaign($request);

        // Should default to webjs as per business requirements
        $this->assertEquals('webjs', $campaign->preferred_provider);
    }

    /**
     * Test campaign message content resolution
     */
    public function test_campaign_message_content_resolution(): void
    {
        $workspace = $this->createWorkspace();
        $contactGroup = $this->createContactGroup($workspace);

        $requestData = [
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

        $request = new HybridCampaignRequest($requestData);

        // Mock session for workspace context
        session(['current_workspace' => $workspace->id]);

        $campaign = $this->campaignService->createHybridCampaign($request);

        // Test the model's getResolvedMessageContent method
        $messageContent = $campaign->getResolvedMessageContent();

        $this->assertIsArray($messageContent);
        $this->assertEquals('text', $messageContent['header_type']);
        $this->assertEquals('Test Header', $messageContent['header_text']);
        $this->assertEquals('Test message body content', $messageContent['body_text']);
        $this->assertEquals('Test footer', $messageContent['footer_text']);
    }

    /**
     * Test campaign type detection methods
     */
    public function test_campaign_type_detection_methods(): void
    {
        $workspace = $this->createWorkspace();
        $contactGroup = $this->createContactGroup($workspace);

        // Test direct campaign
        $directRequestData = [
            'name' => 'Test Direct Campaign',
            'campaign_type' => 'direct',
            'contacts' => $contactGroup->uuid,
            'preferred_provider' => 'webjs',
            'skip_schedule' => true,
            'header_type' => 'text',
            'body_text' => 'Test message body content'
        ];

        $request = new HybridCampaignRequest($directRequestData);
        session(['current_workspace' => $workspace->id]);

        $directCampaign = $this->campaignService->createHybridCampaign($request);

        $this->assertFalse($directCampaign->isTemplateBased());
        $this->assertTrue($directCampaign->isDirectMessage());

        // Test template campaign
        $template = $this->createTemplate($workspace);
        $templateRequestData = [
            'name' => 'Test Template Campaign',
            'campaign_type' => 'template',
            'template' => $template->uuid,
            'contacts' => $contactGroup->uuid,
            'preferred_provider' => 'webjs',
            'skip_schedule' => true
        ];

        $templateRequest = new HybridCampaignRequest($templateRequestData);

        $templateCampaign = $this->campaignService->createHybridCampaign($templateRequest);

        $this->assertTrue($templateCampaign->isTemplateBased());
        $this->assertFalse($templateCampaign->isDirectMessage());
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

    private function createWhatsAppSession($workspace)
    {
        return \App\Models\WhatsAppSession::factory()->create([
            'workspace_id' => $workspace->id,
            'provider_type' => 'webjs'
        ]);
    }
}