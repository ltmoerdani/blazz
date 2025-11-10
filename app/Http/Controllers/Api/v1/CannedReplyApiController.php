<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CannedReplyResource;
use App\Models\AutoReply;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CannedReplyApiController extends Controller
{
    // Constants for repeated string literals
    const VALIDATION_INTEGER_MIN_1 = 'integer|min:1';
    const VALIDATION_INTEGER_MIN_1_MAX_100 = 'integer|min:1|max:100';
    const VALIDATION_MAX_255 = 'max:255';
    const MSG_SUCCESS = 'Request processed successfully';
    const MSG_PROCESSING_ERROR = 'Request unable to be processed';
    const MSG_SUBSCRIPTION_REQUIRED = 'Please renew or subscribe to a plan to continue!';

    /**
     * Display a listing of canned replies.
     */
    public function listCannedReplies(Request $request){
        $workspace = $request->workspace;

        // Check if subscription is active
        if(!SubscriptionService::isSubscriptionActive($workspace->id)){
            return response()->json([
                'message' => self::MSG_SUBSCRIPTION_REQUIRED,
            ], 403);
        }

        $cannedReplies = AutoReply::where('workspace_id', $workspace->id)
            ->where('deleted_at', null)
            ->paginate(50);

        return CannedReplyResource::collection($cannedReplies);
    }

    /**
     * Store a newly created canned reply in storage.
     */
    public function storeCannedReply(Request $request, $uuid = null){
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
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', self::VALIDATION_MAX_255],
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
                $cannedReply = AutoReply::where('uuid', $uuid)->first();
                if(!$cannedReply){
                    return response()->json([
                        'message' => 'Canned reply not found'
                    ], 404);
                }
                $cannedReply->update($validated);
            }else{
                $cannedReply = AutoReply::create($validated);
            }

            DB::commit();

            return response()->json([
                'message' => self::MSG_SUCCESS,
                'data' => new CannedReplyResource($cannedReply)
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
     * Remove the specified canned reply from storage.
     */
    public function destroyCannedReply(Request $request, $uuid){
        $workspace = $request->workspace;

        $cannedReply = AutoReply::where('uuid', $uuid)
            ->where('workspace_id', $workspace->id)
            ->firstOrFail();

        try {
            DB::beginTransaction();

            // Delete canned reply
            $cannedReply->delete();

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