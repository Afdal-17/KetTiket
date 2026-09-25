@extends('layouts.app')

@section('title', 'Customer Area - KetTiket')

@push('styles')
<script src="{{ asset('js/lucide.min.js') }}"></script>
<style>
    :root {
      --canvas: #FAF8F5;
      --surface: #FFFFFF;
      --surface-subtle: #F2EFE9;
      --ink: #1C1917;
      --ink-muted: #78716C;
      --border: #E7E5E4;
      --accent: #E11D48;
      --accent-soft: #FFF1F2;
      --terracotta: #C2410C;
    }

    .dashboard-container {
        font-family: 'Inter', sans-serif;
        color: var(--ink);
        padding-top: 2rem;
        padding-bottom: 5rem;
    }

    .font-serif-custom {
        font-family: 'Instrument Serif', serif;
    }

    .hairline-b {
        border-bottom: 1px solid var(--border);
    }

    .card-soft {
        background-color: var(--surface);
        border: 1px solid var(--border);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02), 0 10px 20px -5px rgba(28, 25, 23, 0.03);
        border-radius: 1.5rem;
        padding: 1.5rem;
    }
    
    .card-hover {
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }
    
    .card-hover:hover {
        border-color: #D6D3D1;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04), 0 20px 30px -10px rgba(28, 25, 23, 0.05);
    }

    .brutalist-btn {
        padding: 0.5rem 1.5rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        border: 1px solid var(--border);
        background: var(--surface);
        color: var(--ink);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .brutalist-btn:hover {
        background: var(--surface-subtle);
    }

    .brutalist-btn-primary {
        background: var(--ink);
        color: var(--surface);
        border-color: var(--ink);
    }

    .brutalist-btn-primary:hover {
        background: #292524;
    }

    .tabs {
        display: flex;
        gap: 2rem;
        margin-bottom: 2rem;
    }
    .tab {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--ink-muted);
        cursor: pointer;
        padding-bottom: 0.25rem;
        border-bottom: 2px solid transparent;
        transition: all 0.3s;
    }
    .tab.active {
        color: var(--ink);
        border-bottom-color: var(--terracotta);
    }

    .tab-content { display: none; }
    .tab-content.active { display: block; }

    /* E-Ticket Styles from desain soft.html */
    .e-ticket-box {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 1.5rem;
        padding: 2rem;
        position: relative;
        overflow: hidden;
        margin-bottom: 1.5rem;
    }

    /* Minimalist QR Code Placeholder */
    .qr-minimal {
        width: 100px;
        height: 100px;
        background: var(--surface-subtle);
        border: 1px solid var(--border);
        border-radius: 0.75rem;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 0.5rem;
    }
    
    .event-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
    }

</style>
@endpush

@section('content')
<div class="dashboard-container">
    <header class="hairline-b pb-6 mb-8">
        <div>
            <h1 class="font-serif-custom text-4xl sm:text-5xl italic tracking-tight block font-normal" style="color: var(--ink); margin:0;">KetTiket.</h1>
            <span class="text-xs font-medium tracking-widest uppercase block mt-2" style="color: var(--ink-muted);">Editorial Concert & Culture Ticketing</span>
        </div>
        <div style="margin-top: 1rem;">
            Halo, <span id="user-name" style="font-weight: 600;">Customer</span>
        </div>
    </header>

    <div class="tabs hairline-b pb-0">
        <div class="tab active" onclick="switchTab('explore')">Eksplorasi</div>
        <div class="tab" onclick="switchTab('orders')">Pesanan Saya</div>
        <div class="tab" onclick="switchTab('tickets')">E-Ticket Saya</div>
    </div>

    <!-- Tab: Explore -->
    <div id="tab-explore" class="tab-content active">
        <div id="event-grid" class="event-grid">
            <div style="color: var(--ink-muted); font-size: 0.875rem;">Loading events...</div>
        </div>
    </div>

    <!-- Tab: Orders (For testing payment success) -->
    <div id="tab-orders" class="tab-content">
        <div id="order-list">
            <div style="color: var(--ink-muted); font-size: 0.875rem;">Loading orders...</div>
        </div>
    </div>

    <!-- Tab: E-Tickets -->
    <div id="tab-tickets" class="tab-content">
        <div id="ticket-list" style="max-width: 800px;">
            <div style="color: var(--ink-muted); font-size: 0.875rem;">Loading tickets...</div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        if(typeof lucide !== 'undefined') lucide.createIcons();
        
        // Mock Auth check if needed, depending on how your app.js handles it
        try {
            const user = Auth.getUser();
            if(user) document.getElementById('user-name').textContent = user.name;
        } catch(e){}

        loadEvents();
    });

    function switchTab(tabId) {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        
        event.target.classList.add('active');
        document.getElementById('tab-' + tabId).classList.add('active');
        
        if(tabId === 'explore') loadEvents();
        if(tabId === 'orders') loadOrders();
        if(tabId === 'tickets') loadTickets();
    }

    async function loadEvents() {
        try {
            const res = await axios.get(`${API_URL}/events`);
            const events = res.data.events || res.data.data || [];
            const grid = document.getElementById('event-grid');
            
            if(events.length === 0) {
                grid.innerHTML = '<div style="color: var(--ink-muted);">Belum ada konser tersedia.</div>';
                return;
            }

            grid.innerHTML = events.map(ev => `
                <div class="card-soft card-hover" style="display:flex; flex-direction:column; justify-content:space-between;">
                    <div>
                        <div style="font-size:0.65rem; color:var(--accent); font-weight:600; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:0.25rem;">
                            Tahap Penjualan
                        </div>
                        <h3 class="font-serif-custom text-2xl" style="margin-bottom:0.5rem; line-height:1.1;">${ev.title}</h3>
                        <div style="font-size:0.75rem; color:var(--ink-muted); margin-bottom:1.5rem; line-height:1.4; display:flex; flex-direction:column; gap:0.25rem;">
                            <span style="display:flex; align-items:center; gap:0.35rem;"><i data-lucide="calendar" style="width:14px; height:14px;"></i> ${new Date(ev.start_date).toLocaleDateString()}</span>
                            <span style="display:flex; align-items:center; gap:0.35rem;"><i data-lucide="map-pin" style="width:14px; height:14px;"></i> ${ev.venue ? ev.venue.name : 'TBA'}</span>
                        </div>
                    </div>
                    <button class="brutalist-btn brutalist-btn-primary" style="width:100%" onclick="window.location.href='/#/customer/events/${ev.id}'">Pesan & Pilih Kursi</button>
                </div>
            `).join('');
        } catch(e) {
            document.getElementById('event-grid').innerHTML = '<div style="color:var(--accent);">Gagal memuat konser.</div>';
        }
    }

    async function loadOrders() {
        const container = document.getElementById('order-list');
        container.innerHTML = '<div style="color: var(--ink-muted);">Loading orders...</div>';
        try {
            const res = await axios.get(`${API_URL}/orders`);
            const orders = res.data.orders || [];
            
            if(orders.length === 0) {
                container.innerHTML = '<div style="color: var(--ink-muted);">Anda belum memiliki pesanan.</div>';
                return;
            }

            container.innerHTML = orders.map(ord => {
                let simButton = '';
                if(ord.status === 'pending') {
                    // Logic to simulate payment webhook if there's a payment record, or we just call the simulate endpoint directly
                    // Since the backend creates a payment via pay(), we should let them initiate payment first if they haven't
                    const payUrl = (ord.payment && ord.payment.transaction_id) 
                        ? `<button class="brutalist-btn brutalist-btn-primary" onclick="simulateWebhook('${ord.payment.transaction_id}')">Simulasi Bayar Sukses</button>
                           <button class="brutalist-btn brutalist-btn-danger" onclick="simulateWebhookFail('${ord.payment.transaction_id}')">Simulasi Bayar Gagal</button>`
                        : `<button class="brutalist-btn brutalist-btn-primary" onclick="initiatePayment(${ord.id})">Bayar Sekarang (Initiate)</button>`;
                    
                    simButton = `<div style="margin-top:1rem; padding-top:1rem; border-top:1px dashed var(--border); display:flex; gap:0.5rem;">${payUrl}</div>`;
                }

                return `
                    <div class="card-soft mb-4">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                            <div>
                                <h4 class="font-serif-custom text-xl">Order #${ord.id} - ${ord.event ? ord.event.title : ''}</h4>
                                <p style="font-size:0.75rem; color:var(--ink-muted); margin: 0.25rem 0;">Total: Rp ${parseInt(ord.total).toLocaleString('id-ID')}</p>
                            </div>
                            <span style="font-size:0.65rem; background:var(--surface-subtle); padding:0.2rem 0.5rem; border-radius:1rem; font-weight:600; text-transform:uppercase;">${ord.status}</span>
                        </div>
                        ${simButton}
                    </div>
                `;
            }).join('');
        } catch(e) {
            container.innerHTML = '<div style="color:var(--accent);">Gagal memuat pesanan.</div>';
        }
    }

    async function loadTickets() {
        const container = document.getElementById('ticket-list');
        container.innerHTML = '<div style="color: var(--ink-muted);">Loading tickets...</div>';
        try {
            const res = await axios.get(`${API_URL}/tickets`);
            const tickets = res.data.data || res.data.tickets || [];
            
            if(tickets.length === 0) {
                container.innerHTML = '<div style="color: var(--ink-muted);">Anda belum memiliki E-Ticket.</div>';
                return;
            }

            container.innerHTML = tickets.map(ticket => {
                const eventTitle = ticket.order_detail?.ticket_type?.event?.title || 'Konser';
                const ticketType = ticket.order_detail?.ticket_type?.name || 'Reguler';
                const venue = ticket.order_detail?.ticket_type?.event?.venue?.name || 'Venue';
                const dateRaw = ticket.order_detail?.ticket_type?.event?.start_date;
                const date = dateRaw ? new Date(dateRaw).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : 'TBA';
                
                return `
                    <div class="e-ticket-box">
                        <div class="hairline-b pb-4 mb-4" style="display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <span class="font-serif-custom text-2xl text-stone-900 block">KetTiket Admission</span>
                                <span class="text-[10px] text-stone-400 tracking-wider uppercase" style="font-size: 0.65rem; color: var(--ink-muted);">Konfirmasi Pembelian Resmi</span>
                            </div>
                            <span style="font-size:0.65rem; background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; padding:0.2rem 0.5rem; border-radius:1rem; font-weight:600; text-transform:uppercase;">
                                ${ticket.status === 'active' ? 'Terverifikasi' : ticket.status}
                            </span>
                        </div>

                        <div style="display:grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items:center;">
                            <div>
                                <span style="font-size:0.65rem; font-weight:600; color:var(--ink-muted); text-transform:uppercase; letter-spacing:0.05em;">Konser</span>
                                <h3 class="text-xl font-semibold mb-4" style="line-height:1.2; margin-top:0.25rem;">${eventTitle}</h3>
                                
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; font-size:0.75rem;">
                                    <div>
                                        <span style="display:block; font-size:0.65rem; color:var(--ink-muted);">WAKTU & TANGGAL</span>
                                        <span style="font-weight:500;">${date}</span>
                                    </div>
                                    <div>
                                        <span style="display:block; font-size:0.65rem; color:var(--ink-muted);">LOKASI VENUE</span>
                                        <span style="font-weight:500;">${venue}</span>
                                    </div>
                                    <div style="grid-column: span 2;">
                                        <span style="display:block; font-size:0.65rem; color:var(--ink-muted);">KATEGORI</span>
                                        <span style="font-weight:600; color:var(--accent);">${ticketType}</span>
                                    </div>
                                </div>
                            </div>

                            <div style="text-align:center; padding-left:1.5rem; border-left:1px solid var(--border);">
                                <div class="qr-minimal">
                                    <i data-lucide="qr-code" style="width:50px; height:50px; color:var(--ink);"></i>
                                </div>
                                <div style="font-family:monospace; font-size:0.65rem; color:var(--ink-muted);">${ticket.barcode}</div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
            if(typeof lucide !== 'undefined') lucide.createIcons();
        } catch(e) {
            container.innerHTML = '<div style="color:var(--accent);">Gagal memuat tiket.</div>';
        }
    }

    async function initiatePayment(orderId) {
        try {
            await axios.post(`${API_URL}/orders/${orderId}/pay`, { gateway: 'Simulator' });
            alert('Pembayaran diinisiasi! Silakan simulasi webhook sekarang.');
            loadOrders();
        } catch(e) {
            alert('Gagal inisiasi pembayaran: ' + (e.response?.data?.message || 'Error'));
        }
    }

    async function simulateWebhook(transactionId) {
        try {
            await axios.post(`${API_URL}/webhook/payment`, {
                transaction_id: transactionId,
                status: 'success'
            });
            alert('Webhook sukses diterima! E-Ticket berhasil di-generate.');
            loadOrders();
        } catch(e) {
            alert('Gagal memproses webhook: ' + (e.response?.data?.message || 'Error'));
        }
    }

    async function simulateWebhookFail(transactionId) {
        try {
            await axios.post(`${API_URL}/webhook/payment`, {
                transaction_id: transactionId,
                status: 'failed'
            });
            alert('Webhook gagal diterima! Order dibatalkan dan kuota dikembalikan.');
            loadOrders();
        } catch(e) {
            alert('Gagal memproses webhook: ' + (e.response?.data?.message || 'Error'));
        }
    }
</script>
@endpush
