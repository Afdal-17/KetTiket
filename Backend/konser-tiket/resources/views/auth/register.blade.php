@extends('layouts.app')

@section('title', 'Register')

@push('styles')
<style>
    .auth-container {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 80vh;
        padding: 2rem 0;
    }
    .auth-card {
        width: 100%;
        max-width: 500px;
        padding: 2.5rem;
    }
    .auth-title {
        text-align: center;
        font-size: 1.8rem;
        margin-bottom: 2rem;
    }
    #register-error {
        color: var(--danger);
        font-size: 0.9rem;
        margin-bottom: 1rem;
        text-align: center;
        display: none;
    }
    .organizer-fields {
        display: none;
        background: rgba(139, 92, 246, 0.1);
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        border: 1px dashed var(--primary);
    }
    .social-login-container {
        margin-top: 1.5rem;
        display: grid;
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    .social-btn {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 1rem;
        padding: 0.85rem;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        border: 1px solid rgba(255,255,255,0.1);
        background: rgba(255, 255, 255, 0.03);
        color: inherit;
        backdrop-filter: blur(10px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }
    .social-btn:hover {
        background: rgba(255, 255, 255, 0.08);
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2), 0 4px 6px -2px rgba(0, 0, 0, 0.1);
    }
    .social-btn.google:hover { border-color: rgba(234, 67, 53, 0.5); }
    .social-btn.tiktok:hover { border-color: rgba(254, 44, 85, 0.5); }
    .social-btn.phone:hover { border-color: rgba(52, 168, 83, 0.5); }
    .social-icon-wrapper {
        display: flex;
        align-items: center;
        justify-content: center;
        background: #fff;
        border-radius: 50%;
        width: 28px; height: 28px;
        padding: 4px;
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
        <h2 class="auth-title">Create Account</h2>
        
        <div id="register-error"></div>

        <form id="register-form">
            <div class="form-group">
                <label class="form-label" for="name">Full Name</label>
                <input type="text" id="name" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <input type="email" id="email" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="role">Register As</label>
                <select id="role" class="form-control" disabled>
                    <option value="customer" selected>Customer</option>
                </select>
                <small class="text-muted">Semua user baru otomatis dibuat sebagai Customer. Role admin, organizer, dan scanner diatur oleh admin dari panel manajemen akun.</small>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" class="form-control" required minlength="8">
            </div>

            <div class="form-group">
                <label class="form-label" for="password_confirmation">Confirm Password</label>
                <input type="password" id="password_confirmation" class="form-control" required minlength="8">
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">Create Account</button>
        </form>

        <div class="divider">OR REGISTER WITH SOCIAL ACCOUNTS</div>

        <div class="social-login-container">
            <div class="card" style="padding:1rem; background: rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08); border-radius:12px;">
                <h4 style="margin-bottom: 0.75rem; font-size: 0.95rem;">Daftar dengan Google</h4>
                <form id="google-register-form" class="social-form">
                    <input type="hidden" name="provider" value="google">
                    <div class="form-group">
                        <label class="form-label" for="google_name">Nama Google</label>
                        <input type="text" id="google_name" name="name" class="form-control" placeholder="Nama akun Google" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="google_email">Email Google</label>
                        <input type="email" id="google_email" name="email" class="form-control" placeholder="nama@gmail.com" required>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;">Daftar via Google</button>
                </form>
            </div>

            <div class="card" style="padding:1rem; background: rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08); border-radius:12px;">
                <h4 style="margin-bottom: 0.75rem; font-size: 0.95rem;">Daftar dengan TikTok</h4>
                <form id="tiktok-register-form" class="social-form">
                    <input type="hidden" name="provider" value="tiktok">
                    <div class="form-group">
                        <label class="form-label" for="tiktok_name">Nama TikTok</label>
                        <input type="text" id="tiktok_name" name="name" class="form-control" placeholder="Nama akun TikTok" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="tiktok_email">Email TikTok</label>
                        <input type="email" id="tiktok_email" name="email" class="form-control" placeholder="tiktok@example.com" required>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;">Daftar via TikTok</button>
                </form>
            </div>

            <div class="card" style="padding:1rem; background: rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08); border-radius:12px;">
                <h4 style="margin-bottom: 0.75rem; font-size: 0.95rem;">Daftar dengan Nomor Telepon</h4>
                <form id="phone-register-form" class="social-form">
                    <input type="hidden" name="provider" value="phone">
                    <div class="form-group">
                        <label class="form-label" for="phone_number">Nomor Telepon</label>
                        <input type="tel" id="phone_number" name="phone" class="form-control" placeholder="0812xxxxxx" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="phone_name">Nama Akun</label>
                        <input type="text" id="phone_name" name="name" class="form-control" placeholder="Nama pemilik telepon" required>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;">Daftar via Telepon</button>
                </form>
            </div>
        </div>

        <p class="text-center text-muted mt-4" style="font-size: 0.9rem;">
            Already have an account? <a href="/login">Sign in</a>
        </p>
    </div>
</div>
@endsection

@push('scripts')
<script>
    if (Auth.getUser()) {
        window.location.href = `/${Auth.getUser().role}/dashboard`;
    }

    const form = document.getElementById('register-form');
    const errorDiv = document.getElementById('register-error');

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        UI.showLoader();
        errorDiv.style.display = 'none';

        const payload = {
            name: document.getElementById('name').value,
            email: document.getElementById('email').value,
            role: 'customer',
            password: document.getElementById('password').value,
            password_confirmation: document.getElementById('password_confirmation').value,
        };

        axios.post(`${API_URL}/register`, payload)
        .then(response => {
            const data = response.data;
            Auth.setSession(data.access_token, data.user);
            window.location.href = `/${data.user.role}/dashboard`;
        })
        .catch(error => {
            UI.hideLoader();
            let errMsg = 'Registration failed.';
            if (error.response && error.response.data && error.response.data.message) {
                errMsg = error.response.data.message;
            }
            errorDiv.textContent = errMsg;
            errorDiv.style.display = 'block';
        });
    });

    function submitSocialRegister(formId, provider) {
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
                let errMsg = 'Social registration failed.';
                if (error.response && error.response.data && error.response.data.message) {
                    errMsg = error.response.data.message;
                }
                errorDiv.textContent = errMsg;
                errorDiv.style.display = 'block';
            });
        });
    }

    submitSocialRegister('google-register-form', 'google');
    submitSocialRegister('tiktok-register-form', 'tiktok');
    submitSocialRegister('phone-register-form', 'phone');
</script>
@endpush
