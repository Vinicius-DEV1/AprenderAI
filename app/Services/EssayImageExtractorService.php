<?php

namespace App\Services;

use App\Services\AI\AIService;
use App\Models\ApiKey;
use Illuminate\Support\Facades\Log;

class EssayImageExtractorService
{
    protected $aiService;

    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Extracts text from an image.
     * 
     * @param string $absoluteImagePath The absolute path to the image in storage/app/public
     * @param string $publicUrl The public URL or path relative to public_path() (e.g. /storage/...)
     * @return string The extracted text
     */
    public function extractText(string $absoluteImagePath, string $publicUrl): string
    {
        if (!$this->aiService->hasActiveKey(ApiKey::CAPABILITY_GENERAL)) {
            Log::warning('OCR failed - No active API key', ['path' => $absoluteImagePath]);
            throw new \Exception('Serviço de extração de texto (OCR) não configurado ou indisponível.');
        }

        try {
            // Using a prompt with the image URL so AIService/ResponseSanitizer can extract it
            $prompt = "Transcreva exatamente o texto manuscrito contido nesta imagem. Não adicione nenhum comentário, markdown, ou introdução, apenas retorne o texto legível da redação da melhor forma que conseguir. Imagem: " . $publicUrl;

            // Prefer Gemini model as it supports vision smoothly in this stack
            $result = $this->aiService->generateJson($prompt, 'gemini-1.5-pro-latest');

            if (isset($result['data']['text'])) {
                return $result['data']['text'];
            }
            if (is_array($result['data']) && !empty($result['data'])) {
                return json_encode($result['data']);
            }
            if (is_string($result['data'])) {
                return $result['data'];
            }

            throw new \Exception("Nenhum texto retornado pela IA.");
        } catch (\Exception $e) {
            Log::warning('OCR failed', [
                'path' => $absoluteImagePath,
                'reason' => $e->getMessage()
            ]);
            throw new \Exception('Falha ao extrair texto da imagem. ' . $e->getMessage());
        }
    }
}
