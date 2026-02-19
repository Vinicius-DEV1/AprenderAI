<?php

namespace App\Services;

use App\Models\SystemPrompt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * PromptService - Orchestrates dynamic prompt retrieval, caching, and variable injection.
 * 
 * DESIGN RATIONALE:
 * Moving prompts to the database allows real-time adjustments without code deployments.
 * Caching is essential to avoid redundant DB queries on every AI interaction.
 */
class PromptService
{
    /**
     * Get a formatted prompt from the database with caching and variable replacement.
     *
     * @param string $slug
     * @param array $variables
     * @param string|null $fallback  A string to return if the prompt is missing from DB.
     * @return string
     */
    public function get(string $slug, array $variables = [], ?string $fallback = null): string
    {
        try {
            // PROMPT CACHING: Stores the SystemPrompt object for 1 hour.
            // In case of high concurrency, this significantly reduces DB load.
            $prompt = Cache::remember("system_prompt_{$slug}", 3600, function () use ($slug) {
                return SystemPrompt::where('slug', $slug)->first();
            });

            if (!$prompt) {
                if ($fallback) {
                    // SAFE FALLBACK: If DB is empty or slug is wrong, uses the hardcoded string provided.
                    return $this->replaceVariables($fallback, $variables);
                }
                Log::warning("System prompt with slug '{$slug}' not found.");
                return "";
            }

            if ($prompt instanceof SystemPrompt) {
                return $this->replaceVariables($prompt->content, $variables);
            }

            Log::error("Prompt found in cache/DB for '{$slug}' but it's not a SystemPrompt object.");
            return "";
        }
        catch (\Exception $e) {
            // ROBUSTNESS: If Redis or DB fails, we still try to return the fallback to keep the service running.
            Log::error("Error retrieving system prompt '{$slug}': " . $e->getMessage());
            return $fallback ? $this->replaceVariables($fallback, $variables) : "";
        }
    }

    /**
     * Replace placeholders in the format {variable_name} with actual values.
     * 
     * HOW IT WORKS:
     * It scans the template for curly braces and performs a direct string replacement.
     * If a variable is missing from the $variables array, the placeholder remains in the string
     * (the AI usually handles this gracefully or ignores it).
     */
    protected function replaceVariables(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            $content = str_replace("{{$key}}", (string)$value, $content);
        }
        return $content;
    }

    /**
     * Clear the cache for a specific prompt or all prompts.
     * 
     * CACHE INVALIDATION:
     * To manually clear via terminal: php artisan cache:forget system_prompt_{slug}
     * Or clear everything: php artisan cache:clear
     *
     * @param string|null $slug
     * @return void
     */
    public function clearCache(?string $slug = null): void
    {
        if ($slug) {
            Cache::forget("system_prompt_{$slug}");
        }
        else {
            Log::info("Global clearing of system prompts should be done via artisan cache:clear.");
        }
    }
}
