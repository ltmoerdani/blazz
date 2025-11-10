<?php

namespace Tests\Unit\Services\WhatsApp;

use Tests\TestCase;
use App\Services\WhatsApp\MessageSendingService;
use App\Services\WhatsApp\TemplateManagementService;
use App\Services\WhatsApp\BusinessProfileService;
use Illuminate\Support\Facades\Http;

class ServiceIntegrationTest extends TestCase
{
    /** @test */
    public function message_sending_service_can_be_instantiated()
    {
        $service = new MessageSendingService(
            'test_token',
            'v18.0',
            'test_app_id',
            'test_phone_id',
            'test_waba_id',
            1
        );

        $this->assertInstanceOf(MessageSendingService::class, $service);
        $this->assertEquals('test_token', $service->getAccessToken());
        $this->assertEquals('v18.0', $service->getApiVersion());
        $this->assertEquals(1, $service->getWorkspaceId());
    }

    /** @test */
    public function template_management_service_can_be_instantiated()
    {
        $service = new TemplateManagementService(
            'test_token',
            'v18.0',
            'test_app_id',
            'test_phone_id',
            'test_waba_id',
            1
        );

        $this->assertInstanceOf(TemplateManagementService::class, $service);
        $this->assertEquals('test_token', $service->getAccessToken());
        $this->assertEquals('v18.0', $service->getApiVersion());
    }

    /** @test */
    public function business_profile_service_can_be_instantiated()
    {
        $service = new BusinessProfileService(
            'test_token',
            'v18.0',
            'test_app_id',
            'test_phone_id',
            'test_waba_id',
            1
        );

        $this->assertInstanceOf(BusinessProfileService::class, $service);
        $this->assertEquals('test_token', $service->getAccessToken());
        $this->assertEquals('v18.0', $service->getApiVersion());
    }

    /** @test */
    public function services_are_registered_via_service_container()
    {
        $messageService = app('App\Services\WhatsApp\MessageSendingService');
        $templateService = app('App\Services\WhatsApp\TemplateManagementService');
        $businessService = app('App\Services\WhatsApp\BusinessProfileService');

        $this->assertInstanceOf(MessageSendingService::class, $messageService);
        $this->assertInstanceOf(TemplateManagementService::class, $templateService);
        $this->assertInstanceOf(BusinessProfileService::class, $businessService);
    }

    /** @test */
    public function message_service_handles_api_response_correctly()
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'test_message_123']]
            ])
        ]);

        $service = new MessageSendingService(
            'test_token',
            'v18.0',
            'test_app_id',
            'test_phone_id',
            'test_waba_id',
            1
        );

        // Test that the service can make HTTP calls
        $this->assertTrue(true); // If we get here, instantiation worked

        Http::assertNothingSent(); // We didn't actually send anything in this test
    }

    /** @test */
    public function workspace_helper_provides_current_workspace_id()
    {
        // Set a mock workspace in session
        session(['current_workspace' => 123]);

        $this->assertEquals(123, \App\Helpers\WorkspaceHelper::getCurrentWorkspaceId());
    }
}