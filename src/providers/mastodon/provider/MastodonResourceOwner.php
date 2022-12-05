<?php
/**
 * Created by PhpStorm.
 * User: lrf141
 * Date: 18/08/30
 * Time: 2:26
 */

namespace verbb\auth\providers\mastodon\provider;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Tool\ArrayAccessorTrait;

class MastodonResourceOwner implements ResourceOwnerInterface
{

    use ArrayAccessorTrait;

    /**
     * @var array
     */
    protected $response;

    public function __construct(array $response = array())
    {
        $this->response = $response;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->getValueByKey($this->response, 'id');
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->getValueByKey($this->response, 'username');
    }


    /**
     * @return array
     */
    public function toArray()
    {
        return $this->response;
    }
}
