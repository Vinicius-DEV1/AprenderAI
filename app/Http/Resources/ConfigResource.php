<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConfigResource extends JsonResource
{
    /**
     * Desabilita o wrapping automático para este resource especificamente.
     * O resource já retorna dentro de "data" por padrão no JsonResource.
     */

    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'app_name' => $this->resource['app_name'],
            'ai_name' => $this->resource['ai_name'],
            'app_version' => $this->resource['app_version'],
            'google_login_enabled' => $this->resource['google_login_enabled'],
            'features' => $this->resource['features'],
            'plans' => PlanResource::collection($this->resource['plans']),
        ];
    }
}
