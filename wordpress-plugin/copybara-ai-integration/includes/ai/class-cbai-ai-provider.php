<?php
/**
 * Copybara AI provider for WordPress AI Client (WP 7+).
 *
 * @package Cbai
 * @license GPL-2.0-or-later
 */

defined('ABSPATH') || exit;

use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

class Cbai_Ai_Provider extends AbstractApiProvider {

    protected static function baseUrl(): string {
        return Cbai_Ai_Bootstrap::get_ai_base_url();
    }

    protected static function createModel(
        ModelMetadata $model_metadata,
        ProviderMetadata $provider_metadata
    ): ModelInterface {
        return new Cbai_Ai_Text_Model($model_metadata, $provider_metadata);
    }

    protected static function createProviderMetadata(): ProviderMetadata {
        return new ProviderMetadata(
            'copybara',
            __('copybara.ai', 'copybara-ai-integration'),
            ProviderTypeEnum::cloud(),
            'https://copybara.ai/settings',
            RequestAuthenticationMethod::apiKey()
        );
    }

    protected static function createProviderAvailability(): ProviderAvailabilityInterface {
        return new ListModelsApiBasedProviderAvailability(
            static::modelMetadataDirectory()
        );
    }

    protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
        return new Cbai_Ai_Model_Metadata_Directory();
    }
}
