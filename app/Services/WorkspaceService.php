<?php

namespace App\Services;

use App\Http\Resources\WorkspacesResource;
use App\Http\Resources\BillingResource;
use App\Http\Resources\UserResource;
use App\Models\BillingCredit;
use App\Models\BillingDebit;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\BillingTransaction;
use App\Models\workspace;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\Template;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Propaganistas\LaravelPhone\PhoneNumber;

class WorkspaceService
{
    /**
     * Get all workspaces based on the provided request filters.
     *
     * @param Request $request
     * @return mixed
     */
    public function get(object $request, $userId = null)
    {
        $workspaces = (new workspace)->listAll($request->query('search'), $userId);

        return WorkspacesResource::collection($workspaces);
    }

    /**
     * Retrieve an workspace by its UUID.
     *
     * @param string $uuid
     * @return \App\Models\workspace
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getByUuid($request, $uuid = null)
    {
        $result['plans'] = SubscriptionPlan::all();

        if ($uuid === null) {
            $result['workspace'] = null;
            $result['billing'] = null;
            $result['users'] = null;
    
            return $result;
        }

        $workspace = workspace::with('subscription.plan')->where('uuid', $uuid)->first();
        $users = (new User)->listAll('user', $request->query('search'), $workspace->id);
        $billing = (new BillingTransaction)->listAll($request->query('search'), $workspace->id);
        
        $result['workspace'] = $workspace;
        $result['billing'] = BillingResource::collection($billing);
        $result['users'] = UserResource::collection($users);

        return $result;
    }

    /**
     * Store a new workspace based on the provided request data.
     *
     * @param Request $request
     */
    public function store(Object $request)
    {
        return DB::transaction(function () use ($request) {
            if($request->input('create_user') == 1){
                //Create and attach user to workspace
                $user = User::create([
                    'first_name' => $request->input('first_name'),
                    'last_name' => $request->input('last_name'),
                    'email' => $request->input('email'),
                    'role' => 'user',
                    'phone' => $request->input('phone') ? phone($request->input('phone'))->formatE164() : null,
                    'address' => json_encode([
                        'street' => $request->input('street'),
                        'city' => $request->input('city'),
                        'state' => $request->input('state'),
                        'zip' => $request->input('zip'),
                        'country' => $request->input('country'),
                    ]),
                    'password' => $request->input('password'),
                ]);
            } else {
                //Attach existng user to workspace
                $user = User::where('email', $request->input('email'))->first();
            }

            $timestamp = now()->format('YmdHis');
            $randomString = Str::random(4);
            $userId = $user->id;

            $workspace = workspace::create([
                'name' => $request->input('name'),
                'identifier' => $timestamp . $userId . $randomString,
                'address' => json_encode([
                    'street' => $request->street,
                    'city' => $request->city,
                    'state' => $request->state,
                    'zip' => $request->zip,
                    'country' => $request->country,
                ]),
                'created_by' => Auth::id(),
            ]);

            Team::create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'role' => 'owner',
                'status' => 'active',
                'created_by' => Auth::id(),
            ]);

            $plan = SubscriptionPlan::where('uuid', $request->plan)->first();
            $config = Setting::where('key', 'trial_period')->first();
            $has_trial = isset($config->value) && $config->value > 0 ? true : false;

            Subscription::create([
                'workspace_id' => $workspace->id,
                'status' => $has_trial ? 'trial' : 'active',
                'plan_id' => $plan ? $plan->id : null,
                'start_date' => now(),
                'valid_until' => $has_trial ? date('Y-m-d H:i:s', strtotime('+' . $config->value . ' days')) : now(),
            ]);

            return $workspace;
        });
    }

    /**
     * Update workspace.
     *
     * @param Request $request
     * @param string $uuid
     * @return \App\Models\workspace
     */
    public function update($request, $uuid)
    {
        $workspace = workspace::where('uuid', $uuid)->firstOrFail();

        $workspace->update([
            'name' => $request->input('name'),
            'address' => json_encode([
                'street' => $request->street,
                'city' => $request->city,
                'state' => $request->state,
                'zip' => $request->zip,
                'country' => $request->country,
            ]),
        ]);

        $subscription = Subscription::where('workspace_id', $workspace->id)->first();
        $plan = SubscriptionPlan::where('uuid', $request->plan)->first();

        if($subscription){
            $subscription->update([
                'plan_id' => $plan->id
            ]);
        } else {
            $config = Setting::where('key', 'trial_period')->first();
            $has_trial = isset($config->value) && $config->value > 0 ? true : false;
            
            Subscription::create([
                'workspace_id' => $workspace->id,
                'status' => $has_trial ? 'trial' : 'active',
                'plan_id' => $plan->id,
                'start_date' => now(),
                'valid_until' => $has_trial ? date('Y-m-d H:i:s', strtotime('+' . $config->value . ' days')) : now(),
            ]);
        }

        return $workspace;
    }

    public function storeTransaction($request, $uuid){
        return DB::transaction(function () use ($request, $uuid) {
            $workspace = workspace::where('uuid', $uuid)->firstOrFail();
    
            $modelClass = match ($request->type) {
                'credit' => BillingCredit::class,
                'debit' => BillingDebit::class,
                'payment' => BillingPayment::class,
            };

            $transactionData = [
                'workspace_id' => $workspace->id,
                'amount' => $request->amount,
            ];
            
            if (in_array($request->type, ['credit', 'debit'])) {
                $transactionData['description'] = $request->description;
            }
            
            if ($request->type === 'payment') {
                $transactionData['processor'] = $request->method;
            }
    
            $entry = $modelClass::create($transactionData);
    
            return BillingTransaction::create([
                'workspace_id' => $workspace->id,
                'entity_type' => $request->type,
                'entity_id' => $entry->id,
                'description' => $request->type === 'payment' ? $request->method . ' Transaction' : $request->description,
                'amount' => $request->amount,
                'created_by' => Auth::id()
            ]);
        });
    }

    public function destroy($uuid){
        // Find the workspace by its UUID
        $workspace = workspace::where('uuid', $uuid)->first();

        if ($workspace) {
            // Delete all teams associated with the workspace
            Team::where('workspace_id', $workspace->id)->delete();
            
            // Delete the workspace
            $workspace->delete();

            // Return true to indicate successful deletion
            return true;
        }

        // Return false if the workspace does not exist
        return false;
    }
}
