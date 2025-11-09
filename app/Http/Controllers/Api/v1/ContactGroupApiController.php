<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContactGroupResource;
use App\Models\ContactGroup;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ContactGroupApiController extends Controller
{
    // Constants for repeated string literals
    const VALIDATION_INTEGER_MIN_1 = 'integer|min:1';
    const VALIDATION_INTEGER_MIN_1_MAX_100 = 'integer|min:1|max:100';
    const VALIDATION_MAX_255 = 'max:255';
    const MSG_SUCCESS = 'Request processed successfully';
    const MSG_PROCESSING_ERROR = 'Request unable to be processed';
    const MSG_SUBSCRIPTION_REQUIRED = 'Please renew or subscribe to a plan to continue!';

    /**
     * Display a listing of contact groups.
     */
    public function listContactGroups(Request $request){
        $workspace = $request->workspace;

        // Check if subscription is active
        if(!SubscriptionService::isSubscriptionActive($workspace->id)){
            return response()->json([
                'message' => self::MSG_SUBSCRIPTION_REQUIRED,
            ], 403);
        }

        $contactGroups = ContactGroup::where('workspace_id', $workspace->id)
            ->where('deleted_at', null)
            ->paginate(50);

        return ContactGroupResource::collection($contactGroups);
    }

    /**
     * Store a newly created contact group in storage.
     */
    public function storeContactGroup(Request $request, $uuid = null){
        $workspace = $request->workspace;

        // Check if subscription is active
        if(!SubscriptionService::isSubscriptionActive($workspace->id)){
            return response()->json([
                'message' => self::MSG_SUBSCRIPTION_REQUIRED,
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', self::VALIDATION_MAX_255],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        try {
            DB::beginTransaction();

            $validated['workspace_id'] = $workspace->id;

            if($uuid){
                $contactGroup = ContactGroup::where('uuid', $uuid)->first();
                if(!$contactGroup){
                    return response()->json([
                        'message' => 'Contact group not found'
                    ], 404);
                }
                $contactGroup->update($validated);
            }else{
                $contactGroup = ContactGroup::create($validated);
            }

            DB::commit();

            return response()->json([
                'message' => self::MSG_SUCCESS,
                'data' => new ContactGroupResource($contactGroup)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => self::MSG_PROCESSING_ERROR,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified contact group from storage.
     */
    public function destroyContactGroup(Request $request, $uuid){
        $workspace = $request->workspace;

        $contactGroup = ContactGroup::where('uuid', $uuid)
            ->where('workspace_id', $workspace->id)
            ->firstOrFail();

        try {
            DB::beginTransaction();

            // Delete contact group
            $contactGroup->delete();

            DB::commit();

            return response()->json([
                'message' => self::MSG_SUCCESS
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => self::MSG_PROCESSING_ERROR,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}