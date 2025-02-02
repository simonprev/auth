<?php

namespace verbb\auth\clients\slack\provider\exception;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Psr\Http\Message\ResponseInterface;

class SlackProviderException extends IdentityProviderException
{
    /**
     * @param  ResponseInterface $response
     * @param string|null $message
     *
     * @return IdentityProviderException
     * @throws \AdamPaterson\OAuth2\Client\Provider\Exception\SlackProviderException
     */
    public static function fromResponse(ResponseInterface $response, string $message = null): IdentityProviderException
    {
        throw new static($message, $response->getStatusCode(), (string) $response->getBody());
    }
}
