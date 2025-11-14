<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use App\Rules\ContactLimit;
use App\Rules\UniquePhone;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Propaganistas\LaravelPhone\PhoneNumber;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ContactApiController extends Controller
{
    // Constants for repeated string literals
    const VALIDATION_INTEGER_MIN_1 = 'integer|min:1';
    const VALIDATION_INTEGER_MIN_1_MAX_100 = 'integer|min:1|max:100';
    const VALIDATION_MAX_255 = 'max:255';
    const VALIDATION_PHONE_AUTO = 'phone:AUTO';
    const MSG_INVALID_DATA = 'The given data was invalid.';
    const MSG_SUBSCRIPTION_REQUIRED = 'Please renew or subscribe to a plan to continue!';
    const MSG_SUCCESS = 'Request processed successfully';
    const MSG_PROCESSING_ERROR = 'Request unable to be processed';
    const MSG_INVALID_PROVIDED_DATA = 'The provided data is invalid.';
    const MSG_API_KEY_INACTIVE = 'API key is inactive. Please renew or subscribe to a plan to continue!';

    /**
     * Display a listing of the resource.
     */
    public function listContacts(Request $request){
        $workspace = $request->workspace;

        // Check if subscription is active
        if(!SubscriptionService::isSubscriptionActive($workspace->id)){
            return response()->json([
                'message' => self::MSG_SUBSCRIPTION_REQUIRED,
            ], 403);
        }

        $contacts = Contact::where('workspace_id', $workspace->id)->paginate(50);

        return ContactResource::collection($contacts);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function storeContact(Request $request, $uuid = null){
        $workspace = $request->workspace;

        // Check if subscription is active
        if(!SubscriptionService::isSubscriptionActive($workspace->id)){
            return response()->json([
                'message' => self::MSG_SUBSCRIPTION_REQUIRED,
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => ['required', self::VALIDATION_MAX_255],
            'last_name' => ['nullable', self::VALIDATION_MAX_255],
            'email' => ['nullable', 'email', self::VALIDATION_MAX_255],
            'phone' => ['required', self::VALIDATION_PHONE_AUTO, new UniquePhone($workspace->id), new ContactLimit($workspace->id)],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', self::VALIDATION_MAX_255],
            'address' => ['nullable', 'string', self::VALIDATION_MAX_255],
            'city' => ['nullable', 'string', self::VALIDATION_MAX_255],
            'state' => ['nullable', 'string', self::VALIDATION_MAX_255],
            'country' => ['nullable', 'string', self::VALIDATION_MAX_255],
            'postal_code' => ['nullable', 'string', self::VALIDATION_MAX_255],
            'company' => ['nullable', 'string', self::VALIDATION_MAX_255],
            'job_title' => ['nullable', 'string', self::VALIDATION_MAX_255],
            'notes' => ['nullable', 'string', 'max:1000'],
            'custom_fields' => ['nullable', 'array'],
            'custom_fields.*.key' => ['required', 'string', self::VALIDATION_MAX_255],
            'custom_fields.*.value' => ['nullable', 'string', self::VALIDATION_MAX_255],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => self::MSG_INVALID_DATA,
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        try {
            DB::beginTransaction();

            // Format phone number to E.164 format
            $phoneNumber = PhoneNumber::make($validated['phone'])->formatE164();
            $validated['phone'] = $phoneNumber;

            // Remove tags from validated data for contact creation
            $tags = $validated['tags'] ?? [];
            unset($validated['tags']);
            unset($validated['workspace_id']);

            if($uuid){
                $contact = Contact::where('uuid', $uuid)->first();
                if(!$contact){
                    throw new HttpException(404, 'Contact not found');
                }
                $validated['workspace_id'] = $workspace->id;
                $contact->update($validated);
            }else{
                $validated['workspace_id'] = $workspace->id;
                $contact = Contact::create($validated);
            }

            // Sync tags
            if (!empty($tags)) {
                $contact->tags()->sync($tags);
            }

            DB::commit();

            return response()->json([
                'message' => self::MSG_SUCCESS,
                'data' => new ContactResource($contact)
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
     * Remove the specified resource from storage.
     */
    public function destroyContact(Request $request, $uuid){
        $workspace = $request->workspace;

        $contact = Contact::where('uuid', $uuid)
            ->where('workspace_id', $workspace->id)
            ->firstOrFail();

        try {
            DB::beginTransaction();

            // Detach tags
            $contact->tags()->detach();

            // Delete contact
            $contact->delete();

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