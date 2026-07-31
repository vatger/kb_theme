<?php

namespace Vatger\Auth\Types;

class AccessTokenResponse
{
    public string $tokenType;
    public int $expiresIn;
    public string $accessToken;
    public string $refreshToken;

    /** @var string[] $scopes  */
    public array $scopes;

    public function __construct(array $body)
    {
        $this->tokenType = $body["token_type"];
        $this->expiresIn = $body["expires_in"];
        $this->accessToken = $body["access_token"];
        $this->refreshToken = $body["refresh_token"];
        $this->scopes = isset($body["scopes"]) ? $body["scopes"] : [];
    }
}
