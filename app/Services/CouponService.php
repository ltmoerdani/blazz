<?php

namespace App\Services;

use App\Http\Resources\CouponResource;
use App\Models\Coupon;
use Illuminate\Support\Facades\Auth;

class CouponService
{
    /**
     * Get all coupons based on the provided request filters.
     *
     * @param Request $request
     * @return mixed
     */
    public function get(object $request)
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
    public function deletePost($request, $uuid)
    {
        return Coupon::where('uuid', $uuid)->update(['deleted' => 1]);
    } 
}