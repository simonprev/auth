<?php
namespace verbb\auth\providers;

use verbb\auth\base\ProviderTrait;
use verbb\auth\clients\azure\provider\Azure as AzureProvider;

class Azure extends AzureProvider
{
    // Traits
    // =========================================================================

    use ProviderTrait;


    // Public Methods
    // =========================================================================

    public function getBaseApiUrl(): ?string
    {
        return 'https://graph.windows.net/';
    }
}