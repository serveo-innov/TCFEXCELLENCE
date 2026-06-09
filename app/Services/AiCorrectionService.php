<?php

namespace App\Services;

use App\Models\AiUsageLog;
use App\Models\Submission;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiCorrectionService
{
    private string $apiKey;
    private string $baseUrl = 'https://api.openai.com/v1';

    public function __construct()
    {
        $this->apiKey = config('services.openai.key');
    }

    // ── CORRECTION EXPRESSION ÉCRITE (GPT-4o) ────────────────────────────────
    public function correctWritten(Submission $submission): array
    {
        $start = microtime(true);

        $prompt = $this->buildWrittenPrompt(
            $submission->content_text,
            $submission->exercise->competence->code,
            $submission->exercise->level
        );

        $response = Http::withToken($this->apiKey)
            ->timeout(30)
            ->post("{$this->baseUrl}/chat/completions", [
                'model'       => 'gpt-4o',
                'temperature' => 0.3,
                'messages'    => [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user',   'content' => $prompt],
                ],
                'response_format' => ['type' => 'json_object'],
            ]);

        $latency = (int) ((microtime(true) - $start) * 1000);

        if ($response->failed()) {
            Log::error('OpenAI API error', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \Exception('Erreur API OpenAI : ' . $response->status());
        }

        $data   = $response->json();
        $result = json_decode($data['choices'][0]['message']['content'], true);

        // Logger l'utilisation
        AiUsageLog::create([
            'submission_id'     => $submission->id,
            'model'             => 'gpt-4o',
            'prompt_tokens'     => $data['usage']['prompt_tokens'],
            'completion_tokens' => $data['usage']['completion_tokens'],
            'total_tokens'      => $data['usage']['total_tokens'],
            'latency_ms'        => $latency,
            'cost'              => $this->estimateCost('gpt-4o', $data['usage']['total_tokens']),
        ]);

        return $result;
    }

    // ── TRANSCRIPTION EXPRESSION ORALE (Whisper) ─────────────────────────────
    public function transcribeAudio(string $audioPath): string
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(60)
            ->attach('file', file_get_contents($audioPath), basename($audioPath))
            ->post("{$this->baseUrl}/audio/transcriptions", [
                'model'    => 'whisper-1',
                'language' => 'fr',
            ]);

        if ($response->failed()) {
            throw new \Exception('Erreur Whisper API : ' . $response->status());
        }

        return $response->json('text', '');
    }

    // ── CORRECTION EXPRESSION ORALE (Whisper + GPT-4o) ───────────────────────
    public function correctOral(Submission $submission, string $audioPath): array
    {
        // 1. Transcrire l'audio
        $transcript = $this->transcribeAudio($audioPath);

        // 2. Corriger la transcription comme un texte
        $fakeSubmission = clone $submission;
        $fakeSubmission->content_text = $transcript;

        $result = $this->correctWritten($fakeSubmission);
        $result['transcript'] = $transcript;

        return $result;
    }

    // ── SYSTEM PROMPT TCF ─────────────────────────────────────────────────────
    private function systemPrompt(): string
    {
        return <<<PROMPT
Tu es un correcteur expert du TCF Canada (Test de Connaissance du Français).
Tu évalues les productions selon le barème officiel TCF Canada et les descripteurs CECRL.
Tu réponds UNIQUEMENT en JSON valide, sans markdown, sans texte avant ou après.
La structure JSON de ta réponse doit être exactement :
{
  "score": <nombre entre 0 et 100>,
  "niveau_estime": <"A1"|"A2"|"B1"|"B2"|"C1"|"C2">,
  "errors": [
    {
      "type": <"orthographe"|"grammaire"|"syntaxe"|"registre"|"coherence">,
      "original": <texte erroné>,
      "correction": <texte corrigé>,
      "explication": <explication courte>
    }
  ],
  "recommendations": [
    <string>,
    <string>,
    <string>
  ],
  "feedback_global": <string>
}
PROMPT;
    }

    // ── PROMPT PAR COMPÉTENCE ─────────────────────────────────────────────────
    private function buildWrittenPrompt(string $text, string $competence, string $level): string
    {
        $competenceLabel = match($competence) {
            'EE' => 'Expression Écrite',
            'EO' => 'Expression Orale (transcription)',
            default => $competence,
        };

        return "Compétence évaluée : {$competenceLabel}\nNiveau cible : {$level}\n\nProduction de l'apprenant :\n\n{$text}";
    }

    // ── ESTIMATION COÛT ───────────────────────────────────────────────────────
    private function estimateCost(string $model, int $tokens): float
    {
        // Tarifs OpenAI en USD pour 1000 tokens
        $rates = [
            'gpt-4o'    => 0.005,
            'whisper-1' => 0.006,
        ];

        return round(($tokens / 1000) * ($rates[$model] ?? 0), 4);
    }
}
