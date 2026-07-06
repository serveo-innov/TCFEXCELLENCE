<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$key = config('services.gemini.key');

$models = ['gemini-2.5-flash', 'gemini-2.0-flash-lite', 'gemini-1.5-flash-latest', 'gemini-1.5-pro'];

foreach ($models as $model) {
    $response = Illuminate\Support\Facades\Http::timeout(30)
        ->post("https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key={$key}", [
            'contents' => [['parts' => [['text' => 'Dis bonjour']]]],
            'generationConfig' => ['temperature' => 0.3, 'maxOutputTokens' => 50],
        ]);
    echo "{$model} : " . $response->status() . "\n";
}
