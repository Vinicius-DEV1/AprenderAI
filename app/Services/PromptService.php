<?php

namespace App\Services;

use App\Models\SystemPrompt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PromptService
{
    /**
     * Get a formatted prompt from the database with caching and variable replacement.
     *
     * @param string $slug
     * @param array $variables
     * @param string|null $fallback
     * @return string
     */
    public function get(string $slug, array $variables = [], ?string $fallback = null): string
    {
        try {
            $prompt = Cache::remember("system_prompt_{$slug}", 3600, function () use ($slug) {
                return SystemPrompt::where('slug', $slug)->first();
            });

            if (!$prompt) {
                if ($fallback) {
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
            Log::error("Error retrieving system prompt '{$slug}': " . $e->getMessage());
            return $fallback ? $this->replaceVariables($fallback, $variables) : "";
        }
    }

    /**
     * Replace placeholders in the format {variable_name} with actual values.
     *
     * @param string $content
     * @param array $variables
     * @return string
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
     * @param string|null $slug
     * @return void
     */
    public function clearCache(?string $slug = null): void
    {
        if ($slug) {
            Cache::forget("system_prompt_{$slug}");
        }
        else {
            // This is a bit aggressive but works if we don't have a list of all slugs.
            // Better to use tags if cache driver supports it.
            Log::info("Clearing all system prompt caches could be expensive depending on driver.");
        }
    }
}
