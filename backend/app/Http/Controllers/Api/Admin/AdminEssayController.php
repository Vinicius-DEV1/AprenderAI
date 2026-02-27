<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Essay;
use App\Http\Resources\EssayResource;
use Illuminate\Http\Request;
use App\Jobs\EvaluateEssayJob;

class AdminEssayController extends Controller
{
    /**
     * List essays for administration.
     */
    public function index(Request $request)
    {
        $query = Essay::with('user');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%')
                ->orWhere('content', 'like', '%' . $request->search . '%');
        }

        $essays = $query->latest()->paginate($request->get('per_page', 20));

        return EssayResource::collection($essays);
    }

    /**
     * Retry essay evaluation.
     */
    public function retry(Essay $essay)
    {
        // Reset status to evaluating
        $essay->update([
            'status' => 'evaluating',
            'ocr_status' => $essay->input_type === 'image' ? 'processing' : 'completed',
            'ocr_error' => null
        ]);

        // Dispatch the evaluation job
        EvaluateEssayJob::dispatch($essay);

        return response()->json([
            'success' => true,
            'message' => 'Reavaliação de redação iniciada!',
            'essay' => new EssayResource($essay)
        ]);
    }
}
