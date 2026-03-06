<?php
$count = \App\Models\SystemPrompt::count();
$exists = \App\Models\SystemPrompt::where('slug', 'essay_topic_generator')->exists() ? 'YES' : 'NO';
$p = \App\Models\SystemPrompt::where('slug', 'essay_topic_generator')->first();
$len = $p ? strlen((string) $p->content) : 0;

echo "count={$count}\n";
echo "essay_topic_generator_exists={$exists}\n";
echo "len={$len}\n";

// Also check cache key
$cacheKey = 'system_prompt_essay_topic_generator';
$cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
echo 'cache_hit=' . ($cached ? 'YES' : 'NO') . "\n";
if ($cached) {
    echo 'cached_type=' . gettype($cached) . "\n";
    if (is_object($cached)) {
        echo 'cached_slug=' . ($cached->slug ?? 'N/A') . "\n";
        echo 'cached_len=' . strlen((string) ($cached->content ?? '')) . "\n";
    }
}

// List ALL slugs in system_prompts
$allSlugs = \App\Models\SystemPrompt::pluck('slug');
echo "all_slugs=" . implode(',', $allSlugs->toArray()) . "\n";
