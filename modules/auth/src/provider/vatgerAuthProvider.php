<?php

namespace Vatger\Auth\Provider;

use BookStack\Access\LoginService;
use BookStack\Entities\Tools\SlugGenerator;
use BookStack\Exceptions\LoginAttemptInvalidUserException;
use BookStack\Exceptions\StoppedAuthenticationException;
use BookStack\Uploads\UserAvatars;
use Exception;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JetBrains\PhpStorm\NoReturn;
use Vatger\Auth\Models\User;
use Vatger\Auth\Types\AccessTokenResponse;
use Vatger\Auth\Types\OAuthState;
use Vatger\Auth\Types\VatgerUserResponse;
use BookStack\Users\Models\User as BaseUser;

class VatgerAuthProvider implements IAuthProvider
{
    private const SESSION_KEY = 'vatger_oauth_state';

    private GuzzleClient $httpClient;
    private OAuthState $state;
    private SlugGenerator $slugGenerator;
    private UserAvatars $userAvatars;
    private LoginService $loginService;

    /**
     * @throws BindingResolutionException
     */
    public function __construct()
    {
        $this->state = OAuthState::new();

        $this->httpClient = new GuzzleClient([
            'base_uri' => $this->state->baseUri,
            'timeout' => 30,
        ]);

        $this->slugGenerator = new SlugGenerator();
        $this->userAvatars = app()->make(UserAvatars::class);
        $this->loginService = app()->make(LoginService::class);
    }

    #[NoReturn]
    public function login(): RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->back()->with('error', 'Already logged in!');
        }

        $state = Str::random(64);

        session()->put(self::SESSION_KEY, $state);

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $this->state->clientId,
            'redirect_uri' => $this->state->redirectUri,
            'scope' => implode(' ', $this->state->scopes),
            'state' => $state,
        ]);

        $url = $this->state->baseUri . '/oauth/authorize?' . $query;

        return redirect()->away($url);
    }

    #[NoReturn]
    public function callback(Request $request): RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->back()->with('error', 'Already logged in!');
        }

        $state = $request->query('state');

        if (strcmp($state, session()->get(self::SESSION_KEY)) != 0) {
            return redirect('/')->with('error', 'Auth state mismatch.');
        }

        $code = $request->query('code');

        try {
            $tokenResponse = $this->getAccessToken($code);
            $user = $this->getUserDetails($tokenResponse->accessToken);
            $loginUser = $this->upsertUser($user);

            $this->loginService->login($loginUser, 'oidc', true);
        } catch (Exception | GuzzleException $e) {
            return redirect('/')->with('error', $e->getMessage());
        }

        return redirect()->intended()->with('success', "Welcome back, {$user->name_first}");
    }

    /**
     * @param VatgerUserResponse $VatgerUserResponse
     * @return BaseUser
     */
    private function upsertUser(VatgerUserResponse $vatgerUserResponse): BaseUser
    {
        $user = User::query()
            ->where('id', $vatgerUserResponse->cid)
            ->first();

        if (!$user) {
            $user = new User();
            $user->id = $vatgerUserResponse->cid;
            $user->password = Str::random(128);
            $user->external_auth_id = $vatgerUserResponse->cid;
        }

        $user->name = $vatgerUserResponse->name_first . ' ' . $vatgerUserResponse->name_last;
        $user->email = $vatgerUserResponse->email;

        $this->slugGenerator->regenerateForUser($user);
        $user->save();

        if ($user->wasRecentlyCreated) {
            try {
                $this->userAvatars->fetchAndAssignToUser($user);
            } catch (Exception $e) {
                Log::error('Failed to save user avatar for user ' . $user->id);
            }

            $user->attachDefaultRole();
        }

        return $user;
    }

    /**
     * @throws Exception|GuzzleException
     */
    private function getAccessToken(string|null $code): AccessTokenResponse|null
    {
        if (is_null($code)) {
            throw new Exception("Missing code from vatsim response");
        }

        $response = $this->httpClient->post($this->state->tokenEndpoint,
            [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Accept' => 'application/json',
                ],
                'form_params' => [
                    'grant_type' => 'authorization_code',
                    'client_id' => $this->state->clientId,
                    'client_secret' => $this->state->clientSecret,
                    'redirect_uri' => $this->state->redirectUri,
                    'code' => $code,
                ]
            ]
        );

        $data = json_decode($response->getBody(), true);

        return new AccessTokenResponse($data);
    }

    /**
     * @throws GuzzleException
     */
    private function getUserDetails(string $accessToken): VatgerUserResponse
    {
        $response = $this->httpClient->get($this->state->userInfoEndpoint,
            [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $accessToken,
                ]
            ]
        );

        $data = json_decode($response->getBody(), true);

        return new VatgerUserResponse($data);
    }
}
