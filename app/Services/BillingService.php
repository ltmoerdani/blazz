<?php

namespace App\Services;

use App\Http\Resources\BillingResource;
use App\Models\BillingCredit;
use App\Models\BillingDebit;
use App\Models\BillingPayment;
use App\Models\BillingTransaction;
use App\Models\workspace;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BillingService
{
    private $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService = null)
    {
        $this->subscriptionService = $subscriptionService ?: new SubscriptionService();
    }
    /**
     * Get all billing history based on the provided request filters.
     *
     * @param Request $request
     * @return mixed
     */
    public function get(object $request, $workspaceUuid = null)
    {
        if ($workspaceUuid !== null) {
            $workspace = workspace::with('subscription.plan')->where('uuid', $workspaceUuid)->first();
            $workspaceId = optional($workspace)->id;
        } else {
            $workspaceId = null;
        }

        $rows = (new BillingTransaction)->listAll($request->query('search'), $workspaceId);

        return BillingResource::collection($rows);
    }

    /**
     * Store a new billing transaction
     *
     * @param Request $request
     */
    public function store($request){
        return DB::transaction(function () use ($request) {
            $workspace = workspace::where('uuid', $request->uuid)->firstOrFail();
    
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
    
            $transaction = BillingTransaction::create([
                'workspace_id' => $workspace->id,
                'entity_type' => $request->type,
                'entity_id' => $entry->id,
                'description' => $request->type === 'payment' ? $request->method . ' Transaction' : $request->description,
                'amount' => $request->type === 'debit' || $request->type === 'invoice' ? -$request->amount : $request->amount,
                'created_by' => Auth::id()
            ]);

            //Activate workspace's plan if credits cover cost of plan
            if ($this->subscriptionService) {
                $this->subscriptionService->activateSubscriptionIfInactiveAndExpiredWithCredits($workspace->id, Auth::id());
            }

            return $transaction;
        });
    }
}
