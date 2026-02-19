<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\SystemPrompt;
use App\Services\PromptService;

class SystemPromptController extends Controller
{
    protected $promptService;

    public function __construct(PromptService $promptService)
    {
        $this->promptService = $promptService;
    }

    public function index()
    {
        $prompts = SystemPrompt::orderBy('title')->get();
        return view('admin.prompts.index', compact('prompts'));
    }

    public function edit(SystemPrompt $systemPrompt)
    {
        return view('admin.prompts.edit', compact('systemPrompt'));
    }

    public function update(Request $request, SystemPrompt $systemPrompt)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $systemPrompt->update($request->only(['title', 'content', 'description']));

        // Clear cache
        $this->promptService->clearCache($systemPrompt->slug);

        return redirect()->route('admin.prompts.index')->with('success', 'Prompt atualizado com sucesso!');
    }

    public function clearCache(SystemPrompt $systemPrompt)
    {
        $this->promptService->clearCache($systemPrompt->slug);
        return back()->with('success', "Cache do prompt '{$systemPrompt->slug}' limpo com sucesso!");
    }
}
