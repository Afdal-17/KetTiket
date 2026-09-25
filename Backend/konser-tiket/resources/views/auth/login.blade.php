@extends('layouts.app')

@section('title', 'Login')

@push('styles')
<style>
    .auth-container {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 80vh;
    }
    .auth-card {
        width: 100%;
        max-width: 420px;
        padding: 2.5rem;
    }
    .auth-title {
        text-align: center;
        font-size: 1.8rem;
        margin-bottom: 2rem;
    }
    #login-error {
        color: var(--danger);
        font-size: 0.9rem;
        margin-bottom: 1rem;
        text-align: center;
        display: none;
    }
    .quick-login-container {
        margin-top: 1.5rem;
        border-top: 1px solid var(--border-color);
        padding-top: 1.5rem;
    }
    .quick-login-btn {
        width: 100%;
        margin-bottom: 0.5rem;
        background: rgba(255,255,255,0.05);
    }
    .social-login-container {
        margin-top: 1.5rem;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }
    .social-btn {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        padding: 0.75rem;
        border-radius: 8px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
        border: 1px solid var(--border-color);
        background: rgba(255, 255, 255, 0.05);
        color: inherit;
    }
    .social-btn:hover {
        background: rgba(255, 255, 255, 0.1);
    }
    .social-btn.google {
        border-color: #ea4335;
    }
    .social-btn.tiktok {
        border-color: #fe2c55;
    }
    .social-btn.phone {
        border-color: #34a853;
    }
    .divider {
        display: flex;
        align-items: center;
        text-align: center;
        margin: 1.5rem 0;
        color: var(--text-muted);
        font-size: 0.85rem;
    }
    .divider::before, .divider::after {
        content: '';
        flex: 1;
        border-bottom: 1px solid var(--border-color);
    }
    .divider::before {
        margin-right: .75em;
    }
    .divider::after {
        margin-left: .75em;
    }
</style>
@endpush

@section('content')
<div class="auth-container">
    <div class="glass-panel auth-card">
        <h2 class="auth-title">Welcome Back</h2>
        
        <div id="login-error"></div>

        <form id="login-form">
            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <input type="email" id="email" class="form-control" placeholder="you@example.com" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" class="form-control" placeholder="••••••••" required>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">Sign In</button>
        </form>

        <div class="divider">OR SIGN IN WITH SOCIAL ACCOUNTS</div>

        <div class="social-login-container">
            <div class="card" style="padding:1rem; background: rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08); border-radius:12px;">
                <h4 style="margin-bottom: 0.75rem; font-size: 0.95rem;">Masuk dengan Google</h4>
                <form id="google-login-form" class="social-form">
                    <input type="hidden" name="provider" value="google">
                    <div class="form-group">
                        <label class="form-label" for="google_login_name">Nama Google</label>
                        <input type="text" id="google_login_name" name="name" class="form-control" placeholder="Nama akun Google" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="google_login_email">Email Google</label>
                        <input type="email" id="google_login_email" name="email" class="form-control" placeholder="nama@gmail.com" required>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;">Masuk via Google</button>
                </form>
            </div>

            <div class="card" style="padding:1rem; background: rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08); border-radius:12px;">
                <h4 style="margin-bottom: 0.75rem; font-size: 0.95rem;">Masuk dengan TikTok</h4>
                <form id="tiktok-login-form" class="social-form">
                    <input type="hidden" name="provider" value="tiktok">
                    <div class="form-group">
                        <label class="form-label" for="tiktok_login_name">Nama TikTok</label>
                        <input type="text" id="tiktok_login_name" name="name" class="form-control" placeholder="Nama akun TikTok" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="tiktok_login_email">Email TikTok</label>
                        <input type="email" id="tiktok_login_email" name="email" class="form-control" placeholder="tiktok@example.com" required>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;">Masuk via TikTok</button>
                </form>
            </div>

            <div class="card" style="padding:1rem; background: rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08); border-radius:12px;">
                <h4 style="margin-bottom: 0.75rem; font-size: 0.95rem;">Masuk dengan Nomor Telepon</h4>
                <form id="phone-login-form" class="social-form">
                    <input type="hidden" name="provider" value="phone">
                    <div class="form-group">
                        <label class="form-label" for="phone_login_number">Nomor Telepon</label>
                        <input type="tel" id="phone_login_number" name="phone" class="form-control" placeholder="0812xxxxxx" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="phone_login_name">Nama Akun</label>
                        <input type="text" id="phone_login_name" name="name" class="form-control" placeholder="Nama pemilik telepon" required>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;">Masuk via Telepon</button>
                </form>
            </div>
        </div>

        <p class="text-center text-muted mt-4" style="font-size: 0.9rem;">
            Don't have an account? <a href="/register">Sign up here</a>
        </p>

        <!-- Quick Login for testing -->
        <div class="quick-login-container">
            <p class="text-center text-muted" style="font-size: 0.8rem; margin-bottom: 1rem;">Testing Accounts</p>
            <button onclick="quickLogin('customer@kettiket.com', 'password123')" class="btn btn-outline quick-login-btn">Customer Login</button>
            <button onclick="quickLogin('organizer@kettiket.com', 'password123')" class="btn btn-outline quick-login-btn">Organizer Login</button>
            <button onclick="quickLogin('admin@kettiket.com', 'password123')" class="btn btn-outline quick-login-btn">Admin Login</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    if (Auth.getUser()) {
        window.location.href = `/${Auth.getUser().role}/dashboard`;
    }

    const loginForm = document.getElementById('login-form');
    const errorDiv = document.getElementById('login-error');

    function performLogin(email, password) {
        UI.showLoader();
        errorDiv.style.display = 'none';

        axios.post(`${API_URL}/login`, {
            email: email,
            password: password
        })
        .then(response => {
            const data = response.data;
            Auth.setSession(data.access_token, data.user);
            window.location.href = `/${data.user.role}/dashboard`;
        })
        .catch(error => {
            UI.hideLoader();
            let errMsg = 'Login failed.';
            if (error.response && error.response.data && error.response.data.message) {
                errMsg = error.response.data.message;
            }
            errorDiv.textContent = errMsg;
            errorDiv.style.display = 'block';
        });
    }

    loginForm.addEventListener('submit', (e) => {
        e.preventDefault();
        performLogin(
            document.getElementById('email').value,
            document.getElementById('password').value
        );
    });

    window.quickLogin = function(email, password) {
        document.getElementById('email').value = email;
        document.getElementById('password').value = password;
        performLogin(email, password);
    }

    function submitSocialLogin(formId, provider) {
        const form = document.getElementById(formId);
        if (!form) return;

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            UI.showLoader();
            errorDiv.style.display = 'none';

            const formData = new FormData(form);
            const payload = {
                provider: provider,
                name: formData.get('name'),
                email: formData.get('email') || '',
                phone: formData.get('phone') || '',
            };

            axios.post(`${API_URL}/login/social`, payload)
            .then(response => {
                const data = response.data;
                Auth.setSession(data.access_token, data.user);
                window.location.href = `/${data.user.role}/dashboard`;
            })
            .catch(error => {
                UI.hideLoader();
                let errMsg = 'Social login failed.';
                if (error.response && error.response.data && error.response.data.message) {
                    errMsg = error.response.data.message;
                }
                errorDiv.textContent = errMsg;
                errorDiv.style.display = 'block';
            });
        });
    }

    submitSocialLogin('google-login-form', 'google');
    submitSocialLogin('tiktok-login-form', 'tiktok');
    submitSocialLogin('phone-login-form', 'phone');
</script>
@endpush
