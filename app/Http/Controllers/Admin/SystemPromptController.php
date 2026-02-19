<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\SystemPrompt;
use App\Services\PromptService;

/**
 * SystemPromptController - Admin interface for managing dynamic AI prompts.
 * 
 * Allows editing prompt content in real-time and clearing cache to apply changes.
 */
class SystemPromptController extends Controller
{
    protected $promptService;

    public function __construct(PromptService $promptService)
    {
        $this->promptService = $promptService;
    }

    /**
     * List all available system prompts.
     */
    public function index()
    {
        $prompts = SystemPrompt::orderBy('title')->get();
        return view('admin.prompts.index', compact('prompts'));
    }

    /**
     * Show the edit form for a specific prompt.
     */
    public function edit(SystemPrompt $systemPrompt)
    {
        return view('admin.prompts.edit', compact('systemPrompt'));
    }

    /**
     * Update prompt content and invalidate its cache.
     */
    public function update(Request $request, SystemPrompt $systemPrompt)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $systemPrompt->update($request->only(['title', 'content', 'description']));

        // Clear cache so the AI uses the new content immediately.
        $this->promptService->clearCache($systemPrompt->slug);

        return redirect()->route('admin.prompts.index')->with('success', 'Prompt atualizado com sucesso!');
    }

    /**
     * Manually clear the cache for a prompt without updating it.
     */
    public function clearCache(SystemPrompt $systemPrompt)
    {
        $this->promptService->clearCache($systemPrompt->slug);
        return back()->with('success', "Cache do prompt '{$systemPrompt->slug}' limpo com sucesso!");
    }
}
