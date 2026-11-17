<?php
/**
 * Model metadata directory for Copybara AI provider.
 *
 * Declares text-generation models the SDK can match. Runtime availability
 * is validated via GET /v1/ai/models (ListModelsApiBasedProviderAvailability).
 *
 * @package Cbai
 * @license GPL-2.0-or-later
 */

defined('ABSPATH') || exit;

use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Enums\OptionEnum;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;

class Cbai_Ai_Model_Metadata_Directory implements ModelMetadataDirectoryInterface {

    /** @var ModelMetadata[]|null */
    private $cache = null;

    public function listModelMetadata(): array {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $defs = [
            ['openai/gpt-4o', 'GPT-4o'],
            ['openai/gpt-4o-mini', 'GPT-4o Mini'],
            ['anthropic/claude-sonnet-4', 'Claude Sonnet 4'],
            ['google/gemini-2.5-flash', 'Gemini 2.5 Flash'],
        ];

        $out = [];
        foreach ($defs as $pair) {
            $meta = $this->make_text_model($pair[0], $pair[1]);
            if ($meta) {
                $out[] = $meta;
            }
        }
        $this->cache = $out;
        return $this->cache;
    }

    public function getModelMetadata(string $modelId): ModelMetadata {
        foreach ($this->listModelMetadata() as $meta) {
            if (method_exists($meta, 'getId') && $meta->getId() === $modelId) {
                return $meta;
            }
        }
        throw new \InvalidArgumentException('Unknown model: ' . $modelId);
    }

    /**
     * @return ModelMetadata|null
     */
    private function make_text_model(string $id, string $name) {
        try {
            $caps = [CapabilityEnum::textGeneration()];
        } catch (\Throwable $e) {
            return null;
        }

        $options = [];
        try {
            $options = [
                new SupportedOption(
                    OptionEnum::inputModalities(),
                    [[ModalityEnum::text()]]
                ),
                new SupportedOption(
                    OptionEnum::outputModalities(),
                    [[ModalityEnum::text()]]
                ),
                new SupportedOption(OptionEnum::systemInstruction()),
                new SupportedOption(OptionEnum::temperature()),
                new SupportedOption(OptionEnum::maxTokens()),
            ];
        } catch (\Throwable $e) {
            $options = [];
        }

        try {
            return new ModelMetadata($id, $name, $caps, $options);
        } catch (\Throwable $e) {
            try {
                return new ModelMetadata($id, $name, $caps);
            } catch (\Throwable $e2) {
                return null;
            }
        }
    }
}
