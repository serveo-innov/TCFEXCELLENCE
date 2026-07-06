<?php

namespace App\Jobs;

use App\Models\Correction;
use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessAiCorrection implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;

    public function __construct(protected Submission $submission)
    {
    }

    public function handle(): void
    {
        $geminiKey = config('services.gemini.key');
        $groqKey   = config('services.groq.key');

        if (empty($geminiKey)) {
            Log::info('ProcessAiCorrection: cle Gemini absente', [
                'submission_id' => $this->submission->id,
            ]);
            Correction::updateOrCreate(
                ['submission_id' => $this->submission->id],
                [
                    'is_ai_assisted' => false,
                    'score'          => null,
                    'feedback'       => null,
                    'corrected_text' => null,
                    'ai_raw_result'  => ['status' => 'PENDING_AI', 'message' => 'Cle Gemini absente.'],
                ]
            );
            return;
        }

        try {
            $submission = Submission::with('exercise.competence')->findOrFail($this->submission->id);

            $competenceCode = $submission->exercise->competence->code;
            $level          = $submission->exercise->level ?? 'B1';
            $type           = $submission->type;

            Log::info('ProcessAiCorrection: debut traitement', [
                'submission_id' => $submission->id,
                'type'          => $type,
                'competence'    => $competenceCode,
                'level'         => $level,
            ]);

            $aiResult = match ($type) {
                'TEXT'  => $this->correctText($geminiKey, $competenceCode, $level, $submission->content_text),
                'AUDIO' => $this->correctAudio($geminiKey, $groqKey, $competenceCode, $level, $submission->audio_url),
                default => null,
            };

            Log::info('ProcessAiCorrection: resultat IA', [
                'submission_id' => $submission->id,
                'has_result'    => !empty($aiResult),
                'score'         => $aiResult['score'] ?? 'NULL',
            ]);

            if (!empty($aiResult)) {
                Correction::updateOrCreate(
                    ['submission_id' => $submission->id],
                    [
                        'is_ai_assisted' => true,
                        'score'          => $aiResult['score'] ?? null,
                        'feedback'       => $aiResult['feedback_global'] ?? null,
                        'corrected_text' => $aiResult['transcript'] ?? $submission->content_text,
                        'ai_raw_result'  => $aiResult,
                    ]
                );

                Log::info('ProcessAiCorrection: correction sauvegardee', [
                    'submission_id' => $submission->id,
                    'score'         => $aiResult['score'] ?? null,
                ]);
            } else {
                Log::warning('ProcessAiCorrection: aiResult vide', [
                    'submission_id' => $submission->id,
                    'type'          => $type,
                ]);
            }

        } catch (\Throwable $e) {
            Log::error('ProcessAiCorrection: erreur', [
                'submission_id' => $this->submission->id,
                'error'         => $e->getMessage(),
            ]);

            Correction::updateOrCreate(
                ['submission_id' => $this->submission->id],
                [
                    'is_ai_assisted' => false,
                    'score'          => null,
                    'feedback'       => null,
                    'corrected_text' => null,
                    'ai_raw_result'  => ['status' => 'ERROR', 'message' => $e->getMessage()],
                ]
            );

            throw $e;
        }
    }

    private function correctText(string $apiKey, string $competence, string $level, string $contentText): array
    {
        mb_internal_encoding('UTF-8');

        $model  = config('services.gemini.model', 'gemini-2.5-flash');
        $prompt = $this->buildPrompt($competence, $level);

        $response = Http::timeout(90)
            ->withHeaders(['Content-Type' => 'application/json; charset=utf-8'])
            ->post("https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key={$apiKey}", [
                'contents' => [
                    ['parts' => [['text' => $prompt . "\n\nTexte a corriger :\n" . $contentText]]],
                ],
                'generationConfig' => [
                    'temperature'     => 0.3,
                    'maxOutputTokens' => 2048,
                ],
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Gemini API error ' . $response->status() . ': ' . $response->body());
        }

        $body    = $response->body();
        $decoded = json_decode($body, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
        $text    = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $text    = mb_convert_encoding($text, 'UTF-8', 'UTF-8');

        Log::info('ProcessAiCorrection: reponse Gemini', [
            'raw_length' => strlen($text),
        ]);

        return $this->parseJson($text);
    }

    private function correctAudio(string $geminiKey, ?string $groqKey, string $competence, string $level, ?string $audioUrl): array
    {
        if (empty($groqKey)) {
            throw new \RuntimeException('Cle Groq absente.');
        }
        if (empty($audioUrl)) {
            throw new \RuntimeException('URL audio manquante.');
        }

        $audioContent = file_get_contents($audioUrl);
        if (!$audioContent) {
            throw new \RuntimeException('Impossible de telecharger le fichier audio.');
        }

        $whisperModel = config('services.groq.model', 'whisper-large-v3');
        $groqResponse = Http::withToken($groqKey)->timeout(60)
            ->attach('file', $audioContent, 'audio.webm', ['Content-Type' => 'audio/webm'])
            ->post('https://api.groq.com/openai/v1/audio/transcriptions', [
                'model'    => $whisperModel,
                'language' => 'fr',
            ]);

        if (!$groqResponse->successful()) {
            throw new \RuntimeException('Groq Whisper error: ' . $groqResponse->body());
        }

        $transcript = $groqResponse->json('text') ?? '';
        $model      = config('services.gemini.model', 'gemini-2.5-flash');
        $prompt     = $this->buildPrompt($competence, $level, isOral: true);

        $geminiResponse = Http::timeout(90)
            ->withHeaders(['Content-Type' => 'application/json; charset=utf-8'])
            ->post("https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key={$geminiKey}", [
                'contents' => [
                    ['parts' => [['text' => $prompt . "\n\nTranscription :\n" . $transcript]]],
                ],
                'generationConfig' => [
                    'temperature'     => 0.3,
                    'maxOutputTokens' => 2048,
                ],
            ]);

        if (!$geminiResponse->successful()) {
            throw new \RuntimeException('Gemini API error: ' . $geminiResponse->body());
        }

        $body    = $geminiResponse->body();
        $decoded = json_decode($body, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
        $text    = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $text    = mb_convert_encoding($text, 'UTF-8', 'UTF-8');

        $result               = $this->parseJson($text);
        $result['transcript'] = $transcript;

        return $result;
    }

    private function parseJson(string $text): array
    {
        $text = preg_replace('/^```json\s*/i', '', trim($text));
        $text = preg_replace('/\s*```$/i', '', $text);
        $text = trim($text);

        // Tentative 1 : direct
        $parsed = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE && !empty($parsed)) {
            return $parsed;
        }

        // Tentative 2 : conversion encodage
        $converted = mb_convert_encoding(
            $text,
            'UTF-8',
            mb_detect_encoding($text, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true) ?: 'UTF-8'
        );
        $parsed = json_decode($converted, true);
        if (json_last_error() === JSON_ERROR_NONE && !empty($parsed)) {
            return $parsed;
        }

        // Tentative 3 : supprimer caracteres de controle
        $clean  = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $converted);
        $parsed = json_decode($clean, true);
        if (json_last_error() === JSON_ERROR_NONE && !empty($parsed)) {
            return $parsed;
        }

        // Tentative 4 : JSON_INVALID_UTF8_SUBSTITUTE
        $parsed = json_decode($clean, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
        if (json_last_error() === JSON_ERROR_NONE && !empty($parsed)) {
            return $parsed;
        }

        // Tentative 5 : extraire JSON avec regex
        if (preg_match('/\{[\s\S]*\}/u', $clean, $matches)) {
            $parsed = json_decode($matches[0], true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
            if (json_last_error() === JSON_ERROR_NONE && !empty($parsed)) {
                return $parsed;
            }
        }

        Log::error('ProcessAiCorrection: JSON invalide', [
            'error'    => json_last_error_msg(),
            'hex'      => bin2hex(substr($text, 0, 80)),
            'full_len' => strlen($text),
        ]);

        return [];
    }

    private function buildPrompt(string $competence, string $level, bool $isOral = false): string
    {
        $labels = [
            'EE' => 'Expression Ecrite',
            'EO' => 'Expression Orale',
            'CE' => 'Comprehension Ecrite',
            'CO' => 'Comprehension Orale',
        ];
        $label = $labels[$competence] ?? $competence;

        return <<<PROMPT
Correcteur TCF Canada niveau {$level} en {$label}.
Reponds en JSON uniquement, sans backticks, sans texte avant ou apres.
Format exact:
{"score":65,"niveau_estime":"B1","feedback_global":"feedback max 80 mots","errors":[{"type":"grammaire","original":"mot errone","correction":"mot corrige","explication":"explication courte"}],"recommendations":["conseil1","conseil2","conseil3"]}
Regles strictes: maximum 3 erreurs, feedback court (max 80 mots), 3 recommendations courtes, tout en francais.
PROMPT;
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ProcessAiCorrection: job echoue definitivement', [
            'submission_id' => $this->submission->id,
            'error'         => $e->getMessage(),
        ]);

        Correction::updateOrCreate(
            ['submission_id' => $this->submission->id],
            [
                'is_ai_assisted' => false,
                'ai_raw_result'  => ['status' => 'FAILED', 'message' => 'Correction IA echouee apres 3 tentatives.'],
            ]
        );
    }
}