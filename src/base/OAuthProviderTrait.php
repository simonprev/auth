<?php
namespace verbb\auth\base;

use verbb\auth\Auth;
use verbb\auth\helpers\Session;
use verbb\auth\models\Token;

use Craft;
use craft\helpers\App;
use craft\helpers\Json;

use Exception;

use League\OAuth1\Client\Credentials\TokenCredentials as OAuth1Token;
use League\OAuth1\Client\Server\Server as OAuth1Provider;
use League\OAuth2\Client\Provider\AbstractProvider as OAuth2Provider;
use League\OAuth2\Client\Token\AccessToken as OAuth2Token;

trait OAuthProviderTrait
{
    // Properties
    // =========================================================================

    public array $config = [];
    public ?string $clientId = null;
    public ?string $clientSecret = null;
    public ?string $redirectUri = null;

    protected OAuth1Provider|OAuth2Provider|null $_oauthProvider = null;


    // Public Methods
    // =========================================================================

    public function getClientId(): ?string
    {
        return App::parseEnv($this->clientId);
    }

    public function getClientSecret(): ?string
    {
        return App::parseEnv($this->clientSecret);
    }

    public function getRedirectUri(): ?string
    {
        return $this->redirectUri;
    }

    public function getOAuthVersion(): int
    {
        // Determine the OAuth version based on the string class, because we check against
        // the OAuth version when passing in the init config, which will throw us in a loop.
        $className = static::getOAuthProviderClass();
        $isOAuth1 = is_subclass_of($className, OAuth1Provider::class) || is_a($className, OAuth1Provider::class, true);

        return ($isOAuth1) ? 1 : 2;
    }

    public function getIsOAuth1(): bool
    {
        return $this->getOAuthVersion() === 1;
    }

    public function getIsOAuth2(): bool
    {
        return $this->getOAuthVersion() === 2;
    }

    public function getOAuthProviderConfig(): array
    {
        if ($this->getIsOAuth1()) {
            return array_merge([
                'identifier' => $this->getClientId(),
                'secret' => $this->getClientSecret(),
                'callback_uri' => $this->getRedirectUri(),
            ], $this->config);
        }

        return array_merge([
            'clientId' => $this->getClientId(),
            'clientSecret' => $this->getClientSecret(),
            'redirectUri' => $this->getRedirectUri(),
        ], $this->config);
    }

    public function getOAuthProvider(): OAuth1Provider|OAuth2Provider
    {
        if ($this->_oauthProvider !== null) {
            return $this->_oauthProvider;
        }

        $className = static::getOAuthProviderClass();

        return $this->_oauthProvider = new $className($this->getOAuthProviderConfig());
    }

    public function getAuthorizationUrlOptions(): array
    {
        return [];
    }

    public function getAuthorizationUrl(): ?string
    {
        $authUrl = null;
        $request = Craft::$app->getRequest();
        $oauthProvider = $this->getOAuthProvider();

        // Allow passing in a `redirect` param to redirect to upon callback
        $redirect = Craft::$app->getSecurity()->validateData($request->getParam('redirect'));
        $redirect = $redirect ?: $request->getReferrer();
        Session::set('redirect', $redirect);

        // OAuth v1
        if ($this->getIsOAuth1()) {
            $temporaryCredentials = $oauthProvider->getTemporaryCredentials();

            Session::set('temporaryCredentials', $temporaryCredentials);

            $authUrl = $oauthProvider->getAuthorizationUrl($temporaryCredentials);
        }

        // OAuth v2
        if ($this->getIsOAuth2()) {
            // Must do this before calling `getState()`
            $authUrl = $oauthProvider->getAuthorizationUrl($this->getAuthorizationUrlOptions());

            Session::set('state', $oauthProvider->getState());
            Session::set('origin', $request->getReferrer());
        }

        return $authUrl;
    }

    public function getAccessTokenOptions(array $options = []): array
    {
        return $options;
    }

    public function getAccessToken(): OAuth1Token|OAuth2Token|null
    {
        $accessToken = null;
        $request = Craft::$app->getRequest();
        $oauthProvider = $this->getOAuthProvider();

        // OAuth v1
        if ($this->getIsOAuth1()) {
            $oauthToken = $request->getParam('oauth_token');
            $oauthVerifier = $request->getParam('oauth_verifier');

            // Retrieve the temporary credentials we saved before.
            $temporaryCredentials = Session::get('temporaryCredentials');

            // Obtain token credentials from the server.
            $accessToken = $oauthProvider->getTokenCredentials($temporaryCredentials, $oauthToken, $oauthVerifier);
        }

        // OAuth v2
        if ($this->getIsOAuth2()) {
            $code = $request->getParam('code');
            $state = $request->getParam('state');
            $sessionState = Session::get('state');

            // Check for error
            $error = $request->getParam('error_code');

            if ($error) {
                Auth::error('An error occurred: {error}.', Json::encode($error));

                throw new Exception('An error occurred.');
            }

            // Run CSRF checks
            if ($state !== $sessionState) {
                Auth::error('Invalid callback state. State is mismatched: {state} - {sessionState}.', ['state' => $state, 'sessionState' => $sessionState]);

                throw new Exception('Invalid callback state. State is mismatched.');
            }

            $accessToken = $oauthProvider->getAccessToken('authorization_code', $this->getAccessTokenOptions([
                'code' => $code,
            ]));

            // Some providers (Facebook, Instagram) have long-lived tokens, so use those
            if (method_exists($oauthProvider, 'getLongLivedAccessToken')) {
                $accessToken = $oauthProvider->getLongLivedAccessToken((string)$accessToken);
            }
        }

        return $accessToken;
    }

    public function getToken(): ?Token
    {
        return null; 
    }

    public function request(string $method = 'GET', string $uri = '', array $options = []): mixed
    {
        $oauthProvider = $this->getOAuthProvider();
        $token = $this->getToken();

        return $oauthProvider->getApiRequest($method, $uri, $token, $options);
    }
}
