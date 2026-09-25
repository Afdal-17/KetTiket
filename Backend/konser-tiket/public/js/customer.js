// === CUSTOMER SPA LOGIC ===

// === CUSTOMER DASHBOARD ===
Router.add('/customer/dashboard', async () => {
    UI.mount(`
        <div class="page-body">
            <div class="container">
                <div class="flex justify-between items-center mb-4" style="flex-wrap:wrap;gap:1rem">
                    <div>
                        <h2 style="margin:0"><i data-lucide="search" style="width:23px;height:23px;vertical-align:-5px;color:var(--primary)"></i> Eksplorasi Konser</h2>
                        <p class="text-muted">Temukan konser impianmu dan nikmati alunan musik terbaik</p>
                    </div>
                    <div class="flex gap-2">
                        <input id="search-ev" class="form-control" placeholder="Cari nama artis, konser, kota..." style="width:260px" onkeypress="if(event.key==='Enter')searchEvents()">
                        <button class="btn btn-primary" onclick="searchEvents()"><i data-lucide="search" style="width:16px;height:16px"></i> Cari</button>
                        <button class="btn btn-outline" onclick="Router.navigate('/customer/tickets')"><i data-lucide="ticket" style="width:16px;height:16px"></i> Tiket Saya</button>
                        <button class="btn btn-accent" onclick="openGlobalAiChat()"><i data-lucide="sparkles" style="width:16px;height:16px"></i> AI Rekomendasi</button>
                    </div>
                </div>
                <div id="c-dash">
                    <div class="text-center p-4"><div class="spinner" style="margin:0 auto"></div></div>
                </div>
            </div>
        </div>
    `, true);

    try {
        const r = await axios.get(API + '/events');
        renderEvents(r.data.events || []);
    } catch (e) {
        document.getElementById('c-dash').innerHTML = '<p class="text-danger text-center">Gagal memuat daftar event konser.</p>';
    }
});

window.searchEvents = async () => {
    const q = document.getElementById('search-ev').value;
    try {
        const r = await axios.get(API + '/events?search=' + encodeURIComponent(q));
        renderEvents(r.data.events || []);
    } catch (e) { }
};

function renderEvents(events) {
    let h = '<div class="grid-2">';
    if (!events.length) {
        h = '<div class="card text-center p-4 w-100" style="grid-column: 1 / -1;"><p class="text-muted">Tidak ada konser ditemukan.</p></div>';
    } else {
        events.forEach(e => {
            const img = e.image || (e.id % 4 === 1 ? '/images/sheila_on_7.jpeg' : e.id % 4 === 2 ? '/images/perunggu.jpeg' : e.id % 4 === 3 ? '/images/opick.jpg' : '/images/for%20revenge.jpg');
            const mapUrl = e.google_maps_link || e.venue?.google_maps_link || (e.latitude && e.longitude ? `https://maps.google.com/?q=${e.latitude},${e.longitude}` : '');

            h += `
                <div class="card" style="padding:0;overflow:hidden;cursor:pointer" onclick="Router.navigate('/customer/events/${e.id}')">
                    <div style="height:170px;background:url('${img}') center/cover,linear-gradient(135deg,#6c3ce1,#3b82f6);position:relative">
                        <div style="position:absolute;bottom:0;left:0;right:0;padding:.75rem 1rem;background:linear-gradient(transparent,rgba(0,0,0,.75))">
                            <span class="badge badge-purple">${e.status.toUpperCase()}</span>
                        </div>
                    </div>
                    <div style="padding:1.25rem">
                        <h3 style="margin-bottom:.4rem;font-size:1.15rem">${escapeHtmlCust(e.title)}</h3>
                        <p class="text-muted" style="font-size:.85rem;margin-bottom:0.75rem">
                            <b>${escapeHtmlCust(e.location_name || e.venue?.name || 'Lokasi event')}</b> - ${escapeHtmlCust(e.venue?.city || '')}<br>
                            ${new Date(e.start_date).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })}
                        </p>
                        ${mapUrl ? `<p style="font-size:0.8rem;margin-bottom:0.5rem"><a href="${mapUrl}" target="_blank" onclick="event.stopPropagation()" style="color:var(--primary);text-decoration:none;display:inline-flex;align-items:center;gap:4px"><i data-lucide="map-pin" style="width:13px;height:13px"></i> Buka di Google Maps <i data-lucide="external-link" style="width:12px;height:12px"></i></a></p>` : ''}
                        <button class="btn btn-outline btn-sm w-100 mt-2"><i data-lucide="arrow-right" style="width:15px;height:15px"></i> Lihat Detail & Beli Tiket</button>
                    </div>
                </div>
            `;
        });
        h += '</div>';
    }
    document.getElementById('c-dash').innerHTML = h;
}

// === EVENT DETAIL + SEAT + GOOGLE MAPS + CHECKOUT ===
Router.add('/customer/events/:id', async (eid) => {
    UI.mount(`
        <div class="page-body">
            <div class="container">
                <div id="e-detail">
                    <div class="text-center p-4"><div class="spinner" style="margin:0 auto"></div></div>
                </div>
            </div>
        </div>
    `, true);

    try {
        const r = await axios.get(API + '/events/' + eid);
        const ev = r.data.event;

        // Seat Map Loading
        let seats = '';
        if (ev.has_seating && ev.venue) {
            try {
                const sr = await axios.get(API + '/venues/' + ev.venue.id + '/seats?event_id=' + eid);
                const s = sr.data.seats || [];
                if (s.length) {
                    let sections = {};
                    s.forEach(st => {
                        if (!sections[st.section]) sections[st.section] = [];
                        sections[st.section].push(st);
                    });
                    seats = '<h3 class="mt-4"><i data-lucide="armchair" style="width:20px;height:20px;vertical-align:-4px;color:var(--primary)"></i> Pilih Tempat Duduk / Section</h3>';
                    for (let sec in sections) {
                        seats += `<p class="mb-1" style="font-weight:600;font-size:0.95rem">Section ${sec}</p><div class="seat-grid mb-3">`;
                        sections[sec].forEach((st, idx) => {
                            const cls = st.is_available ? 'available' : 'booked';
                            // Show ONLY section name on the seat element (e.g. A5, A6, A7)
                            const label = st.section;
                            seats += `<div class="seat ${cls}" data-sid="${st.id}" onclick="${st.is_available ? 'pickSeat(' + st.id + ',\'' + label + '\')' : ''}" title="${label} (${st.is_available ? 'Tersedia' : 'Sudah Terisi'})">${label}</div>`;
                        });
                        seats += '</div>';
                    }
                }
            } catch (e) { }
        } else if (!ev.has_seating) {
            seats = '<div class="card mt-4" style="padding:1rem"><b>General admission</b><p class="text-muted" style="margin:0.35rem 0 0">Event ini tidak menggunakan nomor tempat duduk. Tiket berlaku untuk area berdiri.</p></div>';
        }

        // Ticket Types
        let ttHtml = '<h3 class="mt-4"><i data-lucide="ticket" style="width:20px;height:20px;vertical-align:-4px;color:var(--primary)"></i> Pilih Kategori Tiket</h3><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem" id="ticket-types">';
        if (ev.ticket_types && ev.ticket_types.length) {
            ev.ticket_types.forEach(t => {
                const quotaText = t.quota > 0 ? `Sisa Kuota: ${t.quota} tiket` : 'Kuota Habis';
                const quotaBadge = t.quota > 0 ? 'color:var(--text-muted);' : 'color:var(--danger);font-weight:700;';
                ttHtml += `
                    <div id="tt-${t.id}" class="card tt-card" style="padding:1.25rem;cursor:pointer;text-align:center;border:2px solid var(--border)" onclick="selectT(${t.id},${t.price})">
                        <h4 style="margin-bottom:.5rem">${escapeHtmlCust(t.name)}</h4>
                        <div style="color:var(--primary);font-size:1.4rem;font-weight:800">Rp ${Number(t.price).toLocaleString('id-ID')}</div>
                        <div style="font-size:0.85rem;margin-top:0.4rem;${quotaBadge};display:inline-flex;align-items:center;justify-content:center;gap:.35rem"><i data-lucide="${t.quota > 0 ? 'ticket' : 'circle-x'}" style="width:14px;height:14px;vertical-align:-2px"></i> ${quotaText}</div>
                    </div>
                `;
            });
        } else {
            ttHtml += '<p class="text-danger">Tiket belum tersedia untuk event ini.</p>';
        }
        ttHtml += '</div>';

        // Google Maps Integration Section
        const mapLatitude = ev.latitude || ev.venue?.latitude;
        const mapLongitude = ev.longitude || ev.venue?.longitude;
        const hasCoordinates = mapLatitude !== null && mapLatitude !== undefined && mapLatitude !== ''
            && mapLongitude !== null && mapLongitude !== undefined && mapLongitude !== '';

        let embedUrl = '';
        if (hasCoordinates) {
            embedUrl = `https://www.google.com/maps?q=${encodeURIComponent(mapLatitude + ',' + mapLongitude)}&z=15&output=embed`;
        } else if (ev.location_name || ev.venue?.name) {
            embedUrl = `https://www.google.com/maps?q=${encodeURIComponent(ev.location_name || ev.venue?.name)}&z=15&output=embed`;
        } else if (ev.google_maps_link || ev.venue?.google_maps_link) {
            embedUrl = ev.google_maps_link || ev.venue?.google_maps_link;
        }

        const directMapUrl = ev.google_maps_link || ev.venue?.google_maps_link
            || (hasCoordinates ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(mapLatitude + ',' + mapLongitude)}` : (ev.location_name ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(ev.location_name)}` : ''));

        let mapsSection = `
            <div class="card mt-4" style="padding:1.25rem;overflow:hidden">
                <div class="flex justify-between items-center mb-3" style="flex-wrap:wrap;gap:0.5rem">
                    <div>
                        <h3 style="margin:0;display:flex;align-items:center;gap:0.5rem"><i data-lucide="map-pin" style="width:20px;height:20px;color:var(--primary)"></i> Lokasi & Venue Konser</h3>
                        <p class="text-muted" style="margin:0;font-size:0.9rem">${escapeHtmlCust(ev.location_name || ev.venue?.name || 'Lokasi Belum Ditentukan')} - ${escapeHtmlCust(ev.venue?.address || '')}</p>
                    </div>
                    ${directMapUrl ? `<a href="${directMapUrl}" target="_blank" class="btn btn-outline btn-sm"><i data-lucide="map-pin" style="width:15px;height:15px"></i> Buka Google Maps Lengkap <i data-lucide="external-link" style="width:13px;height:13px"></i></a>` : ''}
                </div>
                ${embedUrl ? `
                    <div style="width:100%;height:320px;min-height:280px;border-radius:var(--radius);overflow:hidden;border:1px solid var(--border);position:relative;background:#eef2f7">
                        <iframe 
                            src="${embedUrl}" 
                            width="100%" 
                            height="100%" 
                            style="border:0" 
                            allowfullscreen="" 
                            loading="lazy" 
                            referrerpolicy="no-referrer-when-downgrade"
                            title="Lokasi venue konser">
                        </iframe>
                    </div>
                ` : '<p class="text-muted">Peta lokasi tidak tersedia untuk event ini.</p>'}
            </div>
        `;

        const bannerImg = ev.image || '/images/sheila_on_7.jpeg';

        document.getElementById('e-detail').innerHTML = `
            <div class="card mb-3" style="padding:0;overflow:hidden">
                <div style="height:220px;background:url('${bannerImg}') center/cover,linear-gradient(135deg,#6c3ce1,#3b82f6);position:relative"></div>
                <div style="padding:1.5rem">
                    <div class="flex justify-between items-center" style="flex-wrap:wrap;gap:1rem">
                        <div>
                            <h1 style="color:var(--primary);margin-bottom:.4rem;font-size:1.8rem">${escapeHtmlCust(ev.title)}</h1>
                            <p style="font-size:1rem;color:var(--text-dark)">
                                <b>${escapeHtmlCust(ev.venue?.name || '-')}</b> &nbsp;|&nbsp;
                                ${new Date(ev.start_date).toLocaleString('id-ID')}
                            </p>
                            <p class="text-muted mt-2" style="line-height:1.6">${escapeHtmlCust(ev.description || '')}</p>
                        </div>
                        <button class="btn btn-outline" onclick="Router.navigate('/customer/dashboard')"><i data-lucide="arrow-left" style="width:16px;height:16px"></i> Kembali</button>
                    </div>
                </div>
            </div>

            ${ttHtml}
            ${seats}
            ${mapsSection}

            <div class="card mt-4" style="padding:1.25rem;border:1px solid rgba(255,255,255,0.08)">
                <div class="flex justify-between items-center" style="flex-wrap:wrap;gap:0.75rem">
                    <div>
                        <div style="font-size:0.72rem;letter-spacing:.15em;text-transform:uppercase;color:var(--muted);margin-bottom:0.35rem">Ringkasan Pembayaran</div>
                        <h3 style="margin:0"><i data-lucide="banknote" style="width:19px;height:19px;vertical-align:-4px;color:var(--primary)"></i> Total Pembayaran</h3>
                    </div>
                    <span class="badge badge-purple">Midtrans Checkout</span>
                </div>
                <div class="mt-3" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:0.75rem">
                    <div>
                        <div style="font-size:0.78rem;color:var(--muted);margin-bottom:0.2rem">Harga Tiket</div>
                        <div id="selected-price-text" style="font-size:1.5rem;font-weight:800;color:var(--primary);line-height:1.2">Rp 0</div>
                    </div>
                    <div>
                        <div style="font-size:0.78rem;color:var(--muted);margin-bottom:0.2rem">Metode Pembayaran</div>
                        <div style="font-size:1.1rem;font-weight:700">Midtrans</div>
                    </div>
                </div>
            </div>

            <div id="seat-info" class="mt-3" style="font-size:.9rem"></div>
            <div style="text-align:right" class="mt-4">
                <button id="btn-checkout" class="btn btn-accent btn-lg" disabled onclick="openPayment()"><i data-lucide="credit-card" style="width:17px;height:17px"></i> Checkout Tiket Sekarang</button>
            </div>
        `;

        let selTT = null, selPrice = 0, selSeat = null, curOrder = null;

        window.pickSeat = (sid, seatLabel) => {
            console.log('Kursi dipilih:', sid, seatLabel);
            document.querySelectorAll('.seat').forEach(el => {
                el.style.borderColor = 'var(--border)';
                el.style.background = 'rgba(255,255,255,0.05)';
            });
            const chosen = document.querySelector(`.seat[data-sid="${sid}"]`);
            if (chosen) {
                chosen.style.borderColor = 'var(--success)';
                chosen.style.background = 'rgba(16,185,129,0.2)';
            }
            selSeat = sid;
            document.getElementById('seat-info').innerHTML = `<span style="color:var(--success);font-weight:600;display:inline-flex;align-items:center;gap:.35rem"><i data-lucide="circle-check" style="width:15px;height:15px"></i> Kursi ${seatLabel || '#' + sid} dipilih</span>`;
        };

        window.selectT = (id, price) => {
            document.querySelectorAll('.tt-card').forEach(el => {
                el.style.borderColor = 'var(--border)';
                el.style.background = 'transparent';
            });
            const chosen = document.getElementById('tt-' + id);
            if (chosen) {
                chosen.style.borderColor = 'var(--primary)';
                chosen.style.background = 'rgba(108,60,225,0.05)';
            }
            selTT = id;
            selPrice = price;
            const priceText = document.getElementById('selected-price-text');
            if (priceText) {
                priceText.innerText = 'Rp ' + Number(price).toLocaleString('id-ID');
            }
            const btn = document.getElementById('btn-checkout');
            if (btn) {
                btn.disabled = false;
                btn.classList.remove('btn-secondary');
                btn.classList.add('btn-accent');
                btn.innerHTML = '<i data-lucide="credit-card" style="width:17px;height:17px"></i> Checkout Tiket Sekarang (Rp ' + Number(price).toLocaleString('id-ID') + ')';
            }
        };

        window.openPayment = async () => {
            if (!selTT) {
                alert('Silakan pilih salah satu kategori tiket terlebih dahulu sebelum checkout.');
                return;
            }

            UI.showLoader();
            try {
                // 1. Buat order dulu
                const payload = {
                    event_id: Number(eid),
                    items: [{ ticket_type_id: Number(selTT), quantity: 1 }]
                };
                if (selSeat) payload.items[0].seat_ids = [Number(selSeat)];

                const orderResponse = await axios.post(API + '/orders', payload);
                curOrder = orderResponse.data.order.id;

                // 2. Langsung proses pembayaran Midtrans
                const paymentResponse = await axios.post(API + '/orders/' + curOrder + '/pay', {
                    gateway: 'midtrans'
                });

                UI.hideLoader();

                // 3. Buka Snap Midtrans langsung tanpa modal custom
                if (paymentResponse.data.demo_mode) {
                    // Demo mode - simulasi pembayaran
                    UI.hideLoader();

                    const demoPayment = confirm(
                        'DEMO MODE AKTIF\n\n' +
                        'Ini adalah simulasi pembayaran untuk testing.\n' +
                        'Dalam production, Anda akan melihat UI Midtrans yang asli.\n\n' +
                        'Klik OK untuk simulasi pembayaran berhasil, atau Cancel untuk batal.'
                    );

                    if (demoPayment) {
                        setTimeout(() => {
                            alert('[DEMO] Pembayaran berhasil!\n\nE-Ticket demo telah diterbitkan.\nSilakan cek menu "Tiket Saya".');
                            Router.navigate('/customer/tickets');
                        }, 1000);
                    }
                    return;
                }

                // 3. Lazy Load Midtrans Snap jika belum dimuat, lalu buka Snap.pay
                let snapToken = paymentResponse.data.token;
                if (!snapToken) {
                    UI.hideLoader();
                    alert('Gagal mendapatkan token pembayaran dari server.');
                    return;
                }

                // Fungsi helper lazy load snap.js
                const loadMidtransSnap = () => {
                    return new Promise((resolve, reject) => {
                        if (window.snap) {
                            return resolve(window.snap);
                        }
                        const isProd = document.querySelector('meta[name="midtrans-is-production"]')?.content === 'true';
                        const clientKey = document.querySelector('meta[name="midtrans-client-key"]')?.content || '';

                        const script = document.createElement('script');
                        script.src = isProd ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js';
                        if (clientKey) {
                            script.setAttribute('data-client-key', clientKey);
                        }
                        script.onload = () => {
                            if (window.snap) resolve(window.snap);
                            else reject(new Error('window.snap tidak tersedia setelah script dimuat.'));
                        };
                        script.onerror = () => reject(new Error('Gagal mengunduh Midtrans Snap. Periksa koneksi internet Anda.'));
                        document.head.appendChild(script);
                    });
                };

                try {
                    const snap = await loadMidtransSnap();
                    UI.hideLoader();

                    snap.pay(snapToken, {
                        onSuccess: async function (result) {
                            console.log('Payment success:', result);
                            try {
                                await axios.post(API + '/orders/' + curOrder + '/confirm-payment', {
                                    transaction_status: 'settlement',
                                    midtrans_result: result
                                });
                                alert('Pembayaran berhasil! E-Ticket Anda telah diterbitkan.\n\nSilakan cek menu "Tiket Saya".');
                            } catch (err) {
                                console.error('Error generating tickets:', err);
                                alert('Pembayaran berhasil!\n\nE-Ticket sedang diproses.');
                            }
                            Router.navigate('/customer/tickets');
                        },
                        onPending: function (result) {
                            console.log('Payment pending:', result);
                            alert('Pembayaran sedang diproses.');
                            Router.navigate('/customer/tickets');
                        },
                        onError: function (result) {
                            console.error('Payment error:', result);
                            alert('Pembayaran gagal: ' + (result.status_message || 'Terjadi kesalahan.'));
                        },
                        onClose: function () {
                            console.log('User closed Midtrans popup');
                        }
                    });
                } catch (loadErr) {
                    UI.hideLoader();
                    console.error('Midtrans lazy load error:', loadErr);
                    alert(loadErr.message + '\n\nSilakan coba beberapa saat lagi.');
                }
            } catch (error) {
                UI.hideLoader();
                console.error('Payment error:', error);

                let errorMessage = 'Terjadi kesalahan saat memproses pembayaran.';

                if (error.response?.status === 429) {
                    errorMessage = error.response.data?.message || 'Anda sedang di antrean. Silakan tunggu giliran Anda.';
                } else if (error.response?.status === 500) {
                    errorMessage = 'Server sedang mengalami gangguan. Silakan coba beberapa saat lagi.';
                } else if (error.response?.data?.message) {
                    errorMessage = error.response.data.message;
                } else if (error.message) {
                    errorMessage = error.message;
                }

                alert('⏳ Sesi Checkout / Antrean:\n\n' + errorMessage);
            }
        };

    } catch (err) {
        document.getElementById('e-detail').innerHTML = '<div class="card"><p class="text-danger text-center">Event konser tidak ditemukan.</p></div>';
    }
});

// === MY TICKETS ===
Router.add('/customer/tickets', async () => {
    UI.mount(`
        <div class="page-body">
            <div class="container">
                <div class="flex justify-between items-center mb-4">
                    <div>
                        <h2 style="margin:0"><i data-lucide="ticket" style="width:24px;height:24px;vertical-align:-5px;color:var(--primary)"></i> E-Ticket Saya</h2>
                        <p class="text-muted">Tunjukkan QR Code ini kepada petugas scanner saat masuk venue konser</p>
                    </div>
                    <button class="btn btn-outline" onclick="Router.navigate('/customer/dashboard')"><i data-lucide="arrow-left" style="width:16px;height:16px"></i> Cari Konser Lain</button>
                </div>
                <div id="t-list">
                    <div class="text-center p-4"><div class="spinner" style="margin:0 auto"></div></div>
                </div>
            </div>
        </div>
    `, true);

    try {
        const r = await axios.get(API + '/tickets');
        const tix = r.data.tickets || r.data.data || [];
        let h = '';

        if (!tix.length) {
            h = `
                <div class="card text-center p-4">
                    <p class="text-muted" style="font-size:1.1rem;margin-bottom:1rem">Anda belum memiliki tiket konser aktif.</p>
                    <button class="btn btn-primary" onclick="Router.navigate('/customer/dashboard')"><i data-lucide="search" style="width:16px;height:16px"></i> Eksplor Konser Sekarang</button>
                </div>
            `;
        } else {
            tix.forEach(t => {
                const od = t.order_detail || {};
                const tt = od.ticket_type || {};
                const ev = od.order?.event || tt.event || {};
                const venue = ev?.venue || {};

                const eventTitle = ev.title || 'Konser Musik';
                const eventLocation = ev.location_name || venue.name || venue.address || 'Venue Utama Konser';
                const eventVenueAddress = venue.address ? `(${venue.address})` : '';
                const eventDate = ev.start_date
                    ? new Date(ev.start_date).toLocaleString('id-ID', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' }) + ' WIB'
                    : '-';
                const eventDesc = ev.description || '';

                const ticketLatitude = ev?.latitude || venue?.latitude;
                const ticketLongitude = ev?.longitude || venue?.longitude;
                const mapLink = ev?.google_maps_link || venue?.google_maps_link
                    || (ticketLatitude && ticketLongitude
                        ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(ticketLatitude + ',' + ticketLongitude)}`
                        : (eventLocation !== '-' ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(eventLocation)}` : ''));

                h += `
                    <div class="card mb-3" style="display:flex;padding:0;overflow:hidden;flex-wrap:wrap">
                        <div style="padding:1.5rem;flex:2;min-width:250px;border-right:2px dashed var(--border)">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="badge badge-purple">${escapeHtmlCust(tt.name || 'Tiket')}</span>
                                ${ev?.category ? `<span class="badge badge-outline">${escapeHtmlCust(ev.category)}</span>` : ''}
                            </div>
                            <h2 style="margin-top:0.25rem;font-size:1.5rem;color:var(--primary);font-weight:800">${escapeHtmlCust(eventTitle)}</h2>
                            
                            <div style="margin-top:0.75rem;font-size:0.9rem;line-height:1.6">
                                <p style="margin:0.2rem 0;display:flex;align-items:center;gap:0.4rem">
                                    <span><i data-lucide="map-pin" style="width:15px;height:15px;color:var(--primary)"></i> <b>Lokasi / Venue:</b></span>
                                    <span>${escapeHtmlCust(eventLocation)} ${escapeHtmlCust(eventVenueAddress)}</span>
                                </p>
                                <p style="margin:0.2rem 0;display:flex;align-items:center;gap:0.4rem">
                                    <span><i data-lucide="calendar" style="width:15px;height:15px;color:var(--primary)"></i> <b>Waktu Event:</b></span>
                                    <span>${eventDate}</span>
                                </p>
                                ${eventDesc ? `<p class="text-muted" style="margin-top:0.5rem;font-size:0.85rem;line-height:1.4">${escapeHtmlCust(eventDesc)}</p>` : ''}
                            </div>

                            ${mapLink ? `<p style="font-size:0.85rem;margin-top:0.5rem"><a href="${mapLink}" target="_blank" style="color:var(--primary);font-weight:600;display:inline-flex;align-items:center;gap:4px"><i data-lucide="map-pin" style="width:14px;height:14px"></i> Buka Peta Venue (Google Maps) <i data-lucide="external-link" style="width:13px;height:13px"></i></a></p>` : ''}
                            ${od.seat ? `<p class="mt-2"><span class="badge badge-info"><i data-lucide="armchair" style="width:12px;height:12px;vertical-align:-2px"></i> Tempat Duduk: Section ${od.seat.section}</span></p>` : ''}
                            <p class="mt-3" style="margin-bottom:0;display:flex;align-items:center;gap:.4rem;flex-wrap:wrap"><i data-lucide="ticket-check" style="width:15px;height:15px;color:var(--primary)"></i> Status Tiket: <b class="${t.status === 'active' ? 'text-success' : 'text-muted'}">${t.status.toUpperCase()}</b></p>
                        </div>
                        <div style="padding:1.5rem;flex:1;min-width:220px;display:flex;flex-direction:column;align-items:center;justify-content:center;background:var(--bg-light)">
                            <div id="ticket-present-${t.id}" style="text-align:center;width:100%">
                                <div style="padding:1rem;background:#fff;border:1px dashed var(--primary);border-radius:12px;cursor:pointer;position:relative" onclick="openBarcodeModal(${t.id}, '${t.qr_code_image_url || t.barcode_image_url || 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' + (t.qr_code || 'TICKET-' + t.id)}')">
                                    <div style="filter:blur(5px);opacity:0.3;pointer-events:none;display:flex;justify-content:center">
                                        <img src="${t.qr_code_image_url || 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' + (t.qr_code || 'TICKET-' + t.id)}" style="width:120px;height:120px;object-fit:contain" alt="Barcode censored">
                                    </div>
                                    <div style="position:absolute;top:0;left:0;right:0;bottom:0;display:flex;flex-direction:column;align-items:center;justify-content:center;background:rgba(255,255,255,0.85);border-radius:12px">
                                        <i data-lucide="lock" style="width:28px;height:28px;color:var(--primary);margin-bottom:0.35rem"></i>
                                        <b style="font-size:0.85rem;color:var(--primary)">Klik untuk Pengajuan Barcode</b>
                                        <small class="text-muted" style="font-size:0.7rem">Ke Petugas (35 Detik)</small>
                                    </div>
                                </div>
                                <div class="mt-2 flex gap-1 justify-center">
                                    <a href="${t.qr_code_image_url || 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' + (t.qr_code || 'TICKET-' + t.id)}" download="tiket-${t.id}.png" target="_blank" class="btn btn-sm btn-outline" style="font-size:0.75rem;padding:0.35rem 0.6rem">
                                        <i data-lucide="download" style="width:13px;height:13px"></i> Unduh Barcode
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
        }
        document.getElementById('t-list').innerHTML = h;
    } catch (e) {
        document.getElementById('t-list').innerHTML = '<p class="text-danger text-center">Gagal memuat daftar tiket.</p>';
    }
});

window.barcodeModalTimer = null;

window.openBarcodeModal = async (ticketId, fallbackImg) => {
    // Inject modal structure if not exists
    let modalOverlay = document.getElementById('barcode-modal-overlay');
    if (!modalOverlay) {
        modalOverlay = document.createElement('div');
        modalOverlay.id = 'barcode-modal-overlay';
        modalOverlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.75);z-index:9999;display:flex;align-items:center;justify-content:center;padding:1rem;backdrop-filter:blur(4px);overflow-y:auto';
        document.body.appendChild(modalOverlay);
    }

    modalOverlay.style.display = 'flex';
    modalOverlay.innerHTML = `
        <div style="background:#fff;border-radius:16px;padding:1.15rem 1.15rem 0.9rem;max-width:350px;width:100%;text-align:center;box-shadow:0 20px 25px -5px rgba(0,0,0,0.3);position:relative;margin:auto">
            <button onclick="closeBarcodeModal()" style="position:absolute;top:9px;right:9px;border:none;background:rgba(0,0,0,0.06);border-radius:50%;width:30px;height:30px;cursor:pointer;display:flex;align-items:center;justify-content:center;z-index:20" title="Tutup"><i data-lucide="circle-x" style="width:17px;height:17px"></i></button>
            <h3 style="margin-top:0;margin-bottom:0.3rem;font-size:1.05rem;color:var(--text)"><i data-lucide="qr-code" style="width:17px;height:17px;vertical-align:-3px;color:var(--primary)"></i> Pengajuan Barcode Tiket</h3>
            <p class="text-muted" style="font-size:0.72rem;margin-bottom:0.7rem">Menyiapkan kode barcode aman untuk petugas...</p>
            <div id="modal-barcode-content">
                <div class="spinner" style="margin:1.25rem auto"></div>
            </div>
        </div>
    `;

    await startBarcodeCountdown(ticketId, fallbackImg);
};

window.closeBarcodeModal = () => {
    if (window.barcodeModalTimer) {
        clearInterval(window.barcodeModalTimer);
        window.barcodeModalTimer = null;
    }
    const modalOverlay = document.getElementById('barcode-modal-overlay');
    if (modalOverlay) {
        modalOverlay.style.display = 'none';
    }
};

window.startBarcodeCountdown = async (ticketId, fallbackImg) => {
    const container = document.getElementById('modal-barcode-content');
    if (!container) return;

    if (window.barcodeModalTimer) {
        clearInterval(window.barcodeModalTimer);
        window.barcodeModalTimer = null;
    }

    try {
        const response = await axios.post(API + '/tickets/' + ticketId + '/present');
        // Use ticket QR code or barcode as default identifier for maximum scanner recognition
        const rawCode = response.data.qr_code || response.data.barcode || response.data.scan_token;
        const qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=350x350&margin=10&ecc=M&data=' + encodeURIComponent(rawCode);

        // Dua waktu yang TERPISAH:
        // 1) Waktu tampil ke petugas = 35 detik (kunci otomatis demi keamanan tampilan)
        // 2) Masa berlaku tiket = mengikuti waktu event/konser (bukan hitungan detik/menit)
        const displayUntil = Date.now() + 35000;
        let validText = 'Berlaku hingga event selesai';
        if (response.data.expires_at) {
            const d = new Date(response.data.expires_at);
            validText = 'Berlaku s/d ' + d.toLocaleString('id-ID', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            }) + ' WIB (akhir event)';
        }

        const renderState = () => {
            const remainingSec = Math.max(0, Math.ceil((displayUntil - Date.now()) / 1000));

            if (remainingSec <= 0) {
                if (window.barcodeModalTimer) {
                    clearInterval(window.barcodeModalTimer);
                    window.barcodeModalTimer = null;
                }
                // Automatically close modal when35 seconds expire
                closeBarcodeModal();
                UI.toast('Waktu tampil (35 detik) habis — barcode terkunci. Buka kembali kapan saja; tiket tetap berlaku sampai akhir event.', 'warning');
                return;
            }

            container.innerHTML = `
                <div style="padding:0.8rem;background:#f8fafc;border:1px solid var(--border);border-radius:12px;text-align:center">
                    <div style="padding:10px;background:#fff;display:inline-block;border-radius:12px;margin-bottom:0.5rem;box-shadow:0 4px 10px rgba(0,0,0,0.08)">
                        <img src="${qrImageUrl}" style="width:165px;height:165px;object-fit:contain;display:block" alt="QR Barcode Tiket">
                    </div>
                    <code style="display:block;letter-spacing:1.5px;font-weight:700;font-size:0.88rem;color:var(--text);margin-bottom:0.5rem;word-break:break-all">${rawCode}</code>
                    <div style="font-weight:800;font-size:1.02rem;color:${remainingSec > 10 ? 'var(--primary)' : 'var(--danger)'};margin-bottom:0.25rem;display:flex;align-items:center;justify-content:center;gap:.35rem">
                        <i data-lucide="timer" style="width:17px;height:17px"></i> Sisa Waktu Tampil: ${remainingSec} Detik
                    </div>
                    <div style="font-weight:700;font-size:0.78rem;color:var(--success);margin-bottom:0.5rem;display:flex;align-items:center;justify-content:center;gap:.35rem;flex-wrap:wrap">
                        <i data-lucide="calendar-check" style="width:14px;height:14px;flex:none"></i> ${validText}
                    </div>
                    <div style="margin-bottom:0.4rem">
                        <a href="${qrImageUrl}" download="barcode-tiket-${ticketId}.png" target="_blank" class="btn btn-outline btn-sm" style="font-size:0.74rem">
                            <i data-lucide="download" style="width:13px;height:13px"></i> Unduh Gambar Barcode
                        </a>
                    </div>
                    <p class="text-muted" style="font-size:0.7rem;margin:0">Tunjukkan barcode ini langsung ke kamera scanner petugas gate. Kedaluwarsa tiket mengikuti waktu event — bukan waktu tampil ini.</p>
                </div>
            `;
        };

        renderState();
        window.barcodeModalTimer = setInterval(renderState, 1000);
    } catch (error) {
        container.innerHTML = `<p class="text-danger">${error.response?.data?.message || 'Gagal menyiapkan kode barcode tiket.'}</p>`;
    }
};

window.presentTicket = async ticketId => {
    openBarcodeModal(ticketId, '');
};

function escapeHtmlCust(str) {
    if (!str) return '';
    return String(str).replace(/[&<>'"]/g, tag => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        "'": '&#39;',
        '"': '&quot;'
    }[tag] || tag));
}
