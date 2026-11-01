<?php

namespace App\Ai;

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/** Turns a freeform event write-up into EventDetails. Ponytail: one LLM call per description, no batching. */
class EventDetailsExtractor
{
    /**
     * Returns null when the model output could not be parsed or the call failed.
     * $location is context only: it stops `directions` from repeating the venue we already have.
     */
    public function extract(string $description, ?string $location = null): ?EventDetails
    {
        if (blank($description)) {
            return null;
        }

        // Outside the try: a missing key is a config error, not a per-event failure.
        $config = $this->llmConfig();

        try {
            $result = StructuredOutput::fromConfig($config)
                ->with(
                    system: 'You extract hares, hash cash (cost) and start location/directions from '
                        .'hash house harriers event write-ups. Only report what the text states; '
                        .'never guess a venue, a price, or an address.',
                    messages: filled($location)
                        ? "Known venue (already on the event record): {$location}\n\n{$description}"
                        : $description,
                    responseModel: EventDetails::class,
                )
                ->get();
        } catch (Throwable $e) {
            Log::warning('Event detail extraction failed', ['error' => $e->getMessage()]);

            return null;
        }

        return $result instanceof EventDetails ? $result : null;
    }

    private function llmConfig(): LLMConfig
    {
        $config = (array) config('services.llm', []);

        if (blank($config['api_key'] ?? null)) {
            throw new RuntimeException('No LLM API key set — add LLM_API_KEY (and LLM_DRIVER/LLM_MODEL) to .env.');
        }

        // Preset supplies apiUrl/endpoint/default model; .env only has to set the key.
        $preset = LLMConfig::fromPreset($config['driver'] ?? 'openai')->toArray();
        $overrides = array_filter([
            'apiUrl' => $config['api_url'] ?? null,
            'model' => $config['model'] ?? null,
            'maxTokens' => $config['max_tokens'] ?? null,
        ], fn ($value) => filled($value));

        return LLMConfig::fromArray([...$preset, ...$overrides, 'apiKey' => $config['api_key']]);
    }
}
