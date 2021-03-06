<?php

namespace App\Model;

use League\OAuth2\Server\Entities\ClientEntityInterface;

/**
 * Class ClientModel.
 *
 * @author Jerfeson Guerreiro <jerfeson_guerreiro@hotmail.com>
 */
class ClientModel extends Model implements ClientEntityInterface
{
    const STATUS_ACTIVE = 1;
    const STATUS_INACTIVE = 0;

    protected $table = 'client';
    protected $fillable = ['name', 'status'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function client()
    {
        return $this->hasOne(OAuthClientModel::class, 'id', 'oauth_client_id');
    }

    public function getName()
    {
        // TODO: Implement getName() method.
    }

    public function getRedirectUri()
    {
        // TODO: Implement getRedirectUri() method.
    }

    public function isConfidential()
    {
        // TODO: Implement isConfidential() method.
    }
}
