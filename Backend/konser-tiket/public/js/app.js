// === KetTiket Core SPA Application Script ===
const API = '/api';
const PAYMENT_WEBHOOK_SECRET = 'kettiket-local-secret';

async function computeHmacSha256(message, secret) {
    const encoder = new TextEncoder();
    const key = await crypto.subtle.importKey(
        'raw',
        encoder.encode(secret),
        { name: 'HMAC', hash: 'SHA-256' },
        false,
        ['sign']
    );
    const signature = await crypto.subtle.sign('HMAC', key, encoder.encode(message));
    return Array.from(new Uint8Array(signature))
        .map(byte => byte.toString(16).padStart(2, '0'))
        .join('');
}

// Error Handler & Safe Navigation Helper
window.addEventListener('error', function (e) {
    console.error('Uncaught error:', e.error || e.message);
});

if (window.axios) {
    window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
    window.axios.defaults.timeout = 10000; // 10 detik timeout agar request tidak pernah menggantung tanpa batas
    window.axios.interceptors.request.use(c => {
        const t = Auth.getToken();
        if (t) c.headers.Authorization = 'Bearer ' + t;
        return c;
    });
    window.axios.interceptors.response.use(
        r => r,
        e => {
            if (e.response && e.response.status === 401) {
                Auth.clear();
                Router.navigate('/login');
            }
            return Promise.reject(e);
        }
    );
}

const Auth = {
    getToken: () => localStorage.getItem('auth_token'),
    getUser: () => {
        try {
            return JSON.parse(localStorage.getItem('user_data'));
        } catch (e) {
            return null;
        }
    },
    setSession: (t, u) => {
        localStorage.setItem('auth_token', t);
        localStorage.setItem('user_data', JSON.stringify(u));
    },
    clear: () => {
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user_data');
    },
    logout: async () => {
        UI.showLoader();
        try {
            await axios.post(API + '/logout');
        } catch (e) { }
        Auth.clear();
        UI.hideLoader();
        Router.navigate('/');
    }
};

const UI = {
    showLoader: () => {
        const el = document.getElementById('global-loader');
        if (el) el.classList.add('active');
    },
    hideLoader: () => {
        const el = document.getElementById('global-loader');
        if (el) el.classList.remove('active');
    },
    // Toast notification dengan ikon lucide.
    // PENTING: fungsi ini wajib ada — doScan() dan lainnya memanggil UI.toast SEBELUM
    // merender hasil; jika tidak ada, JS error sehingga data hasil scan tidak pernah tampil.
    toast: (msg, type = 'info') => {
        let box = document.getElementById('toast-container');
        if (!box) {
            box = document.createElement('div');
            box.id = 'toast-container';
            document.body.appendChild(box);
        }
        const iconFor = { success: 'circle-check', danger: 'circle-x', warning: 'alert-triangle', info: 'info' };
        const el = document.createElement('div');
        el.className = 'toast toast-' + type;
        el.innerHTML = `<i data-lucide="${iconFor[type] || 'info'}" style="width:18px;height:18px;flex-shrink:0"></i><span></span>`;
        el.querySelector('span').textContent = msg;
        box.appendChild(el); // observer akan mengubah <i data-lucide> menjadi svg
        setTimeout(() => {
            el.classList.add('toast-out');
            setTimeout(() => el.remove(), 350);
        }, 4000);
    },
    renderNavbar: (dark) => {
        const u = Auth.getUser();
        let r = '';
        if (u) {
            let d = '/' + u.role + '/dashboard';
            if (u.role === 'scanner') d = '/scanner';
            r = `
                <span class="user-greeting" style="font-size:0.9rem;opacity:0.9;margin-right:0.5rem"><i data-lucide="circle-user" style="width:14px;height:14px;vertical-align:-2px"></i> Welcome, <b>${u.name}</b> (${u.role.toUpperCase()})</span>
                <a href="${d}" class="btn-nav"><i data-lucide="layout-dashboard" style="width:15px;height:15px"></i> Dashboard</a>
                <a href="/profile" class="btn-nav"><i data-lucide="user" style="width:15px;height:15px"></i> Profil Saya</a>
            `;
        } else {
            r = `
                <a href="/login" class="btn-nav"><i data-lucide="log-in" style="width:15px;height:15px"></i> Masuk</a>
                <a href="/register" class="btn-nav-solid"><i data-lucide="user-plus" style="width:15px;height:15px"></i> Daftar</a>
            `;
        }
        return `
            <nav id="navbar" class="navbar${dark ? ' dark-page' : ''}">
                <div class="container flex items-center justify-between">
                    <a href="/" class="nav-brand">
                        <i>KetTiket</i>
                    </a>
                    <button class="mobile-menu-btn" id="mobile-menu-btn" aria-label="Toggle navigation" onclick="document.getElementById('nav-links').classList.toggle('show')">
                        <i data-lucide="menu" style="width:24px;height:24px"></i>
                    </button>
                    <div class="nav-links" id="nav-links">${r}</div>
                </div>
            </nav>
        `;
    },
    mount: (html, dark) => {
        const app = document.getElementById('app');
        window.onscroll = () => {
            const n = document.getElementById('navbar');
            if (n && !dark) {
                if (window.scrollY > 50) n.classList.add('scrolled');
                else n.classList.remove('scrolled');
            }
        };
        app.innerHTML = UI.renderNavbar(dark) + '<div>' + html + '</div>' + aiWidget();
        const navLinks = document.getElementById('nav-links');
        app.querySelectorAll('a').forEach(l => {
            if (l.hostname === location.hostname && !l.hasAttribute('target')) {
                l.addEventListener('click', e => {
                    e.preventDefault();
                    if (navLinks) navLinks.classList.remove('show');
                    Router.navigate(l.pathname);
                });
            }
        });
    }
};

const Router = {
    routes: [],
    add: (p, h) => Router.routes.push({ path: p, handler: h }),
    navigate: p => {
        history.pushState({}, '', p);
        Router.resolve();
    },
    resolve: () => {
        const p = location.pathname;
        for (let r of Router.routes) {
            const rx = new RegExp('^' + r.path.replace(/:\w+/g, '([^/]+)') + '$');
            const m = p.match(rx);
            if (m) {
                m.shift();
                return r.handler.apply(null, m);
            }
        }
        UI.mount('<div class="page-body container text-center" style="padding:4rem 1rem"><i data-lucide="home" style="width:44px;height:44px;color:var(--primary)"></i><h1>404</h1><p>Halaman tidak ditemukan.</p><a href="/" class="btn btn-primary mt-3"><i data-lucide="arrow-left" style="width:16px;height:16px"></i> Kembali ke Beranda</a></div>', true);
    }
};
window.addEventListener('popstate', Router.resolve);

// === LANDING ===
Router.add('/', () => {
    UI.mount(`
        <div class="hero-bg">
            <div class="container" style="display:flex;align-items:center;justify-content:space-between;gap:2rem;flex-wrap:wrap">
                <div style="flex:1;min-width:300px">
                    <span class="badge mb-2" style="font-size:0.85rem;padding:0.4rem 0.8rem;background:rgba(255,255,255,0.18);color:#fff;border:1px solid rgba(255,255,255,0.35)"><i data-lucide="sparkles" style="width:13px;height:13px;vertical-align:-2px"></i> SaaS Ticketing Terintegrasi AI</span>
                    <h1 style="font-size:3.5rem;line-height:1.1;margin-bottom:1.5rem">Bangun<br>Pengalaman<br>Terbaikmu!</h1>
                    <p style="font-size:1.15rem;margin-bottom:2rem;max-width:450px;opacity:.9">Platform pemesanan tiket konser terdepan dengan integrasi Bot Assistant cerdas untuk rekomendasi terbaik dan peta lokasi Google Maps lengkap.</p>
                    <div style="display:flex;gap:1rem;flex-wrap:wrap">
                        <button class="btn btn-accent btn-lg" onclick="Router.navigate('/explore')"><i data-lucide="search" style="width:17px;height:17px"></i> Eksplor Sekarang</button>
                        <button class="btn btn-outline btn-lg" style="color:#fff;border-color:rgba(255,255,255,0.4)" onclick="openGlobalAiChat()"><i data-lucide="bot" style="width:17px;height:17px"></i> Tanya Bot Assistant</button>
                    </div>
                </div>
                <div style="flex:1;display:flex;justify-content:center;min-width:280px">
                    <div class="carousel">
                        <div class="carousel-inner">
                            <div class="carousel-item" style="background-image:url('/images/sheila_on_7.jpeg')"></div>
                            <div class="carousel-item" style="background-image:url('/images/perunggu.jpeg')"></div>
                            <div class="carousel-item" style="background-image:url('/images/opick.jpg')"></div>
                            <div class="carousel-item" style="background-image:url('/images/for%20revenge.jpg')"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="wave-divider">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
                    <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V120H0V95.8C59.71,118,137.9,122.9,209.6,108.62,246.54,101.27,282.8,80.7,321.39,56.44Z" class="shape-fill"></path>
                </svg>
            </div>
        </div>
        <div class="features-section">
            <div class="container text-center">
                <h2><i data-lucide="sparkles" style="width:23px;height:23px;vertical-align:-4px;color:var(--primary)"></i> Mengapa Memilih KetTiket?</h2>
                <p class="text-muted">Solusi lengkap untuk pengalaman konser musik tanpa ribet</p>
            </div>
            <div class="features-grid">
                <div class="feature-box">
                    <div class="feature-icon"><i data-lucide="bot" style="width:28px;height:28px"></i></div>
                    <h3><i data-lucide="bot" style="width:19px;height:19px;vertical-align:-4px;color:var(--primary)"></i> AI Assistant & Intelligence</h3>
                    <p class="text-muted">Asisten AI cerdas untuk rekomendasi konser, konsultasi event, analisis penjualan, dan monitoring platform.</p>
                </div>
                <div class="feature-box">
                    <div class="feature-icon"><i data-lucide="map-pin" style="width:28px;height:28px"></i></div>
                    <h3><i data-lucide="map-pin" style="width:19px;height:19px;vertical-align:-4px;color:var(--primary)"></i> Google Maps Terintegrasi</h3>
                    <p class="text-muted">Tinjau lokasi konser dan venue langsung dengan peta Google Maps interaktif & navigasi GPS akurat.</p>
                </div>
                <div class="feature-box">
                    <div class="feature-icon"><i data-lucide="shield-check" style="width:28px;height:28px"></i></div>
                    <h3><i data-lucide="shield-check" style="width:19px;height:19px;vertical-align:-4px;color:var(--primary)"></i> Seat Map & Ticket Protection</h3>
                    <p class="text-muted">Sistem locking kursi real-time dan barcode e-ticket unik anti pemalsuan.</p>
                </div>
            </div>
        </div>
    `);
});

Router.add('/explore', () => {
    const u = Auth.getUser();
    if (u && u.role === 'customer') Router.navigate('/customer/dashboard');
    else if (u && u.role === 'organizer') Router.navigate('/organizer/dashboard');
    else if (u && u.role === 'admin') Router.navigate('/admin/dashboard');
    else Router.navigate('/customer/dashboard');
});

// === INTEGRATED PROFILE PAGE & DASHBOARD ===
Router.add('/profile', async () => {
    const u = Auth.getUser();
    if (!u) return Router.navigate('/login');

    UI.mount(`
        <div class="page-body">
            <div class="container" style="max-width:900px">
                <div class="flex justify-between items-center mb-4">
                    <div>
                        <h2><i data-lucide="user" style="width:23px;height:23px;vertical-align:-4px;color:var(--primary)"></i> Pengaturan Profil & Biodata Akun</h2>
                        <p class="text-muted">Kelola identitas, informasi kontak, dan data pendukung akun ${u.role.toUpperCase()}</p>
                    </div>
                    <button class="btn btn-outline" onclick="Router.navigate('/${u.role === 'scanner' ? 'scanner' : u.role + '/dashboard'}')"><i data-lucide="arrow-left" style="width:16px;height:16px"></i> Kembali ke Dashboard</button>
                </div>

                <div class="grid-profile">
                    <div>
                        <div class="card text-center p-4">
                            ${u.avatar
            ? `<img src="${u.avatar}" alt="Foto profil ${u.name}" style="width:90px;height:90px;border-radius:50%;object-fit:cover;margin:0 auto 1rem;display:block">`
            : `<div style="width:90px;height:90px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:2.5rem;font-weight:700;margin:0 auto 1rem">${u.name ? u.name.charAt(0).toUpperCase() : 'U'}</div>`}
                            <h3>${u.name}</h3>
                            <p class="text-muted" style="font-size:0.85rem"><i data-lucide="mail" style="width:13px;height:13px;vertical-align:-2px"></i> ${u.email}</p>
                            <span class="badge badge-purple mt-2">${u.role.toUpperCase()}</span>
                            <div class="mt-4 pt-3" style="border-top:1px solid var(--border);text-align:left;font-size:0.85rem">
                                <p><b><i data-lucide="calendar" style="width:13px;height:13px;vertical-align:-2px"></i> Bergabung:</b> ${u.created_at ? new Date(u.created_at).toLocaleDateString('id-ID') : 'Baru saja'}</p>
                                <p><b>Status Akun:</b> <span class="badge badge-success"><i data-lucide="circle-check" style="width:11px;height:11px;vertical-align:-1px"></i> Aktif</span></p>
                            </div>
                            <button onclick="Auth.logout()" class="btn btn-danger w-100 mt-4">
                                <i data-lucide="log-out" style="width:16px;height:16px"></i> Keluar dari Akun
                            </button>
                        </div>
                    </div>

                    <div>
                        <div class="card p-4">
                            <h3><i data-lucide="clipboard-list" style="width:19px;height:19px;vertical-align:-3px"></i> Biodata & Informasi Pribadi</h3>
                            <form id="profile-form" class="mt-3">
                                <div class="form-group mb-3">
                                    <label class="form-label"><i data-lucide="user" style="width:14px;height:14px;vertical-align:-2px"></i> Nama Lengkap</label>
                                    <input id="pf-name" class="form-control" value="${u.name || ''}" required>
                                </div>
                                <div class="grid-half mb-3">
                                    <div class="form-group">
                                        <label class="form-label"><i data-lucide="phone" style="width:14px;height:14px;vertical-align:-2px"></i> Nomor Telepon / WA</label>
                                        <input id="pf-phone" class="form-control" value="${u.phone || ''}" placeholder="081234567890">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label"><i data-lucide="calendar" style="width:14px;height:14px;vertical-align:-2px"></i> Tanggal Lahir</label>
                                        <input id="pf-dob" type="date" class="form-control" value="${u.date_of_birth ? u.date_of_birth.slice(0, 10) : ''}">
                                    </div>
                                </div>
                                <div class="grid-half mb-3">
                                    <div class="form-group">
                                        <label class="form-label"><i data-lucide="users" style="width:14px;height:14px;vertical-align:-2px"></i> Jenis Kelamin</label>
                                        <select id="pf-gender" class="form-control">
                                            <option value="">-- Pilih --</option>
                                            <option value="male" ${u.gender === 'male' ? 'selected' : ''}>Laki-Laki</option>
                                            <option value="female" ${u.gender === 'female' ? 'selected' : ''}>Perempuan</option>
                                            <option value="other" ${u.gender === 'other' ? 'selected' : ''}>Lainnya</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label"><i data-lucide="map-pin" style="width:14px;height:14px;vertical-align:-2px"></i> Kota Asal</label>
                                        <input id="pf-city" class="form-control" value="${u.city || ''}" placeholder="Jakarta / Bandung">
                                    </div>
                                </div>
                                <div class="form-group mb-3">
                                    <label class="form-label"><i data-lucide="home" style="width:14px;height:14px;vertical-align:-2px"></i> Alamat Domisili</label>
                                    <input id="pf-address" class="form-control" value="${u.address || ''}" placeholder="Jl. Raya Konser No. 45">
                                </div>
                                <div class="form-group mb-3">
                                    <label class="form-label"><i data-lucide="file-text" style="width:14px;height:14px;vertical-align:-2px"></i> Bio / Ringkasan Preferensi Musik</label>
                                    <textarea id="pf-bio" class="form-control" rows="3" placeholder="Suka genre musik pop, rock, dangdut...">${u.bio || ''}</textarea>
                                </div>
                                <div class="grid-half mb-3">
                                    <div class="form-group">
                                        <label class="form-label"><i data-lucide="user" style="width:14px;height:14px;vertical-align:-2px"></i> Nama Kontak Darurat</label>
                                        <input id="pf-em-name" class="form-control" value="${u.emergency_contact_name || ''}" placeholder="Nama Kerabat">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label"><i data-lucide="phone" style="width:14px;height:14px;vertical-align:-2px"></i> No. Telp Kontak Darurat</label>
                                        <input id="pf-em-phone" class="form-control" value="${u.emergency_contact_phone || ''}" placeholder="081987654321">
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary btn-lg w-100 mt-2"><i data-lucide="save" style="width:17px;height:17px"></i> Simpan Biodata & Profil</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `, true);

    try {
        const r = await axios.get(API + '/profile');
        const usr = r.data.user;
        if (usr) {
            document.getElementById('pf-name').value = usr.name || '';
            document.getElementById('pf-phone').value = usr.phone || '';
            document.getElementById('pf-dob').value = usr.date_of_birth ? usr.date_of_birth.slice(0, 10) : '';
            document.getElementById('pf-gender').value = usr.gender || '';
            document.getElementById('pf-city').value = usr.city || '';
            document.getElementById('pf-address').value = usr.address || '';
            document.getElementById('pf-bio').value = usr.bio || '';
            document.getElementById('pf-em-name').value = usr.emergency_contact_name || '';
            document.getElementById('pf-em-phone').value = usr.emergency_contact_phone || '';
        }
    } catch (e) { }

    document.getElementById('profile-form').addEventListener('submit', async e => {
        e.preventDefault();
        UI.showLoader();
        try {
            const payload = {
                name: document.getElementById('pf-name').value,
                phone: document.getElementById('pf-phone').value,
                date_of_birth: document.getElementById('pf-dob').value || null,
                gender: document.getElementById('pf-gender').value || null,
                city: document.getElementById('pf-city').value,
                address: document.getElementById('pf-address').value,
                bio: document.getElementById('pf-bio').value,
                emergency_contact_name: document.getElementById('pf-em-name').value,
                emergency_contact_phone: document.getElementById('pf-em-phone').value
            };
            const r = await axios.put(API + '/profile', payload);
            alert('Biodata & profil berhasil diperbarui secara permanen!');
            Auth.setSession(Auth.getToken(), r.data.user);
            Router.navigate('/profile');
        } catch (err) {
            alert(err.response?.data?.message || 'Gagal memperbarui profil');
        }
        UI.hideLoader();
    });
});
window.closeSocialDialog = () => {
    document.getElementById('social-dialog')?.remove();
};

window.doSocialAuth = (prov, mode = 'login') => {
    closeSocialDialog();
    const isPhone = prov === 'phone';
    if (!isPhone) {
        window.location.href = `/auth/${prov}/redirect`;
        return;
    }
    const title = mode === 'register' ? 'Daftar via Nomor Telepon' : 'Masuk via Nomor Telepon';

    document.body.insertAdjacentHTML('beforeend', `
        <div id="social-dialog" style="position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.65);display:flex;align-items:center;justify-content:center;padding:1rem">
            <div class="card" style="width:100%;max-width:420px;position:relative">
                <button type="button" id="social-dialog-close" aria-label="Tutup" style="position:absolute;right:1rem;top:.75rem;border:0;background:transparent;cursor:pointer;display:flex;align-items:center;justify-content:center"><i data-lucide="circle-x" style="width:20px;height:20px"></i></button>
                <h3>${title}</h3>
                <p class="text-muted" style="font-size:.9rem">Kode OTP akan dikirim ke nomor Anda melalui SMS.</p>
                <div id="social-dialog-error" class="text-danger" style="min-height:1.25rem;font-size:.85rem"></div>
                <form id="social-dialog-form">
                    <div class="form-group"><label class="form-label"><i data-lucide="smartphone" style="width:14px;height:14px;vertical-align:-2px"></i> Nomor Telepon</label><input id="social-phone" class="form-control" type="tel" placeholder="+62812xxxxxx" required></div>
                    <button type="submit" class="btn btn-primary w-100"><i data-lucide="send" style="width:16px;height:16px"></i> Kirim Kode OTP</button>
                </form>
            </div>
        </div>
    `);

    document.getElementById('social-dialog-close').addEventListener('click', closeSocialDialog);
    document.getElementById('social-dialog-form').addEventListener('submit', async event => {
        event.preventDefault();
        const error = document.getElementById('social-dialog-error');
        UI.showLoader();
        try {
            const phone = document.getElementById('social-phone').value;
            await axios.post(API + '/login/phone/start', { phone });
            document.getElementById('social-dialog-form').innerHTML = `
                <div class="form-group"><label class="form-label"><i data-lucide="user" style="width:14px;height:14px;vertical-align:-2px"></i> Nama</label><input id="social-name" class="form-control" type="text" placeholder="Nama Anda" required></div>
                <div class="form-group"><label class="form-label"><i data-lucide="smartphone" style="width:14px;height:14px;vertical-align:-2px"></i> Kode OTP</label><input id="social-code" class="form-control" inputmode="numeric" autocomplete="one-time-code" required></div>
                <button type="submit" class="btn btn-primary w-100"><i data-lucide="shield-check" style="width:16px;height:16px"></i> Verifikasi dan Masuk</button>`;
            document.getElementById('social-dialog-form').dataset.phone = phone;
            document.getElementById('social-dialog-form').onsubmit = async verifyEvent => {
                verifyEvent.preventDefault();
                try {
                    const response = await axios.post(API + '/login/phone/verify', {
                        phone,
                        name: document.getElementById('social-name').value,
                        code: document.getElementById('social-code').value,
                    });
                    Auth.setSession(response.data.access_token, response.data.user);
                    closeSocialDialog();
                    Router.navigate('/customer/dashboard');
                } catch (verifyError) {
                    document.getElementById('social-dialog-error').innerText = verifyError.response?.data?.message || 'OTP tidak valid.';
                }
            };
        } catch (err) {
            error.innerText = err.response?.data?.message || 'Autentikasi provider gagal.';
        }
        UI.hideLoader();
    });
};

window.doSocialLogin = prov => doSocialAuth(prov, 'login');
window.doSocialRegister = prov => doSocialAuth(prov, 'register');

// === LOGIN ===
Router.add('/login', () => {
    const socialCode = new URLSearchParams(window.location.search).get('social_code');
    const socialError = new URLSearchParams(window.location.search).get('social_error');
    if (socialCode) {
        UI.showLoader();
        axios.post(API + '/login/social/exchange', { code: socialCode })
            .then(response => {
                Auth.setSession(response.data.access_token, response.data.user);
                window.history.replaceState({}, '', '/login');
                Router.navigate('/customer/dashboard');
            })
            .catch(error => {
                window.history.replaceState({}, '', '/login');
                Router.navigate('/login');
            })
            .finally(() => UI.hideLoader());
        return;
    }
    if (Auth.getUser()) return Router.navigate('/');
    UI.mount(`
        <div class="page-body" style="display:flex;align-items:center;justify-content:center">
            <div class="card" style="width:100%;max-width:440px">
                <h2 class="text-center"><i data-lucide="log-in" style="width:23px;height:23px;vertical-align:-4px;color:var(--primary)"></i> Selamat Datang</h2>
                <p class="text-center text-muted mb-3">Masuk ke akun KetTiket Anda</p>
                <div class="social-buttons">
                    <button class="btn-social" onclick="doSocialLogin('google')">
                        <svg width="18" height="18" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                        Google
                    </button>
                    <button class="btn-social" onclick="doSocialLogin('tiktok')">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z" fill="#000000"/><path d="M16.5 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-1v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z" fill="#25F4EE" opacity="0.4"/><path d="M17.5 7.69a4.83 4.83 0 0 1-3.77-4.25V3h-1v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V10.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 6 21.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z" fill="#FE2C55" opacity="0.4"/></svg>
                        TikTok
                    </button>
                    <button class="btn-social" onclick="doSocialLogin('phone')">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                        No. HP
                    </button>
                </div>
                <div class="flex items-center gap-2 mb-3">
                    <hr style="flex:1;border:none;border-top:1px solid var(--border)">
                    <span class="text-muted" style="font-size:.85rem">atau email</span>
                    <hr style="flex:1;border:none;border-top:1px solid var(--border)">
                </div>
                <div id="auth-err" class="text-danger text-center mb-2" style="font-size:0.9rem">${socialError || ''}</div>
                <form id="login-form">
                    <div class="form-group">
                        <label class="form-label"><i data-lucide="mail" style="width:14px;height:14px;vertical-align:-2px"></i> Email</label>
                        <input type="email" id="l-email" class="form-control" placeholder="nama@email.com" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i data-lucide="lock" style="width:14px;height:14px;vertical-align:-2px"></i> Password</label>
                        <input type="password" id="l-pass" class="form-control" placeholder="••••••••" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i data-lucide="log-in" style="width:17px;height:17px"></i> Masuk ke Sistem</button>
                </form>
                <div class="mt-3 p-2" style="background:rgba(245,158,11,.08);border-radius:8px;border:1px dashed var(--accent);font-size:.8rem;text-align:center">
                    <b><i data-lucide="info" style="width:13px;height:13px;vertical-align:-2px"></i> Akun Pengujian Demo</b> (password: <code>password123</code>)<br>
                    Admin: <code>admin@kettiket.com</code><br>
                    Organizer: <code>organizer@kettiket.com</code><br>
                    Customer: <code>customer@kettiket.com</code><br>
                    Scanner: <code>scanner@kettiket.com</code>
                </div>
                <p class="text-center mt-3" style="font-size:.9rem">Belum punya akun? <a href="/register">Daftar Sekarang</a></p>
            </div>
        </div>
    `, true);

    document.getElementById('login-form').addEventListener('submit', async e => {
        e.preventDefault();
        UI.showLoader();
        try {
            const r = await axios.post(API + '/login', {
                email: document.getElementById('l-email').value,
                password: document.getElementById('l-pass').value
            });
            Auth.setSession(r.data.access_token, r.data.user);
            const role = r.data.user?.role;
            if (role === 'admin') Router.navigate('/admin/dashboard');
            else if (role === 'organizer') Router.navigate('/organizer/dashboard');
            else if (role === 'scanner') Router.navigate('/scanner');
            else Router.navigate('/customer/dashboard');
        } catch (err) {
            document.getElementById('auth-err').innerText = err.response?.data?.message || 'Login gagal. Periksa kembali email dan password Anda.';
        }
        UI.hideLoader();
    });
});

// === REGISTER ===
Router.add('/register', () => {
    if (Auth.getUser()) return Router.navigate('/');
    UI.mount(`
        <div class="page-body" style="display:flex;align-items:center;justify-content:center">
            <div class="card" style="width:100%;max-width:440px">
                <h2 class="text-center"><i data-lucide="user-plus" style="width:23px;height:23px;vertical-align:-4px;color:var(--primary)"></i> Buat Akun Baru</h2>
                <p class="text-center text-muted mb-3">Bergabung dan mulai pengalaman konser terbaik</p>
                <div id="auth-err" class="text-danger text-center mb-2" style="font-size:0.9rem"></div>
                <form id="reg-form">
                    <div class="form-group">
                        <label class="form-label"><i data-lucide="user" style="width:14px;height:14px;vertical-align:-2px"></i> Nama Lengkap</label>
                        <input type="text" id="r-name" class="form-control" placeholder="Nama Anda" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i data-lucide="mail" style="width:14px;height:14px;vertical-align:-2px"></i> Email</label>
                        <input type="email" id="r-email" class="form-control" placeholder="nama@email.com" required>
                    </div>
                    <p class="text-muted" style="font-size:.8rem;margin:-.4rem 0 .9rem;display:flex;gap:.4rem;align-items:flex-start"><i data-lucide="info" style="width:13px;height:13px;flex:none;margin-top:.1rem"></i> Semua akun baru otomatis terdaftar sebagai <b>Customer</b> (penonton).</p>
                    <div class="form-group">
                        <label class="form-label"><i data-lucide="lock" style="width:14px;height:14px;vertical-align:-2px"></i> Password</label>
                        <input type="password" id="r-pass" class="form-control" placeholder="Minimal 8 karakter" required minlength="8">
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i data-lucide="user-plus" style="width:17px;height:17px"></i> Daftar Sekarang</button>
                </form>
                <div class="text-center text-muted mt-3" style="font-size:.85rem">atau daftar dengan</div>
                <div class="social-buttons mt-2">
                    <button type="button" class="btn-social" onclick="doSocialRegister('google')">
                     <svg width="18" height="18" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                    Google</button>
                    <button type="button" class="btn-social" onclick="doSocialRegister('tiktok')">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z" fill="#000000"/><path d="M16.5 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-1v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z" fill="#25F4EE" opacity="0.4"/><path d="M17.5 7.69a4.83 4.83 0 0 1-3.77-4.25V3h-1v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V10.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 6 21.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z" fill="#FE2C55" opacity="0.4"/></svg>
                    TikTok</button>
                    <button type="button" class="btn-social" onclick="doSocialRegister('phone')">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                    No. HP</button>
                </div>
                <p class="text-center mt-3" style="font-size:.9rem">Sudah punya akun? <a href="/login">Masuk</a></p>
            </div>
        </div>
    `, true);

    document.getElementById('reg-form').addEventListener('submit', async e => {
        e.preventDefault();
        UI.showLoader();
        try {
            const r = await axios.post(API + '/register', {
                name: document.getElementById('r-name').value,
                email: document.getElementById('r-email').value,
                password: document.getElementById('r-pass').value,
                password_confirmation: document.getElementById('r-pass').value
            });
            Auth.setSession(r.data.access_token, r.data.user);
            Router.navigate('/customer/dashboard');
        } catch (err) {
            document.getElementById('auth-err').innerText = err.response?.data?.message || 'Gagal mendaftar. Silakan coba lagi.';
        }
        UI.hideLoader();
    });
});

// === GLOBAL BOT ASSISTANT WIDGET (rule-based, seluruh data website) ===
window.aiChatLog = window.aiChatLog || [];

const aiGreetingHtml = () => {
    const u = Auth.getUser();
    const who = u ? `Halo <b>${escapeHtml(u.name)}</b>! ` : 'Halo! ';
    return `<div class="ai-msg bot">${who}Saya <b>Bot Assistant KetTiket</b> — semua jawaban diambil langsung dari data website ini: daftar event, harga tiket, jadwal, venue, cara beli, e-tiket, sampai check-in gate. Mau tanya soal apa?</div>`;
};

const aiWidget = () => {
    const u = Auth.getUser();
    const role = u ? u.role : 'guest';
    const roleTitle = role === 'admin' ? 'Admin Data Assistant' : role === 'organizer' ? 'Organizer Data Assistant' : 'Bot Assistant KetTiket';

    return `
        <div id="ai-widget">
            <div id="ai-window" style="display:none">
                <div class="ai-header">
                    <span><i data-lucide="sparkles" style="width:15px;height:15px;vertical-align:-3px"></i> ${roleTitle}</span>
                    <div style="display:flex;gap:0.5rem;align-items:center">
                        <span title="Clear chat" onclick="clearAiChat()" style="font-size:0.78rem;opacity:0.85;cursor:pointer;display:inline-flex;align-items:center;gap:4px"><i data-lucide="trash-2" style="width:13px;height:13px"></i> Clear</span>
                        <span onclick="toggleAiWindow()" style="cursor:pointer;display:inline-flex;align-items:center;gap:4px"><i data-lucide="circle-x" style="width:14px;height:14px"></i> Close</span>
                    </div>
                </div>
                <div id="ai-chat" class="ai-chat-area">
                    ${aiGreetingHtml()}
                    ${window.aiChatLog.map(m => `<div class="ai-msg ${m.who}">${m.html}</div>`).join('')}
                </div>
                <div class="ai-suggestions p-2" style="background:#fff;border-top:1px solid var(--border);display:flex;gap:0.3rem;overflow-x:auto;padding:0.4rem 0.6rem">
                    ${getAiSuggestions(role)}
                </div>
                <div class="ai-input-area">
                    <input id="ai-input" placeholder="Ketik pesan atau pertanyaan..." onkeypress="if(event.key==='Enter')sendAiMsg()">
                    <button onclick="sendAiMsg()" title="Kirim"><i data-lucide="send" style="width:16px;height:16px"></i></button>
                </div>
            </div>
            <button class="ai-fab" onclick="toggleAiWindow()" title="Buka AI Assistant">
                <i data-lucide="bot" style="width:26px;height:26px"></i>
            </button>
        </div>
    `;
};

function getAiSuggestions(role) {
    if (role === 'organizer') {
        return `
            <button class="btn btn-sm btn-outline" style="font-size:0.75rem;white-space:nowrap;padding:0.2rem 0.5rem" onclick="quickAiPrompt('Analisis penjualan tiket acara saya')"><i data-lucide="chart-column" style="width:12px;height:12px"></i> Analisis Sales</button>
            <button class="btn btn-sm btn-outline" style="font-size:0.75rem;white-space:nowrap;padding:0.2rem 0.5rem" onclick="quickAiPrompt('Berikan rekomendasi penetapan harga tiket konser')"><i data-lucide="banknote" style="width:12px;height:12px"></i> Rekomendasi Harga</button>
            <button class="btn btn-sm btn-outline" style="font-size:0.75rem;white-space:nowrap;padding:0.2rem 0.5rem" onclick="quickAiPrompt('Buatkan deskripsi promosi konser menarik')"><i data-lucide="file-text" style="width:12px;height:12px"></i> Copy Event</button>
        `;
    } else if (role === 'admin') {
        return `
            <button class="btn btn-sm btn-outline" style="font-size:0.75rem;white-space:nowrap;padding:0.2rem 0.5rem" onclick="quickAiPrompt('Analisis performa platform secara keseluruhan')"><i data-lucide="activity" style="width:12px;height:12px"></i> Kinerja Platform</button>
            <button class="btn btn-sm btn-outline" style="font-size:0.75rem;white-space:nowrap;padding:0.2rem 0.5rem" onclick="quickAiPrompt('Periksa potensi transaksi bot atau anomali')"><i data-lucide="shield-alert" style="width:12px;height:12px"></i> Deteksi Fraud</button>
        `;
    } else {
        return `
            <button class="btn btn-sm btn-outline" style="font-size:0.75rem;white-space:nowrap;padding:0.2rem 0.5rem" onclick="quickAiPrompt('Event apa saja yang ada?')"><i data-lucide="calendar-days" style="width:12px;height:12px"></i> Daftar Event</button>
            <button class="btn btn-sm btn-outline" style="font-size:0.75rem;white-space:nowrap;padding:0.2rem 0.5rem" onclick="quickAiPrompt('Rekomendasi konser terbaik')"><i data-lucide="sparkles" style="width:12px;height:12px"></i> Rekomendasi</button>
            <button class="btn btn-sm btn-outline" style="font-size:0.75rem;white-space:nowrap;padding:0.2rem 0.5rem" onclick="quickAiPrompt('Berapa harga tiketnya?')"><i data-lucide="banknote" style="width:12px;height:12px"></i> Harga Tiket</button>
            <button class="btn btn-sm btn-outline" style="font-size:0.75rem;white-space:nowrap;padding:0.2rem 0.5rem" onclick="quickAiPrompt('Gimana cara beli tiket?')"><i data-lucide="shopping-cart" style="width:12px;height:12px"></i> Cara Beli</button>
            <button class="btn btn-sm btn-outline" style="font-size:0.75rem;white-space:nowrap;padding:0.2rem 0.5rem" onclick="quickAiPrompt('Gimana cara check-in di gate?')"><i data-lucide="scan-line" style="width:12px;height:12px"></i> Check-in</button>
        `;
    }
}

window.toggleAiWindow = () => {
    const w = document.getElementById('ai-window');
    if (!w) return;
    const isHidden = w.style.display === 'none' || !w.style.display;
    w.style.display = isHidden ? 'flex' : 'none';
    if (isHidden) {
        const inp = document.getElementById('ai-input');
        if (inp) inp.focus();
    }
};

window.openGlobalAiChat = async () => {
    const w = document.getElementById('ai-window');
    if (w) {
        w.style.display = 'flex';
        w.style.opacity = '1';
        w.style.pointerEvents = 'all';
        if (!window.aiChatLog.length) {
            await sendAiMsg('Rekomendasi konser terbaik saat ini');
        }
        const inp = document.getElementById('ai-input');
        if (inp) inp.focus();
    }
};

window.quickAiPrompt = (txt) => {
    const i = document.getElementById('ai-input');
    if (i) {
        i.value = txt;
        sendAiMsg();
    }
};

window.clearAiChat = () => {
    window.aiChatLog = [];
    const c = document.getElementById('ai-chat');
    if (c) c.innerHTML = aiGreetingHtml();
};

window.sendAiMsg = async (preset) => {
    const i = document.getElementById('ai-input');
    const t = (typeof preset === 'string' && preset.trim()) ? preset.trim() : (i ? i.value.trim() : '');
    if (!t) return;
    if (i) i.value = '';

    const c = document.getElementById('ai-chat');
    if (!c) return;
    const userHtml = escapeHtml(t);
    c.innerHTML += `<div class="ai-msg user">${userHtml}</div>`;
    window.aiChatLog.push({ who: 'user', html: userHtml });
    const tid = 't' + Date.now();
    c.innerHTML += `<div id="${tid}" class="ai-msg bot" style="opacity:.6;font-style:italic">Bot Assistant sedang menyiapkan jawaban...</div>`;
    c.scrollTop = c.scrollHeight;

    const u = Auth.getUser();

    try {
        let endpoint = API + '/chatbot';
        let payload = { message: t };
        const tl = t.toLowerCase();
        // Konteks event: saat widget dibuka di halaman detail event organizer
        const evRoute = (window.location.pathname || '').match(/\/organizer\/events\/(\d+)/);
        const evPayload = evRoute ? { event_id: evRoute[1] } : {};

        // Route smart handling based on role & intent
        if (u && u.role === 'organizer') {
            if (tl.includes('penjualan') || tl.includes('sales')) {
                endpoint = API + '/organizer/ai/analyze-sales';
                payload = evPayload;
            } else if (/(rekomendasi|penetapan|penyesuaian|setting).{0,25}(harga|pricing)|(harga|pricing).{0,25}(rekomendasi|penetapan)/.test(tl)) {
                endpoint = API + '/organizer/ai/ticket-recommendation';
                payload = evPayload;
            } else if (tl.includes('deskripsi') || tl.includes('copywriting') || tl.includes('copy event') || tl.includes('promosi konser')) {
                endpoint = API + '/organizer/ai/generate-description';
                payload = evPayload;
            }
        } else if (u && u.role === 'admin') {
            if (tl.includes('performa') || tl.includes('kinerja') || tl.includes('analisis platform')) {
                endpoint = API + '/admin/ai/analyze-platform';
                payload = {};
            } else if (tl.includes('fraud') || tl.includes('anomali') || (tl.includes('bot') && (tl.includes('deteksi') || tl.includes('periksa') || tl.includes('transaksi') || tl.includes('potensi')))) {
                endpoint = API + '/admin/ai/detect-suspicious';
                payload = {};
            }
        }

        const r = await axios.post(endpoint, payload);
        const el = document.getElementById(tid);
        if (el) el.remove();

        const d = r.data || {};
        let html;
        if (Array.isArray(d.chips) || Array.isArray(d.cards)) {
            html = renderBotReply(d);
        } else {
            html = formatAiMessage(d.ai_message?.message || d.message || d.analysis || d.reply || d.recommendation || d.recommendations || d.trends || d.report || d.description || 'Analisis selesai.');
        }
        c.innerHTML += `<div class="ai-msg bot">${html}</div>`;
        window.aiChatLog.push({ who: 'bot', html });
        c.scrollTop = c.scrollHeight;
    } catch (e) {
        const el = document.getElementById(tid);
        const errMsg = 'Maaf, terjadi kendala saat memproses jawaban: ' + (e.response?.data?.message || 'Koneksi gagal');
        if (el) {
            el.style.opacity = '1';
            el.style.fontStyle = 'normal';
            el.innerText = errMsg;
        }
        window.aiChatLog.push({ who: 'bot', html: escapeHtml(errMsg) });
    }
};

function formatAiMessage(msg) {
    if (!msg) return '';
    return msg
        .replace(/\n/g, '<br>')
        .replace(/\*\*(.*?)\*\*/g, '<b>$1</b>')
        .replace(/### (.*?)(<br>|$)/g, '<h4 style="margin:0.4rem 0">$1</h4>');
}

function escapeHtml(str) {
    return str.replace(/[&<>'"]/g, tag => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        "'": '&#39;',
        '"': '&quot;'
    }[tag] || tag));
}

// Render balasan Bot Assistant: teks + kartu event + chip saran tindak lanjut.
function renderBotReply(d) {
    let html = formatAiMessage(d.reply || '');
    if (Array.isArray(d.cards) && d.cards.length) html += renderAiCards(d.cards);
    if (Array.isArray(d.chips) && d.chips.length) html += renderAiChips(d.chips);
    return html;
}

function renderAiCards(cards) {
    return '<div class="ai-cards">' + cards.map(c => `
        <div class="ai-event-card" onclick="aiOpenEvent(${parseInt(c.id, 10) || 0})" role="button" tabindex="0">
            ${c.image ? `<img src="${escapeHtml(String(c.image))}" alt="" onerror="this.style.display='none'">` : `<div class="ai-event-thumb"><i data-lucide="music" style="width:20px;height:20px"></i></div>`}
            <div class="ai-event-info">
                <b>${escapeHtml(String(c.title || ''))}</b>
                <span><i data-lucide="calendar-days" style="width:12px;height:12px;flex:none"></i> ${escapeHtml(String(c.date_label || ''))}</span>
                ${c.venue ? `<span><i data-lucide="map-pin" style="width:12px;height:12px;flex:none"></i> ${escapeHtml(String(c.venue))}</span>` : ''}
                ${c.price_label ? `<span><i data-lucide="ticket" style="width:12px;height:12px;flex:none"></i> ${escapeHtml(String(c.price_label))}</span>` : ''}
            </div>
            <i data-lucide="chevron-right" style="width:15px;height:15px;flex:none;opacity:.45"></i>
        </div>`).join('') + '</div>';
}

function renderAiChips(chips) {
    return `<div class="ai-chip-row">` + chips.map(q =>
        `<button type="button" class="ai-chip-btn" data-q="${escapeHtml(String(q))}" onclick="quickAiPrompt(this.getAttribute('data-q'))">${escapeHtml(String(q))}</button>`
    ).join('') + `</div>`;
}

window.aiOpenEvent = (id) => {
    if (id) Router.navigate('/customer/events/' + id);
};

document.addEventListener('DOMContentLoaded', Router.resolve);
