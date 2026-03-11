<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Semantic Search Engine — Infrastructure Tables
 *
 * Creates all tables required for the Xavier Advanced Search pipeline:
 * - concepts             : Educational knowledge graph nodes
 * - concept_relations    : Graph edges between concepts
 * - question_concepts    : Pivot: question <-> concept
 * - question_vectors     : Indexing metadata & versioning (Qdrant sync control)
 * - search_interaction_logs : Learning loop click tracking
 */
return new class extends Migration {
    public function up(): void
    {
        // ─── Concepts (Knowledge Graph Nodes) ────────────────────────────────
        Schema::create('concepts', function (Blueprint $table) {
            $table->string('id', 100)->primary(); // slug: 'socrates', 'maieutica'
            $table->string('name', 200);
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained('topics')->nullOnDelete();
            $table->text('description')->nullable();
            $table->json('aliases')->nullable();          // ['socrático', 'método socrático']
            $table->boolean('auto_detected')->default(false); // descoberto por clustering?
            $table->timestamp('qdrant_indexed_at')->nullable();
            $table->timestamps();

            $table->index('subject_id');
            $table->index('topic_id');
        });

        // ─── Concept Relations (Knowledge Graph Edges) ────────────────────────
        Schema::create('concept_relations', function (Blueprint $table) {
            $table->id();
            $table->string('concept_id', 100);
            $table->string('related_id', 100);
            // Relation types: 'related', 'parent_of', 'child_of', 'prerequisite', 'similar'
            $table->string('relation_type', 50)->default('related');
            $table->decimal('weight', 5, 4)->default(1.0000);
            $table->timestamps();

            $table->foreign('concept_id')->references('id')->on('concepts')->cascadeOnDelete();
            $table->foreign('related_id')->references('id')->on('concepts')->cascadeOnDelete();
            $table->unique(['concept_id', 'related_id', 'relation_type'], 'concept_relation_unique');
            $table->index('concept_id');
            $table->index('related_id');
        });

        // ─── Question <-> Concept Pivot ───────────────────────────────────────
        Schema::create('question_concepts', function (Blueprint $table) {
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('concept_id', 100);
            // Confidence: 1.0 = manual, 0.X = AI-extracted
            $table->decimal('confidence', 4, 3)->default(1.000);
            $table->timestamps();

            $table->primary(['question_id', 'concept_id']);
            $table->foreign('concept_id')->references('id')->on('concepts')->cascadeOnDelete();
            $table->index('concept_id');
        });

        // ─── Vector Indexing Control (Qdrant sync state) ─────────────────────
        Schema::create('question_vectors', function (Blueprint $table) {
            $table->foreignId('question_id')->primary()->constrained()->cascadeOnDelete();

            // Qdrant point ID (uses question_id as string for simplicity)
            $table->string('qdrant_id', 100)->nullable()->unique();

            // SHA-256 of the structured text used to generate the embedding.
            // If this hash matches on job execution, we skip the API call (cost = $0).
            $table->char('embedding_hash', 64)->nullable();

            // Increments every time the vectors are regenerated.
            // Allows rollback detection and selective re-indexing.
            $table->tinyInteger('index_version')->unsigned()->default(1);

            // Identifies the pipeline that generated this embedding version.
            // Allows future pipeline upgrades without full re-index confusion.
            $table->string('pipeline_version', 30)->default('v3_structured');

            $table->timestamp('indexed_at')->nullable();
        });

        // ─── Search Interaction Logs (Learning Loop) ─────────────────────────
        Schema::create('search_interaction_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_search_id')
                ->nullable()
                ->constrained('ai_search_requests')
                ->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('question_id')->nullable()->constrained()->nullOnDelete();

            // Position in which the result appeared in the ranked list
            $table->tinyInteger('rank_position')->unsigned()->nullable();
            $table->boolean('was_clicked')->default(false);
            $table->unsignedInteger('time_to_click_ms')->nullable();

            // Search metadata snapshot for offline analysis
            $table->json('expanded_concept_ids')->nullable(); // concepts used in the search
            $table->string('search_path', 20)->nullable();   // 'l1_cache','l2_cache','concept','llm'

            $table->timestamp('created_at')->useCurrent();

            $table->index('ai_search_id');
            $table->index('user_id');
            $table->index(['question_id', 'was_clicked']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_interaction_logs');
        Schema::dropIfExists('question_vectors');
        Schema::dropIfExists('question_concepts');
        Schema::dropIfExists('concept_relations');
        Schema::dropIfExists('concepts');
    }
};
