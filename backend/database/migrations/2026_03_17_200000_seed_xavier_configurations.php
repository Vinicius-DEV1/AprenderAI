<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Configuration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Configuration::set('xavier_vector_search_enabled', '1');
        Configuration::set('xavier_concept_detection_threshold', '0.45');
        Configuration::set('xavier_search_threshold', '0.40');
        Configuration::set('xavier_qdrant_candidate_limit', '50');
        Configuration::set('xavier_final_result_limit', '20');
        Configuration::set('xavier_rerank_weights', json_encode([
            'vector'     => 0.60,
            'popularity' => 0.15,
            'quality'    => 0.15,
            'recency'    => 0.10,
        ]));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Configuration::whereIn('key', [
            'xavier_vector_search_enabled',
            'xavier_concept_detection_threshold',
            'xavier_qdrant_candidate_limit',
            'xavier_final_result_limit'
        ])->delete();
    }
};
