<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Addon;
use App\Models\Contact;
use App\Models\Workspace;
use App\Services\ContactFieldService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class AdminGeneralSettingsController extends BaseController
{
    public function __construct(
        private ContactFieldService $contactFieldService
    ) {
        // Constructor injection - general settings services
    }

    /**
     * Display main settings page
     */
    public function index(Request $request, $display = null)
    {
        if ($request->isMethod('get')) {
            $workspaceId = $this->getWorkspaceId();
            $data['title'] = __('Settings');
            $data['settings'] = workspace::where('id', $workspaceId)->first();
            $data['timezones'] = config('formats.timezones');
            $data['countries'] = config('formats.countries');
            $data['sounds'] = config('sounds');
            $data['modules'] = Addon::get();
            $contactModel = new Contact;
            $data['contactGroups'] = $contactModel->getAllContactGroups($workspaceId);

            return Inertia::render('User/Settings/General', $data);
        }
    }

    /**
     * Display mobile view of settings
     */
    public function mobileView(Request $request)
    {
        $data['title'] = __('Settings');
        $data['settings'] = workspace::where('id', $this->getWorkspaceId())->first();
        return Inertia::render('User/Settings/Main', $data);
    }

    /**
     * Display general settings page
     */
    public function viewGeneralSettings(Request $request)
    {
        $contactModel = new Contact;
        $workspaceId = $this->getWorkspaceId();
        $data['title'] = __('Settings');
        $data['settings'] = workspace::where('id', $this->getWorkspaceId())->first();
        $data['modules'] = Addon::get();
        $data['contactGroups'] = $contactModel->getAllContactGroups($workspaceId);

        return Inertia::render('User/Settings/General', $data);
    }

    /**
     * Display contacts settings page
     */
    public function contacts(Request $request)
    {
        $workspaceId = $this->getWorkspaceId();
        $contactModel = new Contact;

        $data = [
            'title' => __('Contact Settings'),
            'settings' => workspace::where('id', $workspaceId)->first(),
            'contactFields' => $this->contactFieldService->getFields($workspaceId),
            'contactGroups' => $contactModel->getAllContactGroups($workspaceId),
            'modules' => Addon::get(),
            'countries' => config('formats.countries'),
            'timezones' => config('formats.timezones'),
        ];

        return Inertia::render('Admin/Settings/Contacts', $data);
    }

    /**
     * Display tickets settings page
     */
    public function tickets(Request $request)
    {
        $workspaceId = $this->getWorkspaceId();

        $data = [
            'title' => __('Ticket Settings'),
            'settings' => workspace::where('id', $workspaceId)->first(),
            'modules' => Addon::get(),
            'sounds' => config('sounds'),
        ];

        return Inertia::render('Admin/Settings/Tickets', $data);
    }

    /**
     * Display automation settings page
     */
    public function automation(Request $request)
    {
        $workspaceId = $this->getWorkspaceId();

        $data = [
            'title' => __('Automation Settings'),
            'settings' => workspace::where('id', $workspaceId)->first(),
            'modules' => Addon::get(),
        ];

        return Inertia::render('Admin/Settings/Automation', $data);
    }

    /**
     * Update general workspace settings
     */
    public function updateGeneralSettings(Request $request)
    {
        $workspaceId = $this->getWorkspaceId();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'timezone' => 'required|string|timezone',
            'country' => 'required|string|size:2',
            'currency' => 'required|string|size:3',
            'date_format' => 'required|string|max:20',
            'time_format' => 'required|string|in:12h,24h',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $workspace = workspace::findOrFail($workspaceId);
            $workspace->update($request->only([
                'name', 'timezone', 'country', 'currency', 'date_format', 'time_format'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'General settings updated successfully',
                'data' => $workspace
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update notification settings
     */
    public function updateNotificationSettings(Request $request)
    {
        $workspaceId = $this->getWorkspaceId();

        $validator = Validator::make($request->all(), [
            'email_notifications' => 'boolean',
            'sound_notifications' => 'boolean',
            'browser_notifications' => 'boolean',
            'notification_sound' => 'string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $workspace = workspace::findOrFail($workspaceId);

            // Update metadata with notification settings
            $metadata = $workspace->metadata ? json_decode($workspace->metadata, true) : [];
            $metadata['notifications'] = $request->only([
                'email_notifications', 'sound_notifications', 'browser_notifications', 'notification_sound'
            ]);

            $workspace->update(['metadata' => json_encode($metadata)]);

            return response()->json([
                'success' => true,
                'message' => 'Notification settings updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update notification settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update contact field settings
     */
    public function updateContactFieldSettings(Request $request)
    {
        $workspaceId = $this->getWorkspaceId();

        $validator = Validator::make($request->all(), [
            'custom_fields' => 'array',
            'custom_fields.*.name' => 'required|string|max:255',
            'custom_fields.*.type' => 'required|string|in:text,email,phone,date,select,textarea',
            'custom_fields.*.required' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $this->contactFieldService->updateFields($workspaceId, $request->input('custom_fields', []));

            return response()->json([
                'success' => true,
                'message' => 'Contact field settings updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update contact field settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get workspace settings for API
     */
    public function getSettings(Request $request)
    {
        $workspaceId = $this->getWorkspaceId();

        try {
            $workspace = workspace::findOrFail($workspaceId);

            $settings = [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'timezone' => $workspace->timezone,
                'country' => $workspace->country,
                'currency' => $workspace->currency,
                'date_format' => $workspace->date_format,
                'time_format' => $workspace->time_format,
                'metadata' => $workspace->metadata ? json_decode($workspace->metadata, true) : [],
            ];

            return response()->json([
                'success' => true,
                'data' => $settings
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get settings: ' . $e->getMessage()
            ], 500);
        }
    }
}