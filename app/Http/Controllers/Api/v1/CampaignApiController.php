<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CampaignResource;
use App\Models\Campaign;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CampaignApiController extends Controller
{
    // Constants for repeated string literals
    const VALIDATION_INTEGER_MIN_1 = 'integer|min:1';
    const VALIDATION_INTEGER_MIN_1_MAX_100 = 'integer|min:1|max:100';
    const VALIDATION_MAX_255 = 'max:255';
    const MSG_SUCCESS = 'Request processed successfully';
    const MSG_PROCESSING_ERROR = 'Request unable to be processed';
    const MSG_SUBSCRIPTION_REQUIRED = 'Please renew or subscribe to a plan to continue!';

    /**
     * Store a newly created campaign in storage.
     */
    public function storeCampaign(Request $request){
        $workspace = $request->workspace;

        // Check if subscription is active
        if(!SubscriptionService::isSubscriptionActive($workspace->id)){
            return response()->json([
                'message' => self::MSG_SUBSCRIPTION_REQUIRED,
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', self::VALIDATION_MAX_255],
            'message' => ['required', 'string', 'max:2000'],
            'scheduled_at' => ['nullable', 'date'],
            'contact_groups' => ['nullable', 'array'],
            'contact_groups.*' => ['string'],
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
            $validated['status'] = 'pending';
            $validated['created_at'] = now();

            $campaign = Campaign::create($validated);

            DB::commit();

            return response()->json([
                'message' => self::MSG_SUCCESS,
                'data' => new CampaignResource($campaign)
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