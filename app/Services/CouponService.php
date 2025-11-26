<?php

namespace App\Services;

use App\Http\Resources\CouponResource;
use App\Models\Coupon;
use Illuminate\Support\Facades\Auth;

class CouponService
{
    private $workspaceId;

    public function __construct($workspaceId = null)
    {
        // Backward compatible: fallback to session if not provided
        $this->workspaceId = $workspaceId ?? session('current_workspace');
    }

    /**
     * Get all coupons based on the provided request filters.
     *
     * @param Request $request
     * @return mixed
     */
    public function get()
    {
        $rows = Coupon::where('deleted_at', null)->latest()->paginate(10);

        return CouponResource::collection($rows);
    }

    /**
     * Store Coupon
     *
     * @param Request $request
     * @param string $uuid
     * @return \App\Models\Coupon
     */
    public function storeCategory(object $request, $uuid = null)
    {
        $category = $uuid === null ? new Coupon() : Coupon::where('uuid', $uuid)->firstOrFail();

        $category->name = $request->name;
        $category->created_by = Auth::id();
        $category->save();

        return $category;
    }

    /**
     * Delete Coupon
     *
     * @param Request $request
     * @param string $uuid
     * @return bool
     */
    public function deletePost($uuid)
    {
        return Coupon::where('uuid', $uuid)->update(['deleted' => 1]);
    }
}
