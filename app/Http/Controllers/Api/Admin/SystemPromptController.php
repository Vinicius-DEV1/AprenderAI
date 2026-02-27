<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemPrompt;
use App\Services\PromptService;
use Illuminate\Http\Request;

class SystemPromptController extends Controller
{
    protected $promptService;

    public function __construct(PromptService $promptService)
    {
        $this->promptService = $promptService;
    }

    /**
     * List all prompts.
     */
    public function index()
    {
        $prompts = SystemPrompt::orderBy('title')->get();
        return response()->json($prompts);
    }

    /**
     * Show a prompt by ID or Slug.
     */
    public function show($id)
    {
        $prompt = SystemPrompt::where('id', $id)->orWhere('slug', $id)->firstOrFail();
        return response()->json($prompt);
    }

    /**
     * Update a prompt.
     */
    public function update(Request $request, $id)
    {
        $prompt = SystemPrompt::where('id', $id)->orWhere('slug', $id)->firstOrFail();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content' => 'required|string',
        ]);

        $prompt->update($validated);

        // Clear cache after update
        $this->promptService->clearCache($prompt->slug);

        return response()->json([
            'message' => 'Prompt atualizado com sucesso!',
            'prompt' => $prompt
        ]);
    }

    /**
     * Manual cache clearing for a prompt.
     */
    public function clearCache($id)
    {
        $prompt = SystemPrompt::where('id', $id)->orWhere('slug', $id)->firstOrFail();
        $this->promptService->clearCache($prompt->slug);

        return response()->json(['message' => "Cache do prompt {$prompt->slug} limpo!"]);
    }
}
