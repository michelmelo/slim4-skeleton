<?php

namespace App\Repository;

use App\Model\OAuthAccessTokenModel;
use Illuminate\Database\Eloquent\Builder;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;

/**
 * Class OAuthAccessTokenRepository.
 *
 * @author Jerfeson Guerreiro <jerfeson_guerreiro@hotmail.com>
 */
class OAuthAccessTokenRepository extends Repository implements AccessTokenRepositoryInterface
{
    protected $modelClass = OAuthAccessTokenModel::class;

    /**
     * Create a new access token.
     *
     * @param ClientEntityInterface $clientEntity
     * @param ScopeEntityInterface[] $scopes
     * @param mixed $userIdentifier
     *
     * @return OAuthAccessTokenModel
     */
    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, $userIdentifier = null)
    {
        $accessToken = new OAuthAccessTokenModel();
        $accessToken->setClient($clientEntity);
        foreach ($scopes as $scope) {
            $accessToken->addScope($scope);
        }

        $accessToken->setUserIdentifier($userIdentifier->id);

        return $accessToken;
    }

    /**
     * Persists a new access token to permanent storage.
     *
     * @param AccessTokenEntityInterface $accessTokenEntity
     * return void
     */
    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity)
    {
        $token = new OAuthAccessTokenModel();
        $token->access_token = $accessTokenEntity->getIdentifier();
        $token->expiry_date_time = $accessTokenEntity->getExpiryDateTime();
        $token->user_id = $accessTokenEntity->getUserIdentifier();
        $token->oauth_client_id = $accessTokenEntity->getClient()->getIdentifier();
        $token->save();
    }

    /**
     * Check if the access token has been revoked.
     *
     * @param string $tokenId
     *
     * @throws \Exception
     *
     * @return bool Return true if this token has been revoked
     */
    public function isAccessTokenRevoked($tokenId)
    {
        /** @var Builder $query */
        $query = $this->newQuery();
        $query->where('access_token', '=', $tokenId);

        if ($this->doQuery($query, 1, false)->count()) {
            return false;
        }

        return true;
    }

    /**
     * Revoke an access token.
     *
     * @param string $tokenId
     */
    public function revokeAccessToken($tokenId)
    {
        // TODO: Implement revokeAccessToken() method.
    }
}
