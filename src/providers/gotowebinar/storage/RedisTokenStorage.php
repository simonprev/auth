<?php
namespace verbb\auth\providers\gotowebinar\storage;

use function GuzzleHttp\json_encode;

class RedisTokenStorage implements TokenStorageInterface {
    
    /**
     * @var \Redis|\Predis\Client 
     */
    private $redis;
    
    /**
     * @param \Redis|\Predis\Client $redis
     */
    public function __construct($redis) {
        $this->redis = $redis;
    }
 
    /**
     * Recupera un accessToken da redis.
     * Viene usato l'accessToken corrispondente all'organizerKey settato.
     *
     * @see \DalPraS\OAuth2\Client\Storage\TokenStorageInterface::fetchToken()
     * @param string $organizerKey
     * @return \League\OAuth2\Client\Token\AccessToken|NULL
     */
    public function fetchToken(string $organizerKey) {
        $id = sprintf(self::STORAGE_DOMAIN, $organizerKey);
        // controllo che il token sia stato salvato in redis
        if ($this->redis->exists($id)) {
            $data = \json_decode($this->redis->get($id), true);
            if ( !empty($data) ) {
                return new \League\OAuth2\Client\Token\AccessToken($data);
            }
        }
        return null;
    }
    
    /**
     * Save the accessToken with the specified id.
     * Set an expiration of 365 days for the id saved in redis (cleanup in redis).
     *
     * @param \League\OAuth2\Client\Token\AccessTokenInterface $accessToken
     * @return \DalPraS\OAuth2\Client\Storage\RedisTokenStorage
     */
    public function saveToken(\League\OAuth2\Client\Token\AccessToken $accessToken) {
        $organizerKey = (new \DalPraS\OAuth2\Client\Decorators\AccessTokenDecorator($accessToken))->getOrganizerKey();
        $id = sprintf(self::STORAGE_DOMAIN, $organizerKey);
        
        // Store token for future usage
        $this->redis->set($id, \json_encode($accessToken->jsonSerialize()));
        $this->redis->expireAt($id, time() + (86400 * 365));
        return $this;
    }
}

