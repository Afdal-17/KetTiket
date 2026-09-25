// === ORGANIZER DASHBOARD (FULL CRUD + MAPS + AI + GALLERY IMAGE UPLOAD) ===
const renderOrganizerDashboard = async (tab = 'overview') => {
    const u = Auth.getUser();
    if (!u || u.role !== 'organizer') {
        return Router.navigate('/login');
    }

    UI.mount(`
        <div class="dash-layout">
            <aside class="sidebar">
                <div class="sidebar-brand">Organizer Panel</div>
                <ul class="sidebar-nav">
                    <li><a href="/organizer/dashboard/overview" class="${tab === 'overview' ? 'active' : ''}" onclick="event.preventDefault(); Router.navigate('/organizer/dashboard/overview')"><i data-lucide="gauge"></i> Ikhtisar, Statistik & Laporan</a></li>
                    <li><a href="/organizer/dashboard/events" class="${tab === 'events' ? 'active' : ''}" onclick="event.preventDefault(); Router.navigate('/organizer/dashboard/events')"><i data-lucide="calendar-days"></i> Daftar & Kelola Konser</a></li>
                    <li><a href="/organizer/dashboard/ai" class="${tab === 'ai' ? 'active' : ''}" onclick="event.preventDefault(); Router.navigate('/organizer/dashboard/ai')"><i data-lucide="bot"></i> Organizer AI Assistant</a></li>
                    <li><a href="/organizer/dashboard/scan" class="${tab === 'scan' ? 'active' : ''}" onclick="event.preventDefault(); Router.navigate('/organizer/dashboard/scan')"><i data-lucide="scan-line"></i> Pemindai QR & Tiket Masuk</a></li>
                    <li><a href="/" onclick="event.preventDefault();Router.navigate('/')"><i data-lucide="home"></i> Ke Beranda</a></li>
                </ul>
            </aside>
            <main class="dash-content">
                <div id="org-content">
                    <div class="text-center p-4"><div class="spinner" style="margin:0 auto"></div></div>
                </div>
            </main>
        </div>
    `, true);

    loadOrgTab(tab);
};

Router.add('/organizer/dashboard', () => renderOrganizerDashboard('overview'));
Router.add('/organizer/dashboard/:tab', (tab) => renderOrganizerDashboard(tab));

window.loadOrgTab = async (tab, clickedLink = null) => {
    document.querySelectorAll('.sidebar-nav a').forEach(a => {
        a.classList.remove('active');
    });

    if (clickedLink) {
        clickedLink.classList.add('active');
    }

    const el = document.getElementById('org-content');

    if (!el) {
        console.error('#org-content tidak ditemukan!');
        return;
    }

    el.innerHTML = `
        <div class="text-center p-4">
            <div class="spinner" style="margin:0 auto"></div>
        </div>
    `;
    if (tab === 'overview') {
        try {
            const r = await axios.get(API + '/organizer/reports');
            const summary = r.data.summary || {};
            const reps = r.data.report || [];
            let totalRev = summary.total_revenue || 0;
            let totalTickets = summary.total_tickets_sold || 0;
            let totalOrders = summary.total_orders || 0;

            let reportRows = '';
            reps.forEach(rp => {
                let breakdownHtml = '';
                if (rp.ticket_breakdown && rp.ticket_breakdown.length) {
                    breakdownHtml = rp.ticket_breakdown.map(tb =>
                        `<span class="badge badge-purple" style="font-size:0.75rem;margin-right:4px">${escapeHtmlDash(tb.name)}: ${tb.sold_count}/${tb.quota} (Rp ${Number(tb.category_revenue || 0).toLocaleString('id-ID')})</span>`
                    ).join(' ');
                }

                let activitiesHtml = '';
                if (rp.recent_activities && rp.recent_activities.length) {
                    activitiesHtml = rp.recent_activities.map(act =>
                        `<li style="font-size:0.85rem;margin-bottom:4px;color:rgba(255,255,255,0.8)">
                            <b>${escapeHtmlDash(act.customer_name)}</b> membeli tiket senilai <b>Rp ${Number(act.total).toLocaleString('id-ID')}</b> via ${escapeHtmlDash(act.payment_method)} <span class="text-muted">(${new Date(act.created_at).toLocaleString('id-ID')})</span>
                        </li>`
                    ).join('');
                }

                reportRows += `
                    <tr>
                        <td>
                            <b>${escapeHtmlDash(rp.title)}</b><br>
                            <small class="text-muted">${new Date(rp.start_date).toLocaleDateString('id-ID')}</small>
                        </td>
                        <td>${escapeHtmlDash(rp.venue)}</td>
                        <td><span class="badge ${rp.status === 'published' ? 'badge-success' : 'badge-warning'}">${rp.status}</span></td>
                        <td>
                            <b>${rp.orders_count} Order</b><br>
                            <small class="text-muted">${rp.tickets_sold} tiket terjual</small>
                        </td>
                        <td><b>Rp ${(rp.total_revenue || 0).toLocaleString('id-ID')}</b></td>
                        <td>
                            <details>
                                <summary style="cursor:pointer;color:var(--primary);font-size:0.85rem">Rincian Kategori & Aktivitas</summary>
                                <div class="mt-2 p-2" style="background:rgba(0,0,0,0.2);border-radius:6px">
                                    <div class="mb-2"><b>Rincian Kategori Tiket:</b><br>${breakdownHtml || '<span class="text-muted">Tidak ada data</span>'}</div>
                                    <div><b>Log Aktivitas Penjualan Terbaru:</b>
                                        <ul style="padding-left:1rem;margin-top:4px">${activitiesHtml || '<li class="text-muted">Belum ada transaksi</li>'}</ul>
                                    </div>
                                </div>
                            </details>
                        </td>
                    </tr>
                `;
            });

            el.innerHTML = `
                <div class="flex justify-between items-center mb-3">
                    <div>
                        <h2><i data-lucide="chart-column" style="width:23px;height:23px;vertical-align:-4px;color:var(--primary)"></i> Ikhtisar, Statistik & Laporan</h2>
                        <p class="text-muted">Ringkasan cepat performa penjualan tiket, pendapatan bersih, dan laporan penjualan Anda.</p>
                    </div>
                    <button class="btn btn-primary" onclick="loadOrgTab('create')"><i data-lucide="plus" style="width:16px;height:16px"></i> Buat Konser Baru</button>
                </div>

                <div class="stat-grid mt-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background:rgba(16,185,129,.1);color:var(--success)"><i data-lucide="banknote"></i></div>
                        <div>
                            <h4>Total Pendapatan Tiket</h4>
                            <div class="stat-val">Rp ${Number(totalRev).toLocaleString('id-ID')}</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background:rgba(108,60,225,.1);color:var(--primary)"><i data-lucide="ticket"></i></div>
                        <div>
                            <h4>Tiket Terjual</h4>
                            <div class="stat-val">${totalTickets.toLocaleString('id-ID')} Tiket</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background:rgba(59,130,246,.1);color:var(--accent)"><i data-lucide="shopping-cart"></i></div>
                        <div>
                            <h4>Total Transaksi</h4>
                            <div class="stat-val">${totalOrders.toLocaleString('id-ID')} Order</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background:rgba(245,158,11,.1);color:var(--warning)"><i data-lucide="calendar"></i></div>
                        <div>
                            <h4>Total Konser</h4>
                            <div class="stat-val">${reps.length} Event</div>
                        </div>
                    </div>
                </div>

                <div class="table-wrap mt-4">
                    <table>
                        <thead>
                            <tr><th>Konser & Tanggal</th><th>Venue</th><th>Status</th><th>Pemesanan</th><th>Pendapatan</th><th>Detail & Log Aktivitas</th></tr>
                        </thead>
                        <tbody>${reportRows || '<tr><td colspan="6" class="text-center text-muted p-4">Belum ada aktivitas penjualan tiket.</td></tr>'}</tbody>
                    </table>
                </div>

                <div class="mt-4">
                    <div class="card" style="background:linear-gradient(135deg,rgba(108,60,225,0.08),rgba(59,130,246,0.08));border:1px solid var(--primary)">
                        <h3><i data-lucide="bot" style="width:20px;height:20px;vertical-align:-4px;color:var(--primary)"></i> Organizer AI Assistant</h3>
                        <p class="text-muted mt-1">Gunakan kecerdasan buatan untuk mengoptimalkan harga tiket, membuat deskripsi event, dan analisis pasar.</p>
                        <button class="btn btn-accent mt-3" onclick="loadOrgTab('ai')"><i data-lucide="sparkles" style="width:16px;height:16px"></i> Buka AI Intelligence Tools</button>
                    </div>
                </div>
            `;
        } catch (e) {
            el.innerHTML = '<p class="text-danger">Gagal memuat ikhtisar organizer.</p>';
        }
    } else if (tab === 'events') {
        try {
            const r = await axios.get(API + '/organizer/events');
            const evs = r.data.events || [];
            let rows = '';

            evs.sort((firstEvent, secondEvent) => Number(firstEvent.id) - Number(secondEvent.id));

            evs.forEach((e, index) => {
                const img = e.image || '/images/sheila_on_7.jpeg';
                const mapLink = e.google_maps_link || e.venue?.google_maps_link;

                let ticketInfo = '';
                if (e.ticket_types && e.ticket_types.length) {
                    ticketInfo = `<details class="mt-2"><summary style="cursor:pointer;color:var(--primary);font-size:0.85rem">Detail Tiket</summary><div class="mt-1 p-2" style="background:rgba(0,0,0,0.2);border-radius:6px;font-size:0.8rem">` +
                        e.ticket_types.map(t => `<div>${escapeHtmlDash(t.name)} - Rp ${Number(t.price).toLocaleString('id-ID')} (Sisa: ${t.quota})</div>`).join('') +
                        `</div></details>`;
                } else {
                    ticketInfo = `<div class="mt-1 text-muted" style="font-size:0.8rem">Belum ada tiket</div>`;
                }

                rows += `
                    <tr>
                        <td>${index + 1}</td>
                        <td><img src="${img}" style="width:48px;height:48px;object-fit:cover;border-radius:6px" alt="poster"></td>
                        <td>
                            <b>${escapeHtmlDash(e.title)}</b><br>
                            ${mapLink ? `<small><a href="${mapLink}" target="_blank" style="color:var(--primary)">Google Maps</a></small>` : ''}
                            ${ticketInfo}
                        </td>
                        <td>${escapeHtmlDash(e.venue?.name || '-')}</td>
                        <td>${new Date(e.start_date).toLocaleDateString('id-ID')}</td>
                        <td><span class="badge ${e.status === 'published' ? 'badge-success' : e.status === 'draft' ? 'badge-warning' : 'badge-danger'}">${e.status}</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline mb-1 w-100" onclick="showEditModal(${e.id})"><i data-lucide="pencil" style="width:14px;height:14px"></i> Edit</button>
                            <button class="btn btn-sm btn-outline mb-1 w-100" onclick="Router.navigate('/organizer/events/${e.id}')"><i data-lucide="ticket" style="width:14px;height:14px"></i> Kelola Tiket</button>
                            <button class="btn btn-sm btn-danger w-100" onclick="deleteEvent(${e.id})"><i data-lucide="trash-2" style="width:14px;height:14px"></i> Hapus</button>
                        </td>
                    </tr>
                `;
            });

            el.innerHTML = `
                <div class="flex justify-between items-center mb-3">
                    <div>
                        <h2><i data-lucide="calendar-days" style="width:23px;height:23px;vertical-align:-4px;color:var(--primary)"></i> Daftar & Kelola Konser</h2>
                        <p class="text-muted">Kelola konser Anda dengan fitur lengkap: Edit, Hapus, Atur Tiket, dan Peta Lokasi.</p>
                    </div>
                    <button class="btn btn-primary" onclick="loadOrgTab('create')"><i data-lucide="plus" style="width:16px;height:16px"></i> Buat Konser Baru</button>
                </div>
                <div id="edit-modal-area"></div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr><th>No.</th><th>Poster</th><th>Judul Konser & Peta</th><th>Venue</th><th>Jadwal</th><th>Status</th><th>Aksi CRUD</th></tr>
                        </thead>
                        <tbody>${rows || '<tr><td colspan="7" class="text-center text-muted p-4">Belum ada konser. Silakan buat konser pertama Anda!</td></tr>'}</tbody>
                    </table>
                </div>
            `;
        } catch (e) {
            el.innerHTML = '<p class="text-danger">Gagal memuat daftar konser.</p>';
        }
    } else if (tab === 'scan') {
        el.innerHTML = `
            <h2><i data-lucide="scan-line" style="width:23px;height:23px;vertical-align:-4px;color:var(--primary)"></i> Pemindai Tiket (QR Code & Barcode Scanner)</h2>
            <p class="text-muted">Validasi keaslian tiket pengunjung saat registrasi di pintu masuk venue konser.</p>
            <div class="card" style="max-width:600px">
                <div class="form-group">
                    <label class="form-label"><i data-lucide="qr-code" style="width:15px;height:15px;vertical-align:-3px"></i> Masukkan Kode Barcode / Kode QR Tiket</label>
                    <input id="org-scan-code" class="form-control" placeholder="Scan barcode atau ketik kode tiket..." style="font-size:1.2rem;text-align:center;letter-spacing:2px" autofocus>
                </div>
                <button class="btn btn-primary w-100 btn-lg" onclick="doOrgScan()"><i data-lucide="shield-check" style="width:17px;height:17px"></i> Validasi Tiket Masuk</button>
            </div>
            <div id="org-scan-result" class="mt-3" style="max-width:700px"></div>
        `;

        window.doOrgScan = async () => {
            const code = document.getElementById('org-scan-code').value.trim();
            if (!code) {
                UI.toast('Silakan masukkan kode tiket terlebih dahulu.', 'warning');
                return;
            }
            UI.showLoader();
            try {
                const r = await axios.post(API + '/tickets/scan', { code });
                const t = r.data.ticket;
                UI.toast(r.data.message, 'success');
                document.getElementById('org-scan-result').innerHTML = `
                    <div class="scan-result approved card p-4" style="border:3px solid var(--success);background:rgba(16,185,129,0.05);text-align:center">
                        <div class="scan-status-icon success sm"><i data-lucide="circle-check"></i></div>
                        <div class="badge badge-success mb-2" style="font-size:.95rem;padding:6px 16px">TIKET SAH</div>
                        <h3 style="color:var(--success);margin:0">${escapeHtmlDash(r.data.message)}</h3>
                        ${renderScanCustomerCard(t?.order_detail?.order?.user)}
                        ${renderScanTicketDetails(t, {
                            scannedAt: r.data.scanned_at || new Date().toISOString(),
                            scannerName: r.data.scanner_name || Auth.getUser()?.name
                        })}
                    </div>
                `;
                document.getElementById('org-scan-code').value = '';
            } catch (err) {
                const data = err.response?.data || {};
                UI.toast(data.message || 'Tiket Tidak Valid', 'danger');
                const t = data.ticket;
                document.getElementById('org-scan-result').innerHTML = `
                    <div class="scan-result rejected card p-4" style="border:3px solid var(--danger);background:rgba(239,68,68,0.05);text-align:center">
                        <div class="scan-status-icon danger sm"><i data-lucide="circle-x"></i></div>
                        <div class="badge badge-danger mb-2" style="font-size:.95rem;padding:6px 16px">DITOLAK</div>
                        <h3 style="color:var(--danger);margin:0">${escapeHtmlDash(data.message || 'Tiket Tidak Valid')}</h3>
                        ${t ? `${renderScanCustomerCard(t?.order_detail?.order?.user)}${renderScanTicketDetails(t, {})}` : ''}
                    </div>
                `;
            }
            UI.hideLoader();
        };
    } else if (tab === 'create') {
        try {
            const vr = await axios.get(API + '/venues');
            const vs = vr.data.venues || [];
            let opts = '<option value="">-- Pilih Venue Konser --</option>';
            vs.forEach(v => {
                opts += `<option value="${v.id}" data-lat="${v.latitude || ''}" data-lng="${v.longitude || ''}" data-map="${v.google_maps_link || ''}">${v.name} (${v.city || 'Kapasitas: ' + v.capacity})</option>`;
            });

            el.innerHTML = `
                <h2><i data-lucide="plus-circle" style="width:23px;height:23px;vertical-align:-4px;color:var(--primary)"></i> Buat Konser Baru</h2>
                <p class="text-muted">Lengkapi detail event, ambil gambar poster dari galeri, dan integrasi Google Maps venue.</p>
                <div class="card" style="max-width:700px">
                    <form id="create-ev-form">
                        <div class="form-group">
                            <label class="form-label"><i data-lucide="music" style="width:14px;height:14px;vertical-align:-2px"></i> Judul Konser Musik</label>
                            <input id="ev-title" class="form-control" placeholder="Contoh: Sheila On 7 Live in Concert 2026" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i data-lucide="map-pin" style="width:14px;height:14px;vertical-align:-2px"></i> Lokasi / Venue Konser</label>
                            <input id="ev-location-name" class="form-control" placeholder="Contoh: Cianjur, Kopi Kenangan" required>
                            <small class="text-muted">Ketik lokasi bebas. Koordinat GPS dan URL Google Maps akan otomatis terisi.</small>
                        </div>
                        <div class="form-group" style="padding:1rem;border:1px solid var(--border);border-radius:8px">
                            <label style="display:flex;gap:.6rem;align-items:center;cursor:pointer">
                                <input id="ev-has-seating" type="checkbox" checked onchange="document.getElementById('seating-builder-box').style.display = this.checked ? 'block' : 'none'">
                                <span><b>Event memiliki tempat duduk (Seated)</b><br><small class="text-muted">Aktifkan untuk menambahkan kursi manual sesuai venue (misal F5 kuota 100, A5 kuota 50).</small></span>
                            </label>
                        </div>
                        <div id="seating-builder-box" class="form-group mt-3" style="border:1px solid var(--border);padding:1rem;border-radius:8px;background:rgba(255,255,255,0.02)">
                            <div class="flex justify-between items-center mb-2">
                                <label class="form-label" style="margin:0">Tambah Kursi & Kuota per Section (e.g. F5, A5)</label>
                                <button type="button" class="btn btn-sm btn-outline" onclick="addSeatSectionRow()"><i data-lucide="plus" style="width:14px;height:14px"></i> Tambah Section</button>
                            </div>
                            <div id="seat-section-rows">
                                <div class="seat-sec-item flex gap-2 mb-2">
                                    <input class="form-control sec-name" placeholder="Nama Section / Kursi (e.g. F5)" required>
                                    <input type="number" class="form-control sec-quota" placeholder="Kuota (e.g. 100)" style="width:140px" required>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()"><i data-lucide="trash-2" style="width:14px;height:14px"></i> Hapus</button>
                                </div>
                            </div>
                        </div>
                        <div id="venue-map-preview" class="mb-3" style="display:none">
                            <label class="form-label"><i data-lucide="map-pin" style="width:14px;height:14px;vertical-align:-2px"></i> Tinjauan Lokasi Google Maps</label>
                            <div id="venue-map-frame" style="width:100%;height:200px;border-radius:var(--radius);overflow:hidden;border:1px solid var(--border)"></div>
                        </div>
                        <div class="grid-half">
                            <div class="form-group">
                                <label class="form-label"><i data-lucide="locate" style="width:14px;height:14px;vertical-align:-2px"></i> Latitude GPS (Opsional)</label>
                                <input id="ev-lat" class="form-control" placeholder="-6.2185">
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i data-lucide="locate" style="width:14px;height:14px;vertical-align:-2px"></i> Longitude GPS (Opsional)</label>
                                <input id="ev-lng" class="form-control" placeholder="106.8017">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i data-lucide="external-link" style="width:14px;height:14px;vertical-align:-2px"></i> Link Google Maps (URL Tautan)</label>
                            <input id="ev-map" class="form-control" placeholder="https://maps.google.com/?q=...">
                        </div>
                        
                        <!-- Poster Image from Gallery / File / Preset -->
                        <div class="form-group">
                            <label class="form-label"><i data-lucide="image" style="width:14px;height:14px;vertical-align:-2px"></i> Poster Konser (Pilih dari Galeri atau Upload File)</label>
                            <div style="display:flex;gap:0.5rem;margin-bottom:0.5rem;flex-wrap:wrap">
                                <button type="button" class="btn btn-sm btn-outline" onclick="selectGalleryPreset('/images/sheila_on_7.jpeg')"><i data-lucide="image" style="width:13px;height:13px"></i> Sheila On 7</button>
                                <button type="button" class="btn btn-sm btn-outline" onclick="selectGalleryPreset('/images/perunggu.jpeg')"><i data-lucide="image" style="width:13px;height:13px"></i> Perunggu</button>
                                <button type="button" class="btn btn-sm btn-outline" onclick="selectGalleryPreset('/images/opick.jpg')"><i data-lucide="image" style="width:13px;height:13px"></i> Opick</button>
                                <button type="button" class="btn btn-sm btn-outline" onclick="selectGalleryPreset('/images/for%20revenge.jpg')"><i data-lucide="image" style="width:13px;height:13px"></i> For Revenge</button>
                            </div>
                            <div style="display:flex;gap:0.5rem;align-items:center">
                                <input type="file" id="ev-file-upload" class="form-control" accept="image/*" onchange="handleFileGalleryUpload(this)">
                                <span class="text-muted" style="font-size:0.85rem">atau</span>
                            </div>
                            <input id="ev-image" class="form-control mt-2" placeholder="/images/sheila_on_7.jpeg atau URL gambar poster" required>
                            <div id="poster-preview-box" class="mt-2" style="display:none">
                                <img id="poster-preview-img" src="" style="width:100px;height:100px;object-fit:cover;border-radius:6px;border:1px solid var(--border)" alt="Preview">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i data-lucide="file-text" style="width:14px;height:14px;vertical-align:-2px"></i> Deskripsi Lengkap Konser</label>
                            <div class="flex justify-between items-center mb-1">
                                <small class="text-muted">Jelaskan pengalaman konser musik ini</small>
                                <button type="button" class="btn btn-sm btn-outline" style="font-size:0.75rem" onclick="autoGenerateDesc()"><i data-lucide="sparkles" style="width:13px;height:13px"></i> Buat Otomatis via AI</button>
                            </div>
                            <textarea id="ev-desc" class="form-control" rows="4" placeholder="Deskripsi lengkap konser musik..." required></textarea>
                        </div>
                        <div class="grid-half">
                            <div class="form-group">
                                <label class="form-label"><i data-lucide="clock" style="width:14px;height:14px;vertical-align:-2px"></i> Waktu Mulai</label>
                                <input id="ev-start" type="datetime-local" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i data-lucide="timer" style="width:14px;height:14px;vertical-align:-2px"></i> Waktu Selesai</label>
                                <input id="ev-end" type="datetime-local" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i data-lucide="clipboard-check" style="width:14px;height:14px;vertical-align:-2px"></i> Status Event</label>
                            <select id="ev-status" class="form-control">
                                <option value="published">Published (Langsung Tayang di Beranda)</option>
                                <option value="draft">Draft (Simpan Sementara)</option>
                            </select>
                        </div>

                        <!-- Ticket Categories & Prices Input -->
                        <div class="form-group mt-3" style="border:1px solid var(--border);padding:1rem;border-radius:8px;background:rgba(255,255,255,0.02)">
                            <div class="flex justify-between items-center mb-2">
                                <label class="form-label" style="margin:0">Kategori & Harga Tiket Konser</label>
                                <button type="button" class="btn btn-sm btn-outline" onclick="addEvTicketRow()"><i data-lucide="plus" style="width:14px;height:14px"></i> Tambah Kategori Tiket</button>
                            </div>
                            <div id="ev-ticket-rows">
                                <div class="ev-ticket-item flex gap-2 mb-2">
                                    <input class="form-control ev-tt-name" placeholder="Nama Kategori (VIP / Festival A)" required>
                                    <input class="form-control ev-tt-price" placeholder="Harga (Rp) e.g. 500.000" oninput="formatPriceInputDash(this)" required>
                                    <input type="number" class="form-control ev-tt-quota" placeholder="Kuota" style="width:110px" required>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()"><i data-lucide="trash-2" style="width:14px;height:14px"></i> Hapus</button>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg mt-3 w-100"><i data-lucide="save" style="width:17px;height:17px"></i> Simpan & Terbitkan Konser</button>
                    </form>
                </div>
            `;

            window.addEvTicketRow = () => {
                const container = document.getElementById('ev-ticket-rows');
                const div = document.createElement('div');
                div.className = 'ev-ticket-item flex gap-2 mb-2';
                div.innerHTML = `
                    <input class="form-control ev-tt-name" placeholder="Nama Kategori (VIP / Festival B)" required>
                    <input class="form-control ev-tt-price" placeholder="Harga (Rp) e.g. 750.000" oninput="formatPriceInputDash(this)" required>
                    <input type="number" class="form-control ev-tt-quota" placeholder="Kuota" style="width:110px" required>
                    <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()" title="Hapus baris"><i data-lucide="trash-2" style="width:15px;height:15px"></i></button>
                `;
                container.appendChild(div);
            };

            window.selectGalleryPreset = (path) => {
                document.getElementById('ev-image').value = path;
                const box = document.getElementById('poster-preview-box');
                const img = document.getElementById('poster-preview-img');
                box.style.display = 'block';
                img.src = path;
            };

            window.handleFileGalleryUpload = (input) => {
                if (input.files && input.files[0]) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        const base64 = e.target.result;
                        document.getElementById('ev-image').value = base64;
                        const box = document.getElementById('poster-preview-box');
                        const img = document.getElementById('poster-preview-img');
                        box.style.display = 'block';
                        img.src = base64;
                    };
                    reader.readAsDataURL(input.files[0]);
                }
            };

            window.onVenueChange = (sel) => {
                const opt = sel.options[sel.selectedIndex];
                const lat = opt.getAttribute('data-lat');
                const lng = opt.getAttribute('data-lng');
                const map = opt.getAttribute('data-map');
                if (lat) document.getElementById('ev-lat').value = lat;
                if (lng) document.getElementById('ev-lng').value = lng;
                if (map) document.getElementById('ev-map').value = map;

                const prev = document.getElementById('venue-map-preview');
                const frame = document.getElementById('venue-map-frame');
                if (lat && lng) {
                    prev.style.display = 'block';
                    frame.innerHTML = `<iframe src="https://maps.google.com/maps?q=${lat},${lng}&z=15&output=embed" width="100%" height="100%" style="border:0"></iframe>`;
                } else {
                    prev.style.display = 'none';
                }
            };

            window.addSeatSectionRow = () => {
                const container = document.getElementById('seat-section-rows');
                const div = document.createElement('div');
                div.className = 'seat-sec-item flex gap-2 mb-2';
                div.innerHTML = `
                    <input class="form-control sec-name" placeholder="Nama Section / Kursi (e.g. A5)" required>
                    <input type="number" class="form-control sec-quota" placeholder="Kuota (e.g. 50)" style="width:140px" required>
                    <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()" title="Hapus baris"><i data-lucide="trash-2" style="width:15px;height:15px"></i></button>
                `;
                container.appendChild(div);
            };

            document.getElementById('ev-location-name').addEventListener('input', (event) => {
                const location = event.target.value.trim();
                const preview = document.getElementById('venue-map-preview');
                const frame = document.getElementById('venue-map-frame');
                if (location) {
                    preview.style.display = 'block';
                    frame.innerHTML = `<iframe src="https://www.google.com/maps?q=${encodeURIComponent(location)}&output=embed" width="100%" height="100%" style="border:0"></iframe>`;
                    document.getElementById('ev-map').value = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(location);
                } else {
                    preview.style.display = 'none';
                    frame.innerHTML = '';
                    document.getElementById('ev-map').value = '';
                }
            });

            window.autoGenerateDesc = async () => {
                const title = document.getElementById('ev-title').value;
                if (!title) return alert('Silakan isi judul konser terlebih dahulu!');
                UI.showLoader();
                try {
                    const r = await axios.post(API + '/organizer/ai/generate-description', { title, genre: 'Pop/Rock' });
                    document.getElementById('ev-desc').value = r.data.description || '';
                    alert('Deskripsi berhasil dibuat otomatis oleh AI!');
                } catch (e) {
                    alert('Gagal membuat deskripsi: ' + (e.response?.data?.message || 'Error'));
                }
                UI.hideLoader();
            };

            document.getElementById('create-ev-form').addEventListener('submit', async e => {
                e.preventDefault();
                UI.showLoader();

                const ticketRows = document.querySelectorAll('#ev-ticket-rows .ev-ticket-item');
                const ticketTypes = [];
                ticketRows.forEach(row => {
                    const name = row.querySelector('.ev-tt-name').value.trim();
                    const rawPrice = row.querySelector('.ev-tt-price').value.replace(/\./g, '').replace(/,/g, '');
                    const quota = parseInt(row.querySelector('.ev-tt-quota').value);
                    if (name && rawPrice && quota) {
                        ticketTypes.push({ name, price: parseFloat(rawPrice), quota });
                    }
                });

                const seatRows = document.querySelectorAll('#seat-section-rows .seat-sec-item');
                const seatSections = [];
                seatRows.forEach(row => {
                    const section = row.querySelector('.sec-name').value.trim();
                    const quota = parseInt(row.querySelector('.sec-quota').value);
                    if (section && quota) {
                        seatSections.push({ section, quota });
                    }
                });

                try {
                    await axios.post(API + '/events', {
                        location_name: document.getElementById('ev-location-name').value.trim(),
                        has_seating: document.getElementById('ev-has-seating').checked,
                        seat_sections: seatSections,
                        title: document.getElementById('ev-title').value,
                        image: document.getElementById('ev-image').value,
                        description: document.getElementById('ev-desc').value,
                        latitude: document.getElementById('ev-lat').value || null,
                        longitude: document.getElementById('ev-lng').value || null,
                        google_maps_link: document.getElementById('ev-map').value || null,
                        start_date: document.getElementById('ev-start').value,
                        end_date: document.getElementById('ev-end').value,
                        status: document.getElementById('ev-status').value,
                        ticket_types: ticketTypes
                    });
                    alert('Konser berhasil dibuat dengan lokasi bebas dan kuota kursi manual!');
                    Router.navigate('/organizer/dashboard/events');
                } catch (err) {
                    alert(err.response?.data?.message || 'Gagal membuat konser');
                }
                UI.hideLoader();
            });
        } catch (e) {
            el.innerHTML = '<p class="text-danger">Gagal memuat venue konser.</p>';
        }
    } else if (tab === 'ai') {
        el.innerHTML = `
            <h2><i data-lucide="sparkles" style="width:23px;height:23px;vertical-align:-4px;color:var(--primary)"></i> Organizer AI Intelligence Tools</h2>
            <p class="text-muted">Alat bantu kecerdasan buatan untuk memaksimalkan strategi penjualan tiket konser Anda.</p>
            <div class="grid-2 mt-3">
                <div class="card">
                    <h3><i data-lucide="chart-column" style="width:18px;height:18px;vertical-align:-4px;color:var(--primary)"></i> Analisis Penjualan & Performa Tiket</h3>
                    <p class="text-muted">Tinjau kecepatan penjualan tiket, perbandingan per kategori, dan prediksi pencapaian kuota.</p>
                    <button class="btn btn-primary mt-2" onclick="runOrgAi('sales')"><i data-lucide="chart-column" style="width:16px;height:16px"></i> Jalankan Analisis Sales</button>
                </div>
                <div class="card">
                    <h3><i data-lucide="banknote" style="width:18px;height:18px;vertical-align:-4px;color:var(--primary)"></i> Rekomendasi Penetapan Harga Tiket</h3>
                    <p class="text-muted">AI menganalisis tren pasar musik untuk memberikan rekomendasi harga tiket optimal (Dynamic Pricing).</p>
                    <button class="btn btn-primary mt-2" onclick="runOrgAi('pricing')"><i data-lucide="banknote" style="width:16px;height:16px"></i> Dapatkan Saran Harga</button>
                </div>
                <div class="card">
                    <h3><i data-lucide="activity" style="width:18px;height:18px;vertical-align:-4px;color:var(--primary)"></i> Analisis Tren & Demografi Pembeli</h3>
                    <p class="text-muted">Pahami profil customer dan waktu paling aktif terjadinya pemesanan tiket.</p>
                    <button class="btn btn-primary mt-2" onclick="runOrgAi('trends')"><i data-lucide="activity" style="width:16px;height:16px"></i> Analisis Perilaku Pembeli</button>
                </div>
                <div class="card">
                    <h3><i data-lucide="file-text" style="width:18px;height:18px;vertical-align:-4px;color:var(--primary)"></i> Buat Laporan Eksekutif Konser</h3>
                    <p class="text-muted">Buat rangkuman naratif laporan performa acara secara profesional dan otomatis.</p>
                    <button class="btn btn-primary mt-2" onclick="runOrgAi('report')"><i data-lucide="file-text" style="width:16px;height:16px"></i> Generate Laporan Event</button>
                </div>
            </div>
            <div id="org-ai-result" class="card mt-4" style="display:none">
                <h3 id="org-ai-title"><i data-lucide="sparkles" style="width:18px;height:18px;vertical-align:-4px;color:var(--primary)"></i> Hasil Analisis AI</h3>
                <div id="org-ai-body" class="mt-2" style="line-height:1.6"></div>
            </div>
        `;
    }
};

window.runOrgAi = async (type) => {
    const resBox = document.getElementById('org-ai-result');
    const resBody = document.getElementById('org-ai-body');
    const resTitle = document.getElementById('org-ai-title');
    resBox.style.display = 'block';
    resBody.innerHTML = '<div class="spinner" style="margin:1rem auto"></div><p class="text-center text-muted">AI sedang menganalisis data event Anda...</p>';

    let endpoint = API + '/organizer/ai/analyze-sales';
    if (type === 'pricing') endpoint = API + '/organizer/ai/ticket-recommendation';
    else if (type === 'trends') endpoint = API + '/organizer/ai/analyze-trends';
    else if (type === 'report') endpoint = API + '/organizer/ai/generate-report';

    resTitle.innerHTML = '<i data-lucide="sparkles" style="width:18px;height:18px;vertical-align:-4px;color:var(--primary)"></i> ' + (type === 'pricing' ? 'Rekomendasi Penetapan Harga Tiket' : type === 'trends' ? 'Analisis Tren Pembeli' : type === 'report' ? 'Laporan Eksekutif Event' : 'Analisis Penjualan Tiket');

    try {
        const r = await axios.post(endpoint, {});
        const output = r.data.analysis || r.data.report || r.data.recommendation || r.data.recommendations || r.data.trends || r.data.description || r.data.message || 'Analisis selesai';
        resBody.innerHTML = formatAiMessage(output);
    } catch (e) {
        resBody.innerHTML = '<p class="text-danger">Gagal: ' + (e.response?.data?.message || 'Error AI') + '</p>';
    }
};

// === EDIT EVENT MODAL (CRUD EDIT + GOOGLE MAPS + GALLERY IMAGE UPLOAD) ===
window.showEditModal = async (id) => {
    try {
        UI.showLoader();
        const [evRes, vRes] = await Promise.all([
            axios.get(API + '/events/' + id),
            axios.get(API + '/venues')
        ]);
        UI.hideLoader();

        const ev = evRes.data.event;
        const vs = vRes.data.venues || [];

        let opts = '';
        vs.forEach(v => {
            opts += `<option value="${v.id}" ${v.id === ev.venue_id ? 'selected' : ''} data-lat="${v.latitude || ''}" data-lng="${v.longitude || ''}" data-map="${v.google_maps_link || ''}">${v.name} (${v.city || 'Kapasitas: ' + v.capacity})</option>`;
        });

        const startFormatted = ev.start_date ? new Date(ev.start_date).toISOString().slice(0, 16) : '';
        const endFormatted = ev.end_date ? new Date(ev.end_date).toISOString().slice(0, 16) : '';

        const curLat = ev.latitude || ev.venue?.latitude || '';
        const curLng = ev.longitude || ev.venue?.longitude || '';
        const curMap = ev.google_maps_link || ev.venue?.google_maps_link || '';

        document.getElementById('edit-modal-area').innerHTML = `
            <div class="card mb-4" style="max-width:700px;border:2px solid var(--primary);box-shadow:var(--shadow-lg)">
                <div class="flex justify-between items-center mb-3">
                    <h3 style="margin:0"><i data-lucide="pencil" style="width:19px;height:19px;vertical-align:-4px;color:var(--primary)"></i> Edit Event Konser (ID: ${ev.id})</h3>
                    <button type="button" class="btn btn-sm btn-outline" onclick="document.getElementById('edit-modal-area').innerHTML=''"><i data-lucide="circle-x" style="width:14px;height:14px"></i> Tutup</button>
                </div>
                <form id="edit-ev-form">
                    <div class="form-group">
                        <label class="form-label"><i data-lucide="music" style="width:14px;height:14px;vertical-align:-2px"></i> Judul Event Konser</label>
                        <input id="edit-ev-title" class="form-control" value="${escapeHtmlDash(ev.title || '')}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i data-lucide="map-pin" style="width:14px;height:14px;vertical-align:-2px"></i> Venue Konser</label>
                        <select id="edit-ev-venue" class="form-control" required onchange="onEditVenueChange(this)">${opts}</select>
                    </div>
                    <div class="grid-half">
                        <div class="form-group">
                            <label class="form-label"><i data-lucide="locate" style="width:14px;height:14px;vertical-align:-2px"></i> Latitude GPS (Google Maps)</label>
                            <input id="edit-ev-lat" class="form-control" value="${curLat}">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i data-lucide="locate" style="width:14px;height:14px;vertical-align:-2px"></i> Longitude GPS (Google Maps)</label>
                            <input id="edit-ev-lng" class="form-control" value="${curLng}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i data-lucide="external-link" style="width:14px;height:14px;vertical-align:-2px"></i> Link Google Maps Lokasi Konser</label>
                        <input id="edit-ev-map" class="form-control" value="${escapeHtmlDash(curMap)}" placeholder="https://maps.google.com/?q=...">
                    </div>

                    <!-- Poster Image Gallery / Upload Edit -->
                    <div class="form-group">
                        <label class="form-label"><i data-lucide="image" style="width:14px;height:14px;vertical-align:-2px"></i> Poster Konser (Pilih dari Galeri atau Upload File)</label>
                        <div style="display:flex;gap:0.5rem;margin-bottom:0.5rem;flex-wrap:wrap">
                            <button type="button" class="btn btn-sm btn-outline" onclick="selectEditGalleryPreset('/images/sheila_on_7.jpeg')"><i data-lucide="image" style="width:13px;height:13px"></i> Sheila On 7</button>
                            <button type="button" class="btn btn-sm btn-outline" onclick="selectEditGalleryPreset('/images/perunggu.jpeg')"><i data-lucide="image" style="width:13px;height:13px"></i> Perunggu</button>
                            <button type="button" class="btn btn-sm btn-outline" onclick="selectEditGalleryPreset('/images/opick.jpg')"><i data-lucide="image" style="width:13px;height:13px"></i> Opick</button>
                            <button type="button" class="btn btn-sm btn-outline" onclick="selectEditGalleryPreset('/images/for%20revenge.jpg')"><i data-lucide="image" style="width:13px;height:13px"></i> For Revenge</button>
                        </div>
                        <input type="file" id="edit-ev-file-upload" class="form-control mb-2" accept="image/*" onchange="handleEditFileGalleryUpload(this)">
                        <input id="edit-ev-image" class="form-control" value="${escapeHtmlDash(ev.image || '')}">
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i data-lucide="file-text" style="width:14px;height:14px;vertical-align:-2px"></i> Deskripsi</label>
                        <textarea id="edit-ev-desc" class="form-control" rows="4" required>${escapeHtmlDash(ev.description || '')}</textarea>
                    </div>
                    <div class="grid-half">
                        <div class="form-group">
                            <label class="form-label"><i data-lucide="clock" style="width:14px;height:14px;vertical-align:-2px"></i> Waktu Mulai</label>
                            <input id="edit-ev-start" type="datetime-local" class="form-control" value="${startFormatted}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i data-lucide="timer" style="width:14px;height:14px;vertical-align:-2px"></i> Waktu Selesai</label>
                            <input id="edit-ev-end" type="datetime-local" class="form-control" value="${endFormatted}" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i data-lucide="clipboard-check" style="width:14px;height:14px;vertical-align:-2px"></i> Status Event</label>
                        <select id="edit-ev-status" class="form-control">
                            <option value="draft" ${ev.status === 'draft' ? 'selected' : ''}>Draft</option>
                            <option value="published" ${ev.status === 'published' ? 'selected' : ''}>Published (Tayang)</option>
                            <option value="completed" ${ev.status === 'completed' ? 'selected' : ''}>Completed (Selesai)</option>
                            <option value="cancelled" ${ev.status === 'cancelled' ? 'selected' : ''}>Cancelled (Dibatalkan)</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg"><i data-lucide="save" style="width:17px;height:17px"></i> Simpan Perubahan Event</button>
                    <button type="button" class="btn btn-outline" onclick="document.getElementById('edit-modal-area').innerHTML=''"><i data-lucide="circle-x" style="width:16px;height:16px"></i> Batal</button>
                </form>
            </div>
        `;

        window.selectEditGalleryPreset = (path) => {
            document.getElementById('edit-ev-image').value = path;
        };

        window.handleEditFileGalleryUpload = (input) => {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    document.getElementById('edit-ev-image').value = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        };

        window.onEditVenueChange = (sel) => {
            const opt = sel.options[sel.selectedIndex];
            const lat = opt.getAttribute('data-lat');
            const lng = opt.getAttribute('data-lng');
            const map = opt.getAttribute('data-map');
            if (lat) document.getElementById('edit-ev-lat').value = lat;
            if (lng) document.getElementById('edit-ev-lng').value = lng;
            if (map) document.getElementById('edit-ev-map').value = map;
        };

        document.getElementById('edit-ev-form').addEventListener('submit', async e => {
            e.preventDefault();
            UI.showLoader();
            try {
                await axios.put(API + '/events/' + id, {
                    venue_id: document.getElementById('edit-ev-venue').value,
                    title: document.getElementById('edit-ev-title').value,
                    image: document.getElementById('edit-ev-image').value,
                    description: document.getElementById('edit-ev-desc').value,
                    latitude: document.getElementById('edit-ev-lat').value || null,
                    longitude: document.getElementById('edit-ev-lng').value || null,
                    google_maps_link: document.getElementById('edit-ev-map').value || null,
                    start_date: document.getElementById('edit-ev-start').value,
                    end_date: document.getElementById('edit-ev-end').value,
                    status: document.getElementById('edit-ev-status').value
                });
                alert('Event konser berhasil diperbarui beserta koordinat Google Maps!');
                loadOrgTab('events');
            } catch (err) {
                alert(err.response?.data?.message || 'Gagal memperbarui event');
            }
            UI.hideLoader();
        });

    } catch (err) {
        UI.hideLoader();
        alert('Gagal mengambil data event konser');
    }
};

window.deleteEvent = async (id) => {
    if (!confirm('Apakah Anda yakin ingin menghapus konser ini? Seluruh data tiket terkait akan dihapus.')) return;
    UI.showLoader();
    try {
        await axios.delete(API + '/events/' + id);
        alert('Event konser berhasil dihapus!');
        loadOrgTab('events');
    } catch (e) {
        alert(e.response?.data?.message || 'Gagal menghapus event');
    }
    UI.hideLoader();
};

// === ORGANIZER EVENT DETAIL (Kelola Tiket) ===
Router.add('/organizer/events/:id', async (eid) => {
    const u = Auth.getUser();
    if (!u || u.role !== 'organizer') return Router.navigate('/login');

    UI.mount(`
        <div class="page-body">
            <div class="container">
                <div id="org-ev-detail">
                    <div class="text-center p-4"><div class="spinner" style="margin:0 auto"></div></div>
                </div>
            </div>
        </div>
    `, true);

    try {
        const r = await axios.get(API + '/events/' + eid);
        const ev = r.data.event;
        let ttRows = '';
        if (ev.ticket_types) {
            ev.ticket_types.forEach(t => {
                ttRows += `
                    <tr>
                        <td><b>${escapeHtmlDash(t.name)}</b></td>
                        <td>Rp ${Number(t.price).toLocaleString('id-ID')}</td>
                        <td>${t.quota} tiket</td>
                    </tr>
                `;
            });
        }

        const mapLink = ev.google_maps_link || ev.venue?.google_maps_link;

        document.getElementById('org-ev-detail').innerHTML = `
            <div class="flex justify-between items-center mb-3">
                <div>
                    <h2 style="margin:0">${escapeHtmlDash(ev.title)}</h2>
                    <p class="text-muted" style="margin:0.2rem 0 0 0">
                            ${escapeHtmlDash(ev.venue?.name || '')} &nbsp;|&nbsp; 
                            ${new Date(ev.start_date).toLocaleString('id-ID')}
                        </p>
                    </div>
                    <button class="btn btn-outline" onclick="Router.navigate('/organizer/dashboard')"><i data-lucide="arrow-left" style="width:16px;height:16px"></i> Kembali ke Dashboard</button>
            </div>
                <p class="text-muted mt-2">${escapeHtmlDash(ev.description || '')}</p>
                ${mapLink ? `<p class="mt-2"><a href="${mapLink}" target="_blank" class="btn btn-sm btn-outline"><i data-lucide="map-pin" style="width:14px;height:14px"></i> Buka Lokasi Venue di Google Maps</a></p>` : ''}

            <div class="grid-2">
                <div>
                    <h3><i data-lucide="ticket" style="width:18px;height:18px;vertical-align:-4px;color:var(--primary)"></i> Kategori Tiket Konser</h3>
                    <div class="table-wrap mt-2">
                        <table>
                            <thead><tr><th>Kategori</th><th>Harga</th><th>Kuota</th></tr></thead>
                            <tbody>${ttRows || '<tr><td colspan="3" class="text-muted text-center p-3">Belum ada tipe tiket.</td></tr>'}</tbody>
                        </table>
                    </div>
                </div>
                <div>
                    <div class="card">
                        <h3><i data-lucide="plus-circle" style="width:18px;height:18px;vertical-align:-4px;color:var(--primary)"></i> Tambah Tipe Tiket Baru</h3>
                        <div class="form-group mt-3">
                            <label class="form-label"><i data-lucide="ticket" style="width:14px;height:14px;vertical-align:-2px"></i> Nama Kategori Tiket</label>
                            <input id="tt-name" class="form-control" placeholder="Contoh: VIP Soundcheck, Festival A">
                        </div>
                        <div class="grid-half">
                            <div class="form-group">
                                <label class="form-label"><i data-lucide="banknote" style="width:14px;height:14px;vertical-align:-2px"></i> Harga Tiket (Rp)</label>
                                <input id="tt-price" type="text" class="form-control" placeholder="cth: 2.500.000" oninput="formatPriceInputDash(this)">
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i data-lucide="hash" style="width:14px;height:14px;vertical-align:-2px"></i> Kuota Tiket</label>
                                <input id="tt-quota" type="number" class="form-control" placeholder="100">
                            </div>
                        </div>
                        <button class="btn btn-primary w-100 mt-2" onclick="addTicketType(${eid})"><i data-lucide="save" style="width:16px;height:16px"></i> Simpan Tipe Tiket</button>
                    </div>
                </div>
            </div>
        `;
    } catch (e) {
        document.getElementById('org-ev-detail').innerHTML = '<p class="text-danger text-center">Event konser tidak ditemukan.</p>';
    }
});

window.formatPriceInputDash = (input) => {
    let value = input.value.replace(/[^,\d]/g, '');
    let split = value.split(',');
    let sOut = split[0].toString();
    let sRest = sOut.length % 3;
    let sResult = sOut.substr(0, sRest);
    let sThousand = sOut.substr(sRest).match(/\d{3}/gi);
    if (sThousand) {
        let separator = sRest ? '.' : '';
        sResult += separator + sThousand.join('.');
    }
    input.value = sResult;
};

window.addTicketType = async (eid) => {
    try {
        let rawPrice = document.getElementById('tt-price').value.replace(/\./g, '');
        let price = parseFloat(rawPrice);
        await axios.post(API + '/events/' + eid + '/ticket-types', {
            name: document.getElementById('tt-name').value,
            price: price,
            quota: parseInt(document.getElementById('tt-quota').value)
        });
        alert('Tipe tiket berhasil ditambahkan!');
        Router.navigate('/organizer/events/' + eid);
    } catch (e) {
        alert(e.response?.data?.message || 'Gagal menambah tiket');
    }
};

// === SCANNER DASHBOARD ===
// === HELPER RENDER HASIL SCAN (dipakai halaman scanner & tab scan organizer) ===
function scanInitials(name) {
    return String(name || '?').trim().split(/\s+/).slice(0, 2).map(w => w[0] || '').join('').toUpperCase() || '?';
}

function renderScanCustomerCard(user) {
    if (!user) {
        return `<div class="scan-customer-card">
            <div class="scan-avatar" style="background:var(--text-muted)"><i data-lucide="user" style="width:26px;height:26px"></i></div>
            <div><div class="scan-cust-name">Data pelanggan tidak tersedia</div>
            <div class="scan-cust-meta"><span>Kode ini tidak terhubung ke akun pelanggan.</span></div></div>
        </div>`;
    }
    return `
        <div class="scan-customer-card">
            <div class="scan-avatar">${escapeHtmlDash(scanInitials(user.name))}</div>
            <div style="flex:1;min-width:200px">
                <div class="scan-cust-name">${escapeHtmlDash(user.name || '-')}
                    <span class="badge badge-purple">${escapeHtmlDash(String(user.role || 'customer').toUpperCase())}</span>
                </div>
                <div class="scan-cust-meta">
                    <span><i data-lucide="mail"></i> ${escapeHtmlDash(user.email || '-')}</span>
                    <span><i data-lucide="phone"></i> ${escapeHtmlDash(user.phone || '-')}</span>
                    ${user.created_at ? `<span><i data-lucide="calendar"></i> Member sejak ${new Date(user.created_at).toLocaleDateString('id-ID')}</span>` : ''}
                </div>
            </div>
        </div>`;
}

function renderScanTicketDetails(t, opts = {}) {
    if (!t) return '';
    const od = t.order_detail || {};
    const tt = od.ticket_type || {};
    const order = od.order || {};
    const ev = tt.event || order.event || {};
    const venue = ev.venue || order.event?.venue || {};
    const seat = od.seat;
    const payRaw = order.payment;
    const payment = Array.isArray(payRaw) ? payRaw[0] : payRaw;

    const statusMap = {
        active: ['badge-info', 'AKTIF / BELUM DIPAKAI'],
        used: ['badge-success', 'SUDAH CHECK-IN'],
        cancelled: ['badge-danger', 'DIBATALKAN'],
    };
    const st = statusMap[t.status] || ['badge-purple', String(t.status || '-').toUpperCase()];

    const orderMap = {
        completed: ['badge-success', 'LUNAS / SELESAI'],
        pending: ['badge-warning', 'MENUNGGU PEMBAYARAN'],
        expired: ['badge-danger', 'KEDALUWARSA'],
        failed: ['badge-danger', 'GAGAL'],
    };
    const od2 = orderMap[order.status] || ['badge-purple', String(order.status || '-').toUpperCase()];

    const payMap = { success: ['badge-success', 'BERHASIL'], paid: ['badge-success', 'BERHASIL'], pending: ['badge-warning', 'PENDING'], failed: ['badge-danger', 'GAGAL'], denied: ['badge-danger', 'GAGAL'] };
    const pay = payment ? (payMap[payment.status] || ['badge-purple', String(payment.status || '-').toUpperCase()]) : ['badge-purple', 'TIDAK ADA DATA'];

    const item = (icon, label, value) => `
        <div class="scan-detail-item">
            <i data-lucide="${icon}"></i>
            <div style="min-width:0"><b>${label}</b><div class="val">${value}</div></div>
        </div>`;

    const rows = [
        item('music', 'Konser', escapeHtmlDash(ev.title || 'Konser Musik')),
        item('calendar', 'Tanggal Konser', ev.start_date ? new Date(ev.start_date).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) + ' WIB' : '-'),
        item('map-pin', 'Venue', escapeHtmlDash(venue.name || ev.location_name || '-')),
        item('ticket', 'Kategori Tiket', escapeHtmlDash(tt.name || '-')),
        item('list-checks', 'Section / Kursi', escapeHtmlDash(seat ? seat.section : '-')),
        item('banknote', 'Harga', 'Rp ' + Number(od.price || tt.price || 0).toLocaleString('id-ID')),
        item('file-text', 'Nomor Order', `#${escapeHtmlDash(String(order.id || '-'))} <span class="badge ${od2[0]}" style="margin-left:4px">${od2[1]}</span>`),
        item('credit-card', 'Status Pembayaran', `<span class="badge ${pay[0]}">${pay[1]}</span>`),
        item('qr-code', 'Kode Barcode', `<code style="font-size:.8rem;word-break:break-all">${escapeHtmlDash(t.barcode || '-')}</code>`),
        item('ticket-check', 'Status Tiket', `<span class="badge ${st[0]}">${st[1]}</span>`),
        item('clock', 'Waktu Check-in', opts.scannedAt ? new Date(opts.scannedAt).toLocaleString('id-ID') + ' WIB' : '-'),
        item('user', 'Petugas Gate', escapeHtmlDash(opts.scannerName || '-')),
    ];

    return `<div class="scan-detail-grid">${rows.join('')}</div>`;
}

Router.add('/scanner', () => {
    const u = Auth.getUser();
    if (!u || !['scanner', 'organizer', 'admin'].includes(u.role)) return Router.navigate('/login');

    UI.mount(`
        <div class="page-body">
            <div class="container" style="max-width:900px">
                <div class="scanner-box">
                    <div class="text-center mb-4">
                        <span class="badge badge-purple mb-2">Petugas Gate / Scanner Mode</span>
                        <h2 style="margin:0;font-size:1.8rem"><i data-lucide="scan-line" style="width:27px;height:27px;vertical-align:-5px;color:var(--primary)"></i> Pemindai Barcode & Check-in Tiket</h2>
                        <p class="text-muted" style="margin-top:0.25rem;font-size:1rem">Arahkan kamera ke QR/Barcode pelanggan (QR, Code128, EAN didukung) atau input kode secara manual</p>
                    </div>

                    <!-- Statistik Check-in (ikon seperti dashboard) -->
                    <div class="stat-grid">
                        <div class="stat-card">
                            <div class="stat-icon" style="background:rgba(59,130,246,.1);color:var(--accent)"><i data-lucide="calendar"></i></div>
                            <div>
                                <h4>Check-in Hari Ini</h4>
                                <div class="stat-val" id="stat-today">-</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon" style="background:rgba(16,185,129,.1);color:var(--success)"><i data-lucide="activity"></i></div>
                            <div>
                                <h4>Total Check-in</h4>
                                <div class="stat-val" id="stat-total">-</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon" style="background:rgba(108,60,225,.1);color:var(--primary)"><i data-lucide="users"></i></div>
                            <div>
                                <h4>Pelanggan Unik</h4>
                                <div class="stat-val" id="stat-uniq">-</div>
                            </div>
                        </div>
                    </div>

                    <div class="card p-4 mb-4" style="border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,0.06)">
                        <!-- Camera / Video Live Stream Area -->
                        <div id="camera-scanner-container" style="display:none;margin-bottom:1.5rem;position:relative;border-radius:16px;overflow:hidden;background:#000;border:3px solid var(--primary);box-shadow:0 10px 25px rgba(0,0,0,0.2)">
                            <video id="laser-video" playsinline muted autoplay style="width:100%;height:340px;object-fit:cover;display:block"></video>
                            <canvas id="laser-canvas" style="display:none"></canvas>
                            
                            <!-- Scanner Targeting Frame (Clean Look without Laser Line) -->
                            <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:260px;height:260px;border:2px solid rgba(255,255,255,0.85);border-radius:16px;pointer-events:none;box-shadow:0 0 0 9999px rgba(0,0,0,0.55);overflow:hidden">
                                <!-- Corner Highlights -->
                                <div style="position:absolute;top:0;left:0;width:28px;height:28px;border-top:4px solid var(--primary);border-left:4px solid var(--primary);border-top-left-radius:12px"></div>
                                <div style="position:absolute;top:0;right:0;width:28px;height:28px;border-top:4px solid var(--primary);border-right:4px solid var(--primary);border-top-right-radius:12px"></div>
                                <div style="position:absolute;bottom:0;left:0;width:28px;height:28px;border-bottom:4px solid var(--primary);border-left:4px solid var(--primary);border-bottom-left-radius:12px"></div>
                                <div style="position:absolute;bottom:0;right:0;width:28px;height:28px;border-bottom:4px solid var(--primary);border-right:4px solid var(--primary);border-bottom-right-radius:12px"></div>
                            </div>
                            
                            <div id="scan-cam-status" style="position:absolute;bottom:12px;left:0;right:0;text-align:center;color:#fff;font-size:0.85rem;background:rgba(0,0,0,0.65);padding:6px;z-index:5">
                                <i data-lucide="scan-line" style="width:14px;height:14px;vertical-align:-3px"></i> Posisikan QR Code / Barcode di dalam kotak
                            </div>
                        </div>

                        <div class="flex gap-2 mb-3">
                            <button id="btn-toggle-cam" class="btn btn-outline w-100" onclick="toggleCameraScanner()">
                                <i data-lucide="camera" style="width:17px;height:17px"></i> Buka Kamera Scanner
                            </button>
                            <label class="btn btn-outline w-100" style="margin:0;cursor:pointer;text-align:center">
                                <i data-lucide="image" style="width:17px;height:17px"></i> Scan File Barcode
                                <input type="file" id="scan-file-input" accept="image/*" style="display:none" onchange="scanBarcodeFromFile(this)">
                            </label>
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label" style="font-weight:700"><i data-lucide="qr-code" style="width:15px;height:15px;vertical-align:-3px"></i> Kode Barcode / Scan Token</label>
                            <div class="flex gap-2">
                                <input id="scan-code" class="form-control" placeholder="Arahkan scanner hardware atau ketik kode..." autofocus style="font-size:1.1rem;text-align:center;letter-spacing:1px" onkeypress="if(event.key==='Enter') doScan()">
                                <button class="btn btn-primary" onclick="doScan()" style="padding:0 24px;font-weight:700"><i data-lucide="shield-check" style="width:17px;height:17px"></i> Validasi</button>
                            </div>
                        </div>
                    </div>

                    <!-- Scan Results & History Tabs -->
                    <div id="scan-result" class="mb-4"></div>

                    <div class="card p-4" style="border-radius:16px">
                        <div class="flex items-center justify-between mb-3" style="flex-wrap:wrap;gap:.75rem">
                            <h3 style="margin:0;font-size:1.2rem"><i data-lucide="users" style="width:19px;height:19px;vertical-align:-4px;color:var(--primary)"></i> Daftar Pelanggan (Check-in Masuk)</h3>
                            <div class="flex gap-2 items-center">
                                <input id="cust-search" class="form-control cust-list-search" placeholder="Cari nama / email / konser..." oninput="filterCustomerList(this.value)">
                                <button class="btn btn-sm btn-outline" style="margin-bottom:0;white-space:nowrap" onclick="loadScanHistory()"><i data-lucide="refresh-cw" style="width:14px;height:14px"></i> Muat Ulang</button>
                            </div>
                        </div>
                        <div id="scan-history-list" style="overflow-x:auto">
                            <p class="text-muted text-center py-3">Memuat daftar pelanggan...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `, true);

    loadScanHistory();
});

window.loadScanHistory = async () => {
    const listEl = document.getElementById('scan-history-list');
    if (!listEl) return;
    try {
        const res = await axios.get(API + '/tickets/scans/history');
        const logs = res.data.scan_logs || [];
        const s = res.data.summary || {};
        const setTxt = (id, v) => {
            const el = document.getElementById(id);
            if (el) el.textContent = Number(v || 0).toLocaleString('id-ID');
        };
        setTxt('stat-today', s.today_checkins);
        setTxt('stat-total', s.total_checkins);
        setTxt('stat-uniq', s.unique_customers);

        if (logs.length === 0) {
            listEl.innerHTML = `<div class="text-muted text-center py-4" style="margin:0"><i data-lucide="clipboard-list" style="width:30px;height:30px;display:block;margin:0 auto .5rem;color:var(--primary)"></i>Belum ada pelanggan yang check-in.</div>`;
            return;
        }

        let html = `
            <table class="table" style="width:100%;border-collapse:collapse;font-size:0.9rem">
                <thead>
                    <tr style="background:#f8fafc;border-bottom:2px solid var(--border)">
                        <th style="padding:10px;text-align:left">Pelanggan</th>
                        <th style="padding:10px;text-align:left">Konser & Tiket</th>
                        <th style="padding:10px;text-align:left">Waktu Check-in</th>
                        <th style="padding:10px;text-align:left">Petugas Gate</th>
                        <th style="padding:10px;text-align:center">Status</th>
                    </tr>
                </thead>
                <tbody>
        `;

        logs.forEach(log => {
            const ticket = log.ticket;
            const od = ticket?.order_detail || {};
            const user = od.order?.user || {};
            const eventTitle = od.ticket_type?.event?.title || 'Konser Musik';
            const categoryName = od.ticket_type?.name || 'Reguler';
            const seatInfo = od.seat ? ` · Section ${od.seat.section}` : '';
            const userName = user.name || 'Pelanggan';
            const userEmail = user.email || '-';
            const userPhone = user.phone || '-';
            const scannerName = log.scanner?.name || 'Petugas';
            const scanTime = new Date(log.scan_time).toLocaleString('id-ID');
            const search = `${userName} ${userEmail} ${userPhone} ${eventTitle} ${categoryName}`.toLowerCase();

            html += `
                <tr style="border-bottom:1px solid var(--border)" data-search="${escapeHtmlDash(search)}">
                    <td style="padding:10px">
                        <div style="display:flex;align-items:center;gap:.6rem">
                            <div class="scan-avatar" style="width:36px;height:36px;font-size:.85rem">${escapeHtmlDash(scanInitials(userName))}</div>
                            <div style="min-width:0">
                                <b>${escapeHtmlDash(userName)}</b><br>
                                <span class="text-muted" style="font-size:.78rem;word-break:break-all"><i data-lucide="mail" style="width:11px;height:11px;vertical-align:-1px"></i> ${escapeHtmlDash(userEmail)} &middot; <i data-lucide="phone" style="width:11px;height:11px;vertical-align:-1px"></i> ${escapeHtmlDash(userPhone)}</span>
                            </div>
                        </div>
                    </td>
                    <td style="padding:10px"><b>${escapeHtmlDash(eventTitle)}</b><br><span class="text-muted" style="font-size:0.8rem">${escapeHtmlDash(categoryName)}${escapeHtmlDash(seatInfo)}</span></td>
                    <td style="padding:10px;white-space:nowrap">${scanTime}</td>
                    <td style="padding:10px">${escapeHtmlDash(scannerName)}</td>
                    <td style="padding:10px;text-align:center"><span class="badge badge-success"><i data-lucide="badge-check" style="width:12px;height:12px;vertical-align:-2px"></i> MASUK</span></td>
                </tr>
            `;
        });

        html += `</tbody></table>`;
        listEl.innerHTML = html;

        // Terapkan kembali filter pencarian yang sedang aktif
        const searchInput = document.getElementById('cust-search');
        if (searchInput && searchInput.value.trim()) filterCustomerList(searchInput.value);
    } catch (e) {
        listEl.innerHTML = `<p class="text-danger text-center py-3" style="margin:0">Gagal memuat daftar pelanggan.</p>`;
    }
};

window.filterCustomerList = (q) => {
    const query = String(q || '').trim().toLowerCase();
    document.querySelectorAll('#scan-history-list tr[data-search]').forEach(tr => {
        const match = !query || (tr.getAttribute('data-search') || '').includes(query);
        tr.classList.toggle('list-row-hidden', !match);
    });
};

// ===== DECODER BARCODE MULTI-FORMAT =====
// jsQR hanya membaca QR — sedangkan tiket di aplikasi ini juga menampilkan barcode
// cetak 1D (Code128/EAN) dari halaman e-ticket. Karena itu dibutuhkan ZXing (multi-format)
// dan deteksi native BarcodeDetector bila tersedia. Semua dimuat dari lokal agar tetap
// bekerja saat offline.
const BarcodeDecoder = {
    zxing: null,
    zxingPromise: null,
    native: undefined,       // undefined = belum dicek, false = tidak tersedia
    jsQrPromise: null,
    lastZxingAt: 0,

    status(text, kind = 'info') {
        const el = document.getElementById('scan-cam-status');
        if (!el) return;
        const icons = { info: 'scan-line', ok: 'circle-check', warn: 'triangle-alert', error: 'circle-alert' };
        const colors = { info: '#ffffff', ok: '#34d399', warn: '#fbbf24', error: '#f87171' };
        el.innerHTML = `<i data-lucide="${icons[kind] || 'scan-line'}" style="width:14px;height:14px;vertical-align:-3px"></i> ${text}`;
        el.style.color = colors[kind] || '#ffffff';
    },

    loadScript(src, isReady) {
        return new Promise((resolve, reject) => {
            if (isReady()) return resolve(true);
            let settled = false;
            const finish = () => {
                if (settled) return;
                settled = true;
                if (isReady()) resolve(true);
                else reject(new Error('Gagal memuat ' + src));
            };
            const s = document.createElement('script');
            s.src = src;
            s.async = true;
            s.onload = finish;
            s.onerror = finish;
            document.head.appendChild(s);
            setTimeout(finish, 8000); // jangan gantung bila offline
        });
    },

    ensureJsQr() {
        if (!this.jsQrPromise) {
            this.jsQrPromise = this.loadScript('/js/jsQR.min.js', () => !!window.jsQR)
                .catch(e => { this.jsQrPromise = null; throw e; });
        }
        return this.jsQrPromise;
    },

    ensureZxing() {
        if (this.zxing) return Promise.resolve(this.zxing);
        if (!this.zxingPromise) {
            this.zxingPromise = this.loadScript('/js/zxing.min.js', () => !!(window.ZXing && window.ZXing.MultiFormatReader))
                .then(() => { this.zxing = window.ZXing; return this.zxing; })
                .catch(e => { this.zxingPromise = null; throw e; });
        }
        return this.zxingPromise;
    },

    async ensureNative() {
        if (this.native !== undefined) return this.native;
        this.native = false;
        try {
            if ('BarcodeDetector' in window) {
                const sup = await window.BarcodeDetector.getSupportedFormats();
                const want = ['qr_code', 'code_128', 'code_39', 'code_93', 'ean_13', 'ean_8', 'upc_a', 'upc_e', 'itf', 'codabar']
                    .filter(f => sup[f]);
                if (want.length) this.native = new window.BarcodeDetector({ formats: want });
            }
        } catch (e) { this.native = false; }
        return this.native;
    },

    async prepare() {
        await Promise.allSettled([
            this.ensureJsQr(),
            this.ensureZxing(),
            this.ensureNative(),
        ]);
        const parts = [];
        if (this.native) parts.push('deteksi native');
        if (window.jsQR) parts.push('QR (jsQR)');
        if (this.zxing) parts.push('QR + Code128/EAN (ZXing)');
        if (parts.length) {
            this.status(`Decoder siap: ${parts.join(' &bull; ')} &mdash; posisikan QR / barcode di dalam kotak`,
                (this.zxing || this.native) ? 'ok' : 'warn');
        } else {
            this.status('Decoder gagal dimuat. Periksa lalu jalankan ulang kamera.', 'error');
        }
    },

    // Decode QR + barcode 1D (Code128/EAN/UPC dll) dari canvas — ZXing multi-format
    decodeZxingFromCanvas(canvas) {
        const Z = this.zxing;
        if (!Z) return null;
        try {
            const source = new Z.HTMLCanvasElementLuminanceSource(canvas);
            const bitmap = new Z.BinaryBitmap(new Z.HybridBinarizer(source));
            const reader = new Z.MultiFormatReader();
            const res = reader.decode(bitmap);
            const text = res ? res.getText() : null;
            try { reader.reset(); } catch (e) { }
            return text || null;
        } catch (e) {
            return null; // NotFoundException = tidak ada kode di frame ini (wajar)
        }
    },

    // Deteksi via API native bila browser mendukung (mis. HP Android)
    async detectNative(imageData) {
        const nat = await this.ensureNative();
        if (!nat) return null;
        try {
            const res = await nat.detect(imageData);
            if (res && res.length && res[0].rawValue) return res[0].rawValue;
        } catch (e) { }
        return null;
    },
};

window.toggleCameraScanner = async () => {
    const container = document.getElementById('camera-scanner-container');
    const btn = document.getElementById('btn-toggle-cam');
    const video = document.getElementById('laser-video');
    const canvas = document.getElementById('laser-canvas');

    const stopScannerAll = () => {
        if (window.scanAnimFrame) { cancelAnimationFrame(window.scanAnimFrame); window.scanAnimFrame = null; }
        if (window.cameraStream) { window.cameraStream.getTracks().forEach(track => track.stop()); window.cameraStream = null; }
        if (video) video.srcObject = null;
        if (container) container.style.display = 'none';
        if (btn) {
            btn.innerHTML = '<i data-lucide="camera" style="width:17px;height:17px"></i> Buka Kamera Scanner';
            btn.className = 'btn btn-outline w-100';
        }
    };

    if (container.style.display === 'none') {
        container.style.display = 'block';
        btn.innerHTML = '<i data-lucide="camera-off" style="width:17px;height:17px"></i> Matikan Kamera';
        btn.className = 'btn btn-danger w-100';

        // Siapkan decoder lokal: jsQR (QR) + ZXing (QR & barcode 1D Code128/EAN)
        window.scanHandled = false;
        window.scanDecoding = false;
        BarcodeDecoder.status('Memuat decoder QR + barcode...', 'info');
        BarcodeDecoder.prepare();

        try {
            // Request high-resolution rear camera stream directly
            const constraints = {
                video: {
                    facingMode: { ideal: "environment" },
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                }
            };
            window.cameraStream = await navigator.mediaDevices.getUserMedia(constraints);
            video.srcObject = window.cameraStream;
            await video.play();
            BarcodeDecoder.status('Kamera aktif &mdash; posisikan QR / barcode di dalam kotak', 'info');

            const ctx = canvas.getContext('2d', { willReadFrequently: true });

            // Loop frame: QR (jsQR) tiap frame + deteksi native + barcode 1D (ZXing, ditrottle)
            const handleHit = (text) => {
                if (window.scanHandled || !text) return;
                window.scanHandled = true;
                BarcodeDecoder.status('Kode ditemukan! Memproses data tiket...', 'ok');
                try {
                    const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                    const osc = audioCtx.createOscillator();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(880, audioCtx.currentTime);
                    osc.connect(audioCtx.destination);
                    osc.start();
                    osc.stop(audioCtx.currentTime + 0.18);
                } catch (e) { }
                const codeInput = document.getElementById('scan-code');
                if (codeInput) codeInput.value = text;
                stopScannerAll();
                doScan();
            };

            const scanFrame = () => {
                if (!window.cameraStream || window.scanHandled) return;

                let imageData = null;
                if (video.readyState === video.HAVE_ENOUGH_DATA) {
                    try {
                        canvas.width = video.videoWidth;
                        canvas.height = video.videoHeight;
                        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

                        // --- QR: jsQR (ringan, tetap jalan walau offline) ---
                        if (window.jsQR) {
                            imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                            let code = jsQR(imageData.data, imageData.width, imageData.height, {
                                inversionAttempts: "attemptBoth",
                            });

                            // Bila belum terdeteksi, crop tengah (area kotak bidik)
                            if (!code) {
                                const cropW = Math.floor(canvas.width * 0.6);
                                const cropH = Math.floor(canvas.height * 0.6);
                                const startX = Math.floor((canvas.width - cropW) / 2);
                                const startY = Math.floor((canvas.height - cropH) / 2);
                                const centerData = ctx.getImageData(startX, startY, cropW, cropH);
                                code = jsQR(centerData.data, centerData.width, centerData.height, {
                                    inversionAttempts: "attemptBoth",
                                });
                            }

                            if (code && code.data) { handleHit(code.data); return; }
                        }
                    } catch (e) {
                        console.warn('Scan frame error (dilewati):', e);
                    }

                    // --- Decoder async: native BarcodeDetector + ZXing utk barcode 1D (Code128/EAN) ---
                    if (!window.scanDecoding) {
                        window.scanDecoding = true;
                        (async () => {
                            try {
                                if (!imageData) imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);

                                const rawNat = await BarcodeDecoder.detectNative(imageData);
                                if (rawNat) { window.scanDecoding = false; handleHit(rawNat); return; }

                                const now = Date.now();
                                if (BarcodeDecoder.zxing && now - BarcodeDecoder.lastZxingAt > 200) {
                                    BarcodeDecoder.lastZxingAt = now;
                                    const raw = BarcodeDecoder.decodeZxingFromCanvas(canvas);
                                    if (raw) { window.scanDecoding = false; handleHit(raw); return; }
                                }
                            } catch (e) {
                                console.warn('Async decoder error (dilewati):', e);
                            }
                            window.scanDecoding = false;
                        })();
                    }
                }

                window.scanAnimFrame = requestAnimationFrame(scanFrame);
            };

            window.scanAnimFrame = requestAnimationFrame(scanFrame);

        } catch (e) {
            console.error('Camera error:', e);
            const errMap = {
                NotAllowedError: 'Izin kamera ditolak. Izinkan akses kamera di pengaturan browser lalu coba lagi.',
                NotFoundError: 'Kamera tidak ditemukan di perangkat ini.',
                NotReadableError: 'Kamera sedang dipakai aplikasi lain. Tutup aplikasi tersebut lalu coba lagi.',
                SecurityError: 'Browser memblokir kamera. Buka situs lewat http://127.0.0.1:8000 (localhost).',
            };
            const errMsg = errMap[e.name] || ('Gagal membuka kamera: ' + (e.message || e.name || 'tidak diketahui'));
            stopScannerAll();
            if (typeof UI !== 'undefined' && UI.toast) UI.toast(errMsg, 'danger');
            else alert(errMsg);
        }
    } else {
        stopScannerAll();
    }
};

window.scanBarcodeFromFile = async (input) => {
    if (!input.files || input.files.length === 0) return;
    const file = input.files[0];
    input.value = '';

    UI.showLoader();
    try {
        // Decoder lokal tanpa dependensi DOM: ZXing (QR + Code128/EAN) & jsQR (cadangan QR)
        await BarcodeDecoder.ensureJsQr().catch(() => { });
        await BarcodeDecoder.ensureZxing().catch(() => { });
        const native = await BarcodeDecoder.ensureNative();

        const bmp = await createImageBitmap(file);
        const MAX = 2000;
        const scale = Math.min(1, MAX / Math.max(bmp.width, bmp.height));
        const c = document.createElement('canvas');
        c.width = Math.max(1, Math.round(bmp.width * scale));
        c.height = Math.max(1, Math.round(bmp.height * scale));
        const cx = c.getContext('2d', { willReadFrequently: true });
        cx.drawImage(bmp, 0, 0, c.width, c.height);
        if (bmp.close) bmp.close();

        let text = null;

        // 1) Deteksi native bila didukung browser
        if (native) {
            try {
                const res = await native.detect(cx.getImageData(0, 0, c.width, c.height));
                if (res && res.length && res[0].rawValue) text = res[0].rawValue;
            } catch (e) { }
        }

        // 2) ZXing multi-format: QR + barcode cetak 1D (Code128, EAN, UPC, dll)
        if (!text) text = BarcodeDecoder.decodeZxingFromCanvas(c);

        // 3) jsQR cadangan untuk QR
        if (!text && window.jsQR) {
            try {
                const id = cx.getImageData(0, 0, c.width, c.height);
                const q = jsQR(id.data, id.width, id.height, { inversionAttempts: 'attemptBoth' });
                if (q && q.data) text = q.data;
            } catch (e) { }
        }

        UI.hideLoader();
        if (text) {
            const el = document.getElementById('scan-code');
            if (el) el.value = text;
            UI.toast('Gambar barcode berhasil dibaca!', 'success');
            doScan();
        } else {
            UI.toast('Barcode pada gambar tidak terbaca. Pastikan gambar terang dan tidak blur, atau ketik kode manual.', 'danger');
        }
    } catch (err) {
        UI.hideLoader();
        UI.toast('Gagal membaca gambar: ' + (err && err.message ? err.message : err), 'danger');
    }
};

window.doScan = async () => {
    const codeInput = document.getElementById('scan-code');
    const code = codeInput ? codeInput.value.trim() : '';
    if (!code) {
        UI.toast('Silakan masukkan atau scan kode barcode terlebih dahulu.', 'warning');
        return;
    }
    UI.showLoader();
    try {
        const r = await axios.post(API + '/tickets/scan', { code });
        UI.hideLoader();
        const t = r.data.ticket;
        const msg = r.data.message || 'AKSES DITERIMA - TIKET VALID';

        // Play positive sound feedback
        try {
            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = audioCtx.createOscillator();
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(523.25, audioCtx.currentTime); // C5
            osc.frequency.setValueAtTime(659.25, audioCtx.currentTime + 0.1); // E5
            osc.frequency.setValueAtTime(783.99, audioCtx.currentTime + 0.2); // G5
            osc.connect(audioCtx.destination);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.35);
        } catch (e) { }

        UI.toast(msg, 'success');

        const cust = t?.order_detail?.order?.user;
        const box = document.getElementById('scan-result');
        if (box) {
            box.innerHTML = `
                <div class="scan-result approved card p-4" style="border:3px solid var(--success);background:rgba(16,185,129,0.08);border-radius:12px;text-align:center">
                    <div class="scan-status-icon success"><i data-lucide="circle-check"></i></div>
                    <div class="badge badge-success mb-2" style="font-size:1rem;padding:6px 16px">VERIFIKASI BERHASIL - TIKET SAH</div>
                    <h2 style="color:var(--success);margin:0;font-size:1.5rem">${escapeHtmlDash(msg)}</h2>
                    <p class="text-muted" style="margin:.4rem 0 0;font-size:.92rem"><i data-lucide="user-check" style="width:15px;height:15px;vertical-align:-3px"></i> Data pelanggan berhasil diverifikasi dari QR code</p>
                    ${renderScanCustomerCard(cust)}
                    ${renderScanTicketDetails(t, {
                        scannedAt: r.data.scanned_at || new Date().toISOString(),
                        scannerName: r.data.scanner_name || Auth.getUser()?.name
                    })}
                </div>
            `;
            try { box.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (err) { }
        }
        if (codeInput) codeInput.value = '';
        loadScanHistory();
    } catch (e) {
        UI.hideLoader();
        const data = e.response?.data || {};
        const errorMsg = data.message || 'Tiket Tidak Valid atau Sudah Digunakan';
        const t = data.ticket;

        // Play negative error buzz feedback
        try {
            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = audioCtx.createOscillator();
            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(220, audioCtx.currentTime); // A3
            osc.frequency.setValueAtTime(164.81, audioCtx.currentTime + 0.15); // E3
            osc.connect(audioCtx.destination);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.35);
        } catch (err) { }

        UI.toast(errorMsg, 'danger');

        const cust = t?.order_detail?.order?.user;
        const box = document.getElementById('scan-result');
        if (box) {
            box.innerHTML = `
                <div class="scan-result rejected card p-4" style="border:3px solid var(--danger);background:rgba(239,68,68,0.08);border-radius:12px;text-align:center">
                    <div class="scan-status-icon danger"><i data-lucide="circle-x"></i></div>
                    <div class="badge badge-danger mb-2" style="font-size:1rem;padding:6px 16px">VERIFIKASI DITOLAK</div>
                    <h2 style="color:var(--danger);margin:0;font-size:1.4rem">${escapeHtmlDash(errorMsg)}</h2>
                    ${t ? `
                        <p class="text-muted mt-2" style="font-size:.9rem;margin-bottom:0"><i data-lucide="info" style="width:15px;height:15px;vertical-align:-3px"></i> Data pemegang tiket untuk verifikasi manual di gerbang:</p>
                        ${renderScanCustomerCard(cust)}
                        ${renderScanTicketDetails(t, {})}
                    ` : `<p class="text-muted mt-2" style="font-size:0.9rem">Kode tidak ditemukan di database. Pastikan pelanggan menampilkan QR terbaru dari menu Tiket Saya.</p>`}
                </div>
            `;
            try { box.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (err) { }
        }
    }
};

// === SUPER ADMIN DASHBOARD (STATS, USERS, ORGANIZERS, AI SECURITY) ===
const renderAdminDashboard = async (tab = 'overview') => {
    const u = Auth.getUser();
    if (!u || u.role !== 'admin') return Router.navigate('/login');

    UI.mount(`
        <div class="dash-layout">
            <aside class="sidebar">
                <div class="sidebar-brand">Super Admin Panel</div>
                <ul class="sidebar-nav">
                    <li><a href="/admin/dashboard/overview" class="${tab === 'overview' ? 'active' : ''}" onclick="event.preventDefault(); Router.navigate('/admin/dashboard/overview')"><i data-lucide="chart-column"></i> Ringkasan & Metrik</a></li>
                    <li><a href="/admin/dashboard/users" class="${tab === 'users' ? 'active' : ''}" onclick="event.preventDefault(); Router.navigate('/admin/dashboard/users')"><i data-lucide="users"></i> Manajemen Pengguna</a></li>
                    <li><a href="/admin/dashboard/organizers" class="${tab === 'organizers' ? 'active' : ''}" onclick="event.preventDefault(); Router.navigate('/admin/dashboard/organizers')"><i data-lucide="badge-check"></i> Verifikasi Organizer</a></li>
                    <li><a href="/admin/dashboard/security" class="${tab === 'security' ? 'active' : ''}" onclick="event.preventDefault(); Router.navigate('/admin/dashboard/security')"><i data-lucide="shield-alert"></i> AI Security & Audit</a></li>
                    <li><a href="/" onclick="event.preventDefault();Router.navigate('/')"><i data-lucide="home"></i> Ke Beranda</a></li>
                </ul>
            </aside>
            <main class="dash-content">
                <div id="admin-content">
                    <div class="text-center p-4"><div class="spinner" style="margin:0 auto"></div></div>
                </div>
            </main>
        </div>
    `, true);

    loadAdminTab(tab);
};

Router.add('/admin/dashboard', () => renderAdminDashboard('overview'));
Router.add('/admin/dashboard/:tab', (tab) => renderAdminDashboard(tab));

window.loadAdminTab = async (tab, clickedLink = null) => {
    document.querySelectorAll('.sidebar-nav a').forEach(a => a.classList.remove('active'));
    if (clickedLink) {
        clickedLink.classList.add('active');
    } else if (window.event && window.event.target && window.event.target.tagName === 'A') {
        window.event.target.classList.add('active');
    }
    const el = document.getElementById('admin-content');
    el.innerHTML = '<div class="text-center p-4"><div class="spinner" style="margin:0 auto"></div></div>';

    if (tab === 'overview') {
        try {
            const r = await axios.get(API + '/admin/dashboard');
            const m = r.data.metrics || {};
            const userStats = m.user_stats || [];
            let userStatBadges = userStats.map(s => `<span class="badge badge-purple" style="margin-right:6px;font-size:0.85rem">${s.role.toUpperCase()}: ${s.total}</span>`).join(' ');

            el.innerHTML = `
                <div class="flex justify-between items-center mb-3">
                    <div>
                        <h2><i data-lucide="gauge" style="width:23px;height:23px;vertical-align:-4px;color:var(--primary)"></i> Dashboard Super Admin</h2>
                        <p class="text-muted">Pemantauan kesehatan platform, analitik transaksi, dan pengguna KetTiket.</p>
                    </div>
                    <button class="btn btn-primary" onclick="loadAdminTab('security')"><i data-lucide="shield-alert" style="width:16px;height:16px"></i> Jalankan AI Security</button>
                </div>
                <div class="stat-grid mt-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background:rgba(16,185,129,.1);color:var(--success)"><i data-lucide="banknote"></i></div>
                        <div>
                            <h4>Total Penjualan Sukses</h4>
                            <div class="stat-val">Rp ${Number(m.total_sales || 0).toLocaleString('id-ID')}</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background:rgba(108,60,225,.1);color:var(--primary)"><i data-lucide="shopping-cart"></i></div>
                        <div>
                            <h4>Total Pesanan Sukses</h4>
                            <div class="stat-val">${(m.completed_orders_count || 0).toLocaleString('id-ID')} / ${(m.total_orders_count || 0)}</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background:rgba(59,130,246,.1);color:var(--accent)"><i data-lucide="calendar"></i></div>
                        <div>
                            <h4>Konser Mendatang</h4>
                            <div class="stat-val">${(m.upcoming_events_count || 0)} Event</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background:rgba(245,158,11,.1);color:var(--warning)"><i data-lucide="activity"></i></div>
                        <div>
                            <h4>Status Platform</h4>
                            <div class="stat-val" style="color:var(--success)">Optimal 100%</div>
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <h3><i data-lucide="users" style="width:18px;height:18px;vertical-align:-4px;color:var(--primary)"></i> Distribusi Pengguna Platform</h3>
                    <p class="text-muted">Statistik jumlah akun berdasarkan peran dalam ekosistem</p>
                    <div class="mt-2">${userStatBadges || '<span class="text-muted">Memuat data user...</span>'}</div>
                </div>
            `;
        } catch (e) {
            el.innerHTML = '<p class="text-danger">Gagal memuat statistik Super Admin.</p>';
        }
    } else if (tab === 'users') {
        try {
            const r = await axios.get(API + '/admin/users');
            // Daftar pengguna khusus akun staff: admin, organizer, scanner (customer tidak ditampilkan)
            const users = (r.data.users || []).filter(x => ['admin', 'organizer', 'scanner'].includes(x.role));
            let rows = '';

            users.forEach(u => {
                rows += `
                    <tr>
                        <td>${u.id}</td>
                        <td><b>${escapeHtmlDash(u.name)}</b></td>
                        <td>${escapeHtmlDash(u.email)}</td>
                        <td>
                            <select onchange="changeUserRole(${u.id}, this.value)" style="background:#fff;color:var(--text-dark);border:1px solid var(--border);padding:4px 8px;border-radius:6px;font-weight:600">
                                <option value="organizer" ${u.role === 'organizer' ? 'selected' : ''}>Organizer</option>
                                <option value="scanner" ${u.role === 'scanner' ? 'selected' : ''}>Scanner</option>
                                <option value="admin" ${u.role === 'admin' ? 'selected' : ''}>Admin</option>
                            </select>
                        </td>
                        <td>
                            <span class="badge ${u.status ? 'badge-success' : 'badge-danger'}">${u.status ? 'Aktif' : 'Non-Aktif'}</span>
                        </td>
                        <td>
                            <button class="btn btn-sm ${u.status ? 'btn-danger' : 'btn-outline'}" onclick="toggleUserStatus(${u.id})">
                                <i data-lucide="${u.status ? 'circle-x' : 'user-check'}" style="width:14px;height:14px"></i> ${u.status ? 'Nonaktifkan' : 'Aktifkan'}
                            </button>
                        </td>
                    </tr>
                `;
            });

            el.innerHTML = `
                <h2><i data-lucide="users" style="width:23px;height:23px;vertical-align:-4px;color:var(--primary)"></i> Manajemen Pengguna KetTiket</h2>
                <p class="text-muted">Daftar khusus akun staff: <b>admin, organizer, dan scanner</b> (akun customer tidak ditampilkan). Kelola hak akses role dan status aktivasi akun.</p>
                <div class="card mt-3" style="padding:1rem;">
                    <h3 style="margin-bottom:0.8rem;"><i data-lucide="key-round" style="width:18px;height:18px;vertical-align:-4px;color:var(--primary)"></i> Ubah Role Cepat</h3>
                    <div style="display:flex; gap:0.75rem; flex-wrap:wrap; align-items:end;">
                        <div style="flex:1; min-width:220px;">
                            <label class="form-label" for="roleTargetKey">ID atau Inisial User</label>
                            <input id="roleTargetKey" class="form-control" placeholder="Contoh: 12 atau afif" />
                        </div>
                        <div style="min-width:180px;">
                            <label class="form-label" for="roleTargetValue">Ubah jadi</label>
                            <select id="roleTargetValue" class="form-control">
                                <option value="organizer">Organizer</option>
                                <option value="scanner">Scanner</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <button class="btn btn-primary" onclick="quickAssignRoleFromInput()"><i data-lucide="key-round" style="width:16px;height:16px"></i> Simpan Peran</button>
                    </div>
                </div>
                <div class="table-wrap mt-3">
                    <table>
                        <thead>
                            <tr><th>ID</th><th>Nama</th><th>Email</th><th>Role</th><th>Status Akun</th><th>Aksi</th></tr>
                        </thead>
                        <tbody>${rows || '<tr><td colspan="6" class="text-center text-muted p-4">Tidak ada pengguna.</td></tr>'}</tbody>
                    </table>
                </div>
            `;
        } catch (e) {
            el.innerHTML = '<p class="text-danger">Gagal memuat daftar pengguna.</p>';
        }
    } else if (tab === 'organizers') {
        try {
            const r = await axios.get(API + '/admin/organizers');
            const orgs = r.data.organizers || [];
            let rows = '';

            orgs.forEach(o => {
                rows += `
                    <tr>
                        <td>${o.id}</td>
                        <td><b>${escapeHtmlDash(o.company_name)}</b></td>
                        <td>${escapeHtmlDash(o.user?.name || '-')} (${escapeHtmlDash(o.user?.email || '-')})</td>
                        <td>${escapeHtmlDash(o.phone || '-')}</td>
                        <td>
                            <span class="badge ${o.verified ? 'badge-success' : 'badge-warning'}">${o.verified ? 'Verified' : 'Menunggu Verifikasi'}</span>
                        </td>
                        <td>
                            <button class="btn btn-sm ${o.verified ? 'btn-outline' : 'btn-primary'}" onclick="verifyOrganizerToggle(${o.id})">
                                <i data-lucide="${o.verified ? 'circle-x' : 'badge-check'}" style="width:14px;height:14px"></i> ${o.verified ? 'Batal Verifikasi' : 'Verifikasi Sekarang'}
                            </button>
                        </td>
                    </tr>
                `;
            });

            el.innerHTML = `
                <h2><i data-lucide="badge-check" style="width:23px;height:23px;vertical-align:-4px;color:var(--primary)"></i> Verifikasi Penyelenggara Acara (Organizer)</h2>
                <p class="text-muted">Pastikan identitas dan legalitas event organizer terverifikasi sebelum menyelenggarakan konser musik.</p>
                <div class="table-wrap mt-3">
                    <table>
                        <thead>
                            <tr><th>ID</th><th>Nama Perusahaan</th><th>Akun Terhubung</th><th>Kontak No HP</th><th>Status Verifikasi</th><th>Tindakan</th></tr>
                        </thead>
                        <tbody>${rows || '<tr><td colspan="6" class="text-center text-muted p-4">Belum ada organizer terdaftar.</td></tr>'}</tbody>
                    </table>
                </div>
            `;
        } catch (e) {
            el.innerHTML = '<p class="text-danger">Gagal memuat data organizer.</p>';
        }
    } else if (tab === 'security') {
        el.innerHTML = `
            <h2><i data-lucide="shield-alert" style="width:23px;height:23px;vertical-align:-4px;color:var(--primary)"></i> AI Security & Platform Audit</h2>
            <p class="text-muted">Analisis intelijen otomatis untuk deteksi kecurangan tiket (bot scalping), anomali transaksi, dan audit sistem.</p>
            <div class="grid-2 mt-3">
                <div class="card">
                    <h3><i data-lucide="bot" style="width:18px;height:18px;vertical-align:-4px;color:var(--primary)"></i> Deteksi Transaksi Mencurigakan & Scalper Bot</h3>
                    <p class="text-muted">Pindai pola pesanan cepat, IP tidak wajar, dan scan tiket duplikat di pintu gerbang.</p>
                    <button class="btn btn-primary mt-2" onclick="runAdminAi('detect-suspicious')"><i data-lucide="shield-alert" style="width:16px;height:16px"></i> Jalankan Audit Keamanan</button>
                </div>
                <div class="card">
                    <h3><i data-lucide="chart-column" style="width:18px;height:18px;vertical-align:-4px;color:var(--primary)"></i> Analisis Komprehensif Ekosistem Platform</h3>
                    <p class="text-muted">Rangkuman tren pertumbuhan penjualan, load platform, dan performa event.</p>
                    <button class="btn btn-primary mt-2" onclick="runAdminAi('analyze-platform')"><i data-lucide="chart-column" style="width:16px;height:16px"></i> Analisis Kinerja Platform</button>
                </div>
            </div>
            <div id="admin-ai-box" class="card mt-4" style="display:none">
                <h3 id="admin-ai-title"><i data-lucide="shield-alert" style="width:18px;height:18px;vertical-align:-4px;color:var(--primary)"></i> Laporan Keamanan AI</h3>
                <div id="admin-ai-body" class="mt-2" style="line-height:1.6"></div>
            </div>
        `;
    }
};

window.runAdminAi = async (endpoint) => {
    const box = document.getElementById('admin-ai-box');
    const body = document.getElementById('admin-ai-body');
    const title = document.getElementById('admin-ai-title');
    box.style.display = 'block';
    body.innerHTML = '<div class="spinner" style="margin:1rem auto"></div><p class="text-center text-muted">AI sedang menganalisis database platform...</p>';
    title.innerHTML = '<i data-lucide="shield-alert" style="width:18px;height:18px;vertical-align:-4px;color:var(--primary)"></i> ' + (endpoint === 'detect-suspicious' ? 'Laporan Audit Keamanan & Deteksi Anomali' : 'Analisis Kinerja Platform AI');

    try {
        const r = await axios.post(API + '/admin/ai/' + endpoint, {});
        const out = r.data.report || r.data.analysis || 'Analisis selesai';
        body.innerHTML = formatAiMessage(out);
    } catch (e) {
        body.innerHTML = '<p class="text-danger">Gagal menjalankan AI: ' + (e.response?.data?.message || 'Error') + '</p>';
    }
};

window.toggleUserStatus = async (uid) => {
    UI.showLoader();
    try {
        await axios.put(API + '/admin/users/' + uid + '/status');
        alert('Status pengguna berhasil diubah!');
        loadAdminTab('users');
    } catch (e) {
        alert(e.response?.data?.message || 'Gagal mengubah status');
    }
    UI.hideLoader();
};

window.changeUserRole = async (uid, role) => {
    UI.showLoader();
    try {
        await axios.put(API + '/admin/users/' + uid + '/role', { role });
        alert('Peran pengguna berhasil diperbarui menjadi: ' + role);
        loadAdminTab('users');
    } catch (e) {
        alert(e.response?.data?.message || 'Gagal mengubah role');
    }
    UI.hideLoader();
};

window.quickAssignRoleFromInput = async () => {
    const key = (document.getElementById('roleTargetKey')?.value || '').trim();
    const role = document.getElementById('roleTargetValue')?.value || 'scanner';

    if (!key) {
        alert('Masukkan ID user atau inisial nama terlebih dahulu.');
        return;
    }

    UI.showLoader();
    try {
        const r = await axios.get(API + '/admin/users');
        const users = r.data.users || [];
        const match = users.find(u => {
            const idText = String(u.id);
            const nameText = (u.name || '').toLowerCase();
            const emailText = (u.email || '').toLowerCase();
            const initials = (u.name || '').split(/\s+/).map(s => s[0]).join('').toLowerCase();
            return idText === key || nameText.includes(key.toLowerCase()) || emailText.includes(key.toLowerCase()) || initials.includes(key.toLowerCase());
        });

        if (!match) {
            alert('User dengan ID atau inisial tersebut tidak ditemukan.');
            UI.hideLoader();
            return;
        }

        await axios.put(API + '/admin/users/' + match.id + '/role', { role });
        alert(`Peran berhasil diubah untuk ${match.name} menjadi ${role}.`);
        loadAdminTab('users');
    } catch (e) {
        alert(e.response?.data?.message || 'Gagal mengubah role user.');
    }
    UI.hideLoader();
};

window.verifyOrganizerToggle = async (oid) => {
    UI.showLoader();
    try {
        await axios.post(API + '/admin/organizers/' + oid + '/verify');
        alert('Status verifikasi organizer berhasil diperbarui!');
        loadAdminTab('organizers');
    } catch (e) {
        alert(e.response?.data?.message || 'Gagal memperbarui verifikasi');
    }
    UI.hideLoader();
};

function escapeHtmlDash(str) {
    if (!str) return '';
    return String(str).replace(/[&<>'"]/g, tag => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        "'": '&#39;',
        '"': '&quot;'
    }[tag] || tag));
}
