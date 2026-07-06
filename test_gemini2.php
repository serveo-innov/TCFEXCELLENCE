<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$key   = config('services.gemini.key');
$model = config('services.gemini.model', 'gemini-2.5-flash');

$prompt = 'Tu es un correcteur TCF Canada. Reponds UNIQUEMENT en JSON valide sans backticks ni markdown :
{
  "score": 65,
  "niveau_estime": "B1",
  "feedback_global": "Bon effort, quelques erreurs a corriger.",
  "errors": [{"type": "grammaire", "original": "Je me permet", "correction": "Je me permets", "explication": "Accord du verbe"}],
  "recommendations": ["Travailler la conjugaison", "Enrichir le vocabulaire", "Soigner la ponctuation"]
}

Texte a corriger :
Monsieur, Je me permet de vous ecrire pour demander des informations.';

$response = Illuminate\Support\Facades\Http::timeout(60)
    ->post("https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key={$key}", [
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['temperature' => 0.3, 'maxOutputTokens' => 1500],
    ]);

echo "Status : " . $response->status() . "\n\n";
$raw = $response->json('candidates.0.content.parts.0.text') ?? 'NULL';
echo "Raw text : " . $raw . "\n\n";

$clean = preg_replace('/^```json\s*/i', '', trim($raw));
$clean = preg_replace('/\s*```$/', '', $clean);
echo "Clean : " . $clean . "\n\n";

$parsed = json_decode($clean, true);
echo "Parsed score : " . ($parsed['score'] ?? 'NULL') . "\n";
echo "JSON error : " . json_last_error_msg() . "\n";
