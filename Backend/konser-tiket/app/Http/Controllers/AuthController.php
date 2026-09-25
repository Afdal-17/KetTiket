<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Organizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Pendaftaran publik tidak lagi memilih role: semua akun baru = customer.
        // Input "role" lama dari klien yang masih terbuka cache diabaikan.
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'customer',
            'status' => true,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->load('organizer'),
            'role' => $user->role,
        ], 201);
    }

    /**
     * Authenticate user and return token.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credentials do not match our records.'],
            ]);
        }

        if (!$user->status) {
            return response()->json([
                'message' => 'Your account has been deactivated.'
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->load('organizer'),
        ]);
    }

    /**
     * Resolve a social identity to a user model.
     */
    protected function resolveSocialIdentity(Request $request): array
    {
        $provider = strtolower(trim((string) $request->input('provider', '')));
        $name = trim((string) ($request->input('name') ?? ''));
        $email = trim((string) ($request->input('email') ?? ''));
        $phone = trim((string) ($request->input('phone') ?? ''));

        if ($provider === 'phone') {
            $phone = $phone ?: $email;
            $normalizedPhone = preg_replace('/[^0-9+]/', '', $phone ?? '');
            $email = $normalizedPhone !== ''
                ? 'phone_' . str_replace(['+', ' '], '', $normalizedPhone) . '@kettiket.local'
                : 'phone-user@kettiket.local';
            $name = $name ?: $normalizedPhone ?: 'Phone User';
            return [$provider, $email, $normalizedPhone, $name];
        }

        $name = $name ?: ucfirst($provider) . ' User';
        $email = $email ?: strtolower($provider . '_' . preg_replace('/[^a-z0-9]+/i', '_', $name)) . '@kettiket.local';

        return [$provider, $email, $phone, $name];
    }

    /**
     * Simulate Social Login (Google, TikTok, Phone)
     */
    public function socialLogin(Request $request)
    {
        return response()->json([
            'message' => 'Simulated social login is disabled. Use the official provider OAuth or phone OTP flow.',
        ], 410);

        /*
        $request->validate([
            'provider' => 'required|string|in:google,tiktok,phone',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|string|email|max:255',
            'phone' => 'nullable|string|max:30',
        ]);

        [$provider, $email, $phone, $name] = $this->resolveSocialIdentity($request);

        if ($provider === 'phone' && empty($phone)) {
            throw ValidationException::withMessages([
                'phone' => ['Phone number is required for phone login.'],
            ]);
        }

        $user = User::where('email', $email)->first();
        if (!$user && !empty($phone)) {
            $user = User::where('phone', $phone)->first();
        }

        if (!$user) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'phone' => $phone ?: null,
                'password' => Hash::make(uniqid('social_', true)),
                'role' => 'customer',
                'status' => true,
            ]);
        } else {
            $user->name = $name;
            if (!empty($phone)) {
                $user->phone = $phone;
            }
            $user->role = 'customer';
            $user->save();
        }

        if (!$user->status) {
            return response()->json([
                'message' => 'Your account has been deactivated.'
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => ucfirst($provider) . ' Login successful',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->load('organizer'),
        ]);
        */
    }

    public function googleRedirect()
    {
        if (!config('services.google.client_id') || !config('services.google.client_secret')) {
            return redirect('/login?social_error=Google%20OAuth%20belum%20dikonfigurasi');
        }

        return Socialite::driver('google')->redirect();
    }

    public function googleCallback()
    {
        try {
            $providerUser = Socialite::driver('google')->user();
            return $this->finishProviderLogin(
                'google',
                (string) $providerUser->getId(),
                $providerUser->getName() ?: $providerUser->getNickname() ?: 'Google User',
                $providerUser->getEmail(),
                $providerUser->getAvatar()
            );
        } catch (\Throwable $exception) {
            Log::error('Google OAuth callback failed', ['error' => $exception->getMessage()]);
            return redirect('/login?social_error=Login%20Google%20gagal');
        }
    }

    public function tiktokRedirect()
    {
        $clientKey = config('services.tiktok.client_key');
        if (!$clientKey || !config('services.tiktok.client_secret')) {
            return redirect('/login?social_error=TikTok%20OAuth%20belum%20dikonfigurasi');
        }

        $state = Str::random(40);
        Cache::put('tiktok_oauth_state:' . $state, true, now()->addMinutes(10));
        $query = http_build_query([
            'client_key' => $clientKey,
            'scope' => 'user.info.basic',
            'response_type' => 'code',
            'redirect_uri' => config('services.tiktok.redirect'),
            'state' => $state,
        ]);

        return redirect('https://www.tiktok.com/v2/auth/authorize/?' . $query);
    }

    public function tiktokCallback(Request $request)
    {
        if (!$request->filled('code') || !Cache::pull('tiktok_oauth_state:' . $request->input('state'))) {
            return redirect('/login?social_error=Callback%20TikTok%20tidak%20valid');
        }

        try {
            $tokenResponse = Http::timeout(10)->asForm()->post('https://open.tiktokapis.com/v2/oauth/token/', [
                'client_key' => config('services.tiktok.client_key'),
                'client_secret' => config('services.tiktok.client_secret'),
                'code' => $request->input('code'),
                'grant_type' => 'authorization_code',
                'redirect_uri' => config('services.tiktok.redirect'),
            ])->throw()->json();

            $profile = Http::timeout(10)->withToken($tokenResponse['access_token'])
                ->get('https://open.tiktokapis.com/v2/user/info/', [
                    'fields' => 'open_id,display_name,avatar_url',
                ])->throw()->json('data.user');

            return $this->finishProviderLogin(
                'tiktok',
                (string) ($profile['open_id'] ?? ''),
                $profile['display_name'] ?? 'TikTok User',
                null,
                $profile['avatar_url'] ?? null
            );
        } catch (\Throwable $exception) {
            Log::error('TikTok OAuth callback failed', ['error' => $exception->getMessage()]);
            return redirect('/login?social_error=Login%20TikTok%20gagal');
        }
    }

    public function exchangeSocialLogin(Request $request)
    {
        $request->validate(['code' => 'required|string']);
        $payload = Cache::pull('social_login_exchange:' . $request->input('code'));
        if (!$payload) {
            return response()->json(['message' => 'Kode login sudah kedaluwarsa atau tidak valid.'], 422);
        }

        $user = User::findOrFail($payload['user_id']);
        return $this->tokenResponse($user, ucfirst($payload['provider']) . ' login successful');
    }

    public function startPhoneLogin(Request $request)
    {
        $data = $request->validate(['phone' => 'required|string|max:30']);
        $phone = preg_replace('/[^0-9+]/', '', $data['phone']);
        $twilioConfigured = config('services.twilio.sid') && config('services.twilio.token') && config('services.twilio.verify_service_sid');
        if (!$twilioConfigured) {
            return response()->json(['message' => 'OTP telepon belum aktif. Isi TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, dan TWILIO_VERIFY_SERVICE_SID di .env.'], 503);
        }

        Http::timeout(10)->withBasicAuth(config('services.twilio.sid'), config('services.twilio.token'))
            ->asForm()->post('https://verify.twilio.com/v2/Services/' . config('services.twilio.verify_service_sid') . '/Verifications', [
                'To' => $phone,
                'Channel' => 'sms',
            ])->throw();

        return response()->json(['message' => 'Kode OTP telah dikirim.']);
    }

    public function verifyPhoneLogin(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|string|max:30',
            'code' => 'required|string|max:10',
            'name' => 'nullable|string|max:255',
        ]);
        $phone = preg_replace('/[^0-9+]/', '', $data['phone']);
        $verification = Http::timeout(10)->withBasicAuth(config('services.twilio.sid'), config('services.twilio.token'))
            ->asForm()->post('https://verify.twilio.com/v2/Services/' . config('services.twilio.verify_service_sid') . '/VerificationCheck', [
                'To' => $phone,
                'Code' => $data['code'],
            ])->throw()->json();

        if (($verification['status'] ?? null) !== 'approved') {
            throw ValidationException::withMessages(['code' => ['Kode OTP tidak valid.']]);
        }

        $email = 'phone_' . str_replace('+', '', $phone) . '@kettiket.local';
        $user = User::firstOrCreate(
            ['phone' => $phone],
            ['email' => $email, 'name' => $data['name'] ?: $phone, 'password' => Hash::make(Str::random(40)), 'role' => 'customer', 'status' => true]
        );
        if ($data['name'] && $user->name !== $data['name']) {
            $user->update(['name' => $data['name']]);
        }

        return $this->tokenResponse($user, 'Phone login successful');
    }

    protected function finishProviderLogin(string $provider, string $providerId, ?string $name, ?string $email, ?string $avatar)
    {
        if ($providerId === '') {
            return redirect('/login?social_error=Identitas%20provider%20tidak%20ditemukan');
        }

        $user = User::where('social_provider', $provider)->where('social_id', $providerId)->first();
        if (!$user && $email) {
            $user = User::where('email', $email)->first();
        }
        $user ??= new User();
        $user->fill([
            'name' => $name ?: ucfirst($provider) . ' User',
            'email' => $email ?: $provider . '_' . $providerId . '@kettiket.local',
            'password' => $user->exists ? $user->password : Hash::make(Str::random(40)),
            'role' => $user->exists ? $user->role : 'customer',
            'status' => true,
            'social_provider' => $provider,
            'social_id' => $providerId,
            'avatar' => $avatar,
        ]);
        $user->save();

        $exchangeCode = Str::random(64);
        Cache::put('social_login_exchange:' . $exchangeCode, ['user_id' => $user->id, 'provider' => $provider], now()->addMinutes(2));
        return redirect('/login?social_code=' . urlencode($exchangeCode));
    }

    protected function tokenResponse(User $user, string $message)
    {
        if (!$user->status) {
            return response()->json(['message' => 'Your account has been deactivated.'], 403);
        }
        return response()->json([
            'message' => $message,
            'access_token' => $user->createToken('auth_token')->plainTextToken,
            'token_type' => 'Bearer',
            'user' => $user->load('organizer'),
        ]);
    }

    /**
     * Get the authenticated user's profile.
     */
    public function profile(Request $request)
    {
        return response()->json([
            'user' => $request->user()->load('organizer')
        ]);
    }

    /**
     * Update the authenticated user's profile & complex bio.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:30',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'bio' => 'nullable|string|max:1000',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:30',
            'preferred_language' => 'nullable|string|max:10',
            'timezone' => 'nullable|string|max:50',
            'profile_visibility' => 'nullable|in:public,private,organizer_only',
            'avatar' => 'nullable|string',
        ]);

        $user->update($request->only([
            'name', 'phone', 'date_of_birth', 'gender', 'bio',
            'address', 'city', 'state', 'postal_code', 'country',
            'emergency_contact_name', 'emergency_contact_phone',
            'preferred_language', 'timezone', 'profile_visibility', 'avatar'
        ]));

        if ($user->role === 'organizer' && $request->has('company_name')) {
            $user->organizer()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'company_name' => $request->company_name,
                    'phone' => $request->phone ?? $user->phone
                ]
            );
        }

        return response()->json([
            'message' => 'Profil dan biodata berhasil diperbarui!',
            'user' => $user->fresh()->load('organizer')
        ]);
    }

    /**
     * Log out user (revoke token).
     */
    public function logout(Request $request)
    {
        if ($request->user() && $request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }
}
