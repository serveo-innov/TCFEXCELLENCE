<?php

namespace App\Jobs;

use App\Models\Correction;
use App\Models\Submission;
use App\Services\AiCorrectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAiCorrection implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;

    public function __construct(public Submission $submission)
    {
    }

    public function handle(AiCorrectionService $aiService): void
    {
        try {
            // Correction selon le type
            if ($this->submission->type === 'AUDIO' && $this->submission->audio_url) {
                $result = $aiService->correctOral($this->submission, $this->submission->audio_url);
            } else {
                $result = $aiService->correctWritten($this->submission);
            }

            // Créer la correction en statut "brouillon" — le coach doit valider
            Correction::create([
                'submission_id'  => $this->submission->id,
                'coach_id'       => $this->submission->exercise->competence->id, // placeholder
                'corrected_text' => null, // à remplir par le coach
                'score'          => $result['score'],
                'feedback'       => $result['feedback_global'],
                'is_ai_assisted' => true,
                'ai_raw_result'  => $result,
            ]);

            // Mettre le statut de la soumission en attente de validation coach
            $this->submission->update(['status' => 'PENDING']);

        } catch (\Exception $e) {
            Log::error('AI Correction failed', [
                'submission_id' => $this->submission->id,
                'error'         => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ProcessAiCorrection job failed', [
            'submission_id' => $this->submission->id,
            'error'         => $e->getMessage(),
        ]);
    }
}
