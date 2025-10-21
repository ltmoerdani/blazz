<?php

namespace App\Http\Resources;

use App\Helpers\DateTimeHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AutoReplyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        // Convert updated_at to the workspace's timezone and format it
        $updatedAt = DateTimeHelper::convertToWorkspaceTimezone($this->updated_at);
        $data['updated_at'] = DateTimeHelper::formatDate($updatedAt);

        return $data;
    }
}
