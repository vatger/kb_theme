<?php

namespace Vatger\Auth\Types;

use Illuminate\Support\Facades\URL;
use RuntimeException;

class OAuthState
{
    public string $baseUri;
    public string $userInfoEndpoint;
    public int $clientId;
    public string $clientSecret;
    public string $redirectUri;
    /** @var string[] */
    public array $scopes;

    public static function new(): OAuthState
    {
        $state = new OAuthState();

        $state->baseUri = rtrim(OAuthState::requiredEnv("OAUTH_BASE_URI"), '/');
        $state->clientId = OAuthState::requiredEnv("OAUTH_CLIENT_ID");
        $state->tokenEndpoint = OAuthState::requiredEnv("OAUTH_TOKEN_URI");
        $state->userInfoEndpoint = OAuthState::requiredEnv("OAUTH_USER_INFO_URI");
        $state->clientSecret = OAuthState::requiredEnv("OAUTH_CLIENT_SECRET");
        $state->redirectUri = URL::to("/vatger/oauth/callback");
        $state->scopes = explode(',', OAuthState::requiredEnv("OAUTH_SCOPES"));

        return $state;
    }

    private static function requiredEnv(string $key): string
    {
        $val = getenv($key);

        if (empty($val)) {
            throw new RuntimeException("Missing environment var: {$key}");
        }

        return trim($val);
    }
}
