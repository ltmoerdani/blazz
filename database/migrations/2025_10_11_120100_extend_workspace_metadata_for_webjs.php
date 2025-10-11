<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('workspaces')
            ->select(['id', 'metadata'])
            ->orderBy('id')
            ->chunkById(100, function ($workspaces): void {
                foreach ($workspaces as $workspace) {
                    $metadata = $this->decodeMetadata($workspace->metadata);
                    $metadata['whatsapp'] = $this->mergeWhatsappDefaults($metadata['whatsapp'] ?? []);

                    DB::table('workspaces')
                        ->where('id', $workspace->id)
                        ->update(['metadata' => json_encode($metadata)]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $keysToForget = [
            'webjs_session_id',
            'webjs_phone_number',
            'webjs_status',
            'webjs_last_seen',
            'provider_priority',
        ];

        DB::table('workspaces')
            ->select(['id', 'metadata'])
            ->orderBy('id')
            ->chunkById(100, function ($workspaces) use ($keysToForget): void {
                foreach ($workspaces as $workspace) {
                    $metadata = $this->decodeMetadata($workspace->metadata);

                    if (! isset($metadata['whatsapp']) || ! is_array($metadata['whatsapp'])) {
                        continue;
                    }

                    foreach ($keysToForget as $key) {
                        unset($metadata['whatsapp'][$key]);
                    }

                    if (empty($metadata['whatsapp'])) {
                        unset($metadata['whatsapp']);
                    }

                    DB::table('workspaces')
                        ->where('id', $workspace->id)
                        ->update(['metadata' => empty($metadata) ? null : json_encode($metadata)]);
                }
            });
    }

    private function decodeMetadata(?string $metadata): array
    {
        if (empty($metadata)) {
            return [];
        }

        $decoded = json_decode($metadata, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function mergeWhatsappDefaults(array $whatsapp): array
    {
        $defaults = [
            'provider' => $whatsapp['provider'] ?? 'meta-api',
            'provider_priority' => $whatsapp['provider_priority'] ?? 'webjs',
            'webjs_session_id' => $whatsapp['webjs_session_id'] ?? null,
            'webjs_phone_number' => $whatsapp['webjs_phone_number'] ?? null,
            'webjs_status' => $whatsapp['webjs_status'] ?? 'disconnected',
            'webjs_last_seen' => $whatsapp['webjs_last_seen'] ?? null,
        ];

        return array_merge($defaults, $whatsapp);
    }
};
