<?php
namespace verbb\auth\clients\auth0\provider;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;
use verbb\auth\clients\auth0\provider\exception\AccountNotProvidedException;
use verbb\auth\clients\auth0\provider\exception\Auth0IdentityProviderException;
use verbb\auth\clients\auth0\provider\exception\InvalidRegionException;

class Auth0 extends AbstractProvider
{
    use BearerAuthorizationTrait;

    const REGION_US = 'us';
    const REGION_EU = 'eu';
    const REGION_AU = 'au';
    const REGION_JP = 'jp';

    protected $availableRegions = [self::REGION_US, self::REGION_EU, self::REGION_AU, self::REGION_JP];

    protected $region = self::REGION_US;

    protected $account;

    protected $customDomain;

    protected function domain()
    {
        if ($this->customDomain !== null) {
            return $this->customDomain;
        }

        if (empty($this->account)) {
            throw new AccountNotProvidedException();
        }
        if (!in_array($this->region, $this->availableRegions)) {
            throw new InvalidRegionException();
        }

        $domain = 'auth0.com';

        if ($this->region !== self::REGION_US) {
            $domain = $this->region . '.' . $domain;
        }

        return $this->account . '.' . $domain;
    }

    protected function baseUrl()
    {
        return 'https://' . $this->domain();
    }

    public function getBaseAuthorizationUrl()
    {
        return $this->baseUrl() . '/authorize';
    }

    public function getBaseAccessTokenUrl(array $params = [])
    {
        return $this->baseUrl() . '/oauth/token';
    }

    public function getResourceOwnerDetailsUrl(AccessToken $token)
    {
        return $this->baseUrl() . '/userinfo';
    }

    public function getDefaultScopes()
    {
        return ['openid', 'email'];
    }

    protected function checkResponse(ResponseInterface $response, $data)
    {
        if ($response->getStatusCode() >= 400) {
            throw Auth0IdentityProviderException::fromResponse(
                $response,
                $data['error'] ?: $response->getReasonPhrase()
            );
        }
    }

    protected function createResourceOwner(array $response, AccessToken $token)
    {
        return new Auth0ResourceOwner($response);
    }

    protected function getScopeSeparator()
    {
        return ' ';
    }
}
