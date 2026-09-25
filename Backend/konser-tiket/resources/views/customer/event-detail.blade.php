@extends('layouts.app')

@section('title', 'Event Details')

@push('styles')
<style>
    .event-header {
        background: linear-gradient(to right, rgba(9, 9, 11, 0.9), rgba(9, 9, 11, 0.4)), url('https://images.unsplash.com/photo-1540039155733-d6f1c348d400?q=80&w=1000') center/cover;
        padding: 4rem 2rem;
        border-radius: 16px;
        margin-bottom: 2rem;
        border: 1px solid var(--border-color);
    }
    
    .event-title {
        font-size: 2.5rem;
        color: var(--primary);
        margin-bottom: 0.5rem;
    }

    .ticket-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
    }

    .ticket-card {
        padding: 1.5rem;
        text-align: center;
        border: 1px solid var(--border-color);
        transition: transform 0.3s ease;
    }

    .ticket-card.selected {
        border-color: var(--primary);
        box-shadow: var(--glow-primary);
        transform: translateY(-5px);
    }

    .ticket-price {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--secondary);
        margin: 1rem 0;
    }

    /* Payment Modal Styles & Real Gateway Logos */
    .modal-overlay {
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.8);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 9999;
    }

    .payment-modal {
        background: var(--bg-card, #181512);
        padding: 2.5rem;
        border-radius: 16px;
        border: 1px solid var(--primary, #6c3ce1);
        width: 100%;
        max-width: 480px;
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5);
        color: #fff;
    }

    .payment-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        margin-bottom: 1.5rem;
    }

    .payment-card {
        cursor: pointer;
        border: 2px solid var(--border-color, #333);
        background: rgba(255,255,255,0.03);
        border-radius: 12px;
        padding: 1rem;
        text-align: center;
        transition: all 0.2s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
    }

    .payment-card:hover {
        border-color: var(--primary, #6c3ce1);
        background: rgba(108,60,225,0.08);
    }

    .payment-card.selected {
        border-color: var(--primary, #6c3ce1);
        background: rgba(108,60,225,0.2);
    }

    .payment-card img {
        width: 42px;
        height: 42px;
        object-fit: contain;
        background: #fff;
        padding: 4px;
        border-radius: 8px;
    }

    /* Seat Grid Styles */
    .seat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(40px, 1fr));
        gap: 8px;
        margin-bottom: 1rem;
    }
    .seat {
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 700;
        border-radius: 6px;
        border: 1px solid var(--border-color, #444);
        background: rgba(255,255,255,0.05);
        cursor: pointer;
        transition: all 0.2s;
    }
    .seat.available:hover {
        border-color: var(--primary, #6c3ce1);
        background: rgba(108,60,225,0.3);
    }
    .seat.booked {
        opacity: 0.3;
        cursor: not-allowed;
        background: #333;
    }
    .seat.selected-seat {
        border-color: #10b981 !important;
        background: rgba(16,185,129,0.3) !important;
        color: #10b981;
    }
</style>
@endpush

@section('content')
<div id="loading-state" class="text-center" style="padding: 5rem;">
    <div class="spinner" style="margin: 0 auto;"></div>
    <p class="mt-4 text-muted">Loading event details...</p>
</div>

<div id="event-content" style="display: none;">
    <div class="event-header">
        <h1 id="evt-title" class="event-title">Title</h1>
        <p id="evt-date" class="text-muted" style="font-size: 1.1rem;">Date</p>
        <p id="evt-venue" style="font-size: 1.1rem;">Venue</p>
    </div>

    <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
        <div style="flex: 2; min-width: 300px;">
            <h3>About This Event</h3>
            <p id="evt-desc" class="text-muted" style="white-space: pre-line;"></p>

            <div id="seat-map-section" class="mt-4" style="display:none;">
                <h3 class="mb-3">[ Pilihan Tempat Duduk / Spot ]</h3>
                <div id="seat-grid-container"></div>
                <div id="seat-info" class="mt-2" style="font-size:0.9rem; font-weight:600; color:var(--success);"></div>
            </div>
        </div>
        
        <div class="glass-panel" style="flex: 1; min-width: 300px; padding: 2rem;">
            <h3 style="margin-top:0;">Select Tickets</h3>
            <div id="ticket-list" class="ticket-grid" style="display:flex; flex-direction:column; gap:1rem;">
                <!-- Tickets loaded here -->
            </div>
            
            <hr style="border-color: var(--border-color); margin: 1.5rem 0;">
            
            <div style="display:flex; justify-content:space-between; margin-bottom: 1rem;">
                <span>Total:</span>
                <span id="cart-total" style="font-weight: bold; font-size: 1.2rem; color: var(--primary);">Rp 0</span>
            </div>
            
            <button id="checkout-btn" class="btn btn-primary" style="width:100%" onclick="checkout()" disabled>Checkout tiket sekarang</button>
        </div>
    </div>
</div>

<!-- Simulated Payment Gateway Modal with Real SVG Logo URLs -->
<div id="payment-modal" class="modal-overlay">
    <div class="payment-modal">
        <div class="flex justify-between items-center mb-4">
            <h2 style="margin:0">[ Pilih Metode Pembayaran ]</h2>
            <button class="btn btn-sm btn-outline" onclick="closePayment()" style="background:transparent; color:#fff; border:none; font-size:1.2rem; cursor:pointer;">✕</button>
        </div>
        <p class="text-muted mb-4">Total Tagihan: <b id="pay-amount" style="color:#10b981; font-size:1.2rem">Rp 0</b></p>
        
        <div class="payment-grid">
            <div class="payment-card" onclick="selectPaymentGateway('GoPay', this)">
                <img src="https://upload.wikimedia.org/wikipedia/commons/8/86/Gopay_logo.svg" alt="GoPay">
                <span style="font-weight:600">GoPay</span>
            </div>
            <div class="payment-card" onclick="selectPaymentGateway('OVO', this)">
                <img src="https://upload.wikimedia.org/wikipedia/commons/e/eb/Logo_ovo_purple.svg" alt="OVO">
                <span style="font-weight:600">OVO</span>
            </div>
            <div class="payment-card" onclick="selectPaymentGateway('DANA', this)">
                <img src="https://upload.wikimedia.org/wikipedia/commons/7/72/Logo_dana_blue.svg" alt="DANA">
                <span style="font-weight:600">DANA</span>
            </div>
            <div class="payment-card" onclick="selectPaymentGateway('ShopeePay', this)">
                <img src="https://upload.wikimedia.org/wikipedia/commons/f/fe/ShopeePay_logo.svg" alt="ShopeePay">
                <span style="font-weight:600">ShopeePay</span>
            </div>
            <div class="payment-card" onclick="selectPaymentGateway('QRIS', this)">
                <img src="https://upload.wikimedia.org/wikipedia/commons/a/a2/QRIS_logo.svg" alt="QRIS">
                <span style="font-weight:600">QRIS Instant</span>
            </div>
            <div class="payment-card" onclick="selectPaymentGateway('Credit Card', this)">
                <img src="https://upload.wikimedia.org/wikipedia/commons/b/b5/PayPal.svg" alt="Card">
                <span style="font-weight:600">Kartu Kredit</span>
            </div>
        </div>

        <button id="btn-pay-confirm" class="btn btn-primary" style="width: 100%; padding: 0.75rem; font-weight:700;" disabled onclick="processPayment()">Konfirmasi & Bayar Sekarang</button>
        <button class="btn btn-outline" style="width: 100%; margin-top: 0.5rem; border:none; background:transparent; color:#aaa;" onclick="closePayment()">Batalkan</button>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const eventId = {{ $id }};
    let selectedTicketId = null;
    let selectedPrice = 0;
    let selectedSeatId = null;
    let selectedGateway = null;
    let currentOrderId = null;

    document.addEventListener('DOMContentLoaded', () => {
        loadEventDetails();
    });

    function loadEventDetails() {
        axios.get(`${API_URL}/events/${eventId}`)
            .then(res => {
                const event = res.data.event;
                
                document.getElementById('evt-title').textContent = event.title;
                document.getElementById('evt-desc').textContent = event.description;
                document.getElementById('evt-date').textContent = new Date(event.start_date).toLocaleString();
                document.getElementById('evt-venue').innerHTML = `[ Lokasi ] ${event.venue.name} <br> <small class="text-muted">${event.venue.address}</small>`;
                
                // Load Seats
                if (event.venue && event.venue.id) {
                    loadSeats(event.venue.id);
                }

                // Render Tickets
                const tList = document.getElementById('ticket-list');
                tList.innerHTML = '';
                
                if(!event.ticket_types || event.ticket_types.length === 0) {
                    tList.innerHTML = '<p class="text-danger">Belum ada tiket tersedia.</p>';
                } else {
                    event.ticket_types.forEach(t => {
                        const isSoldOut = t.quota <= 0;
                        tList.innerHTML += `
                            <div class="glass-panel ticket-card ${isSoldOut ? 'sold-out' : ''}" 
                                 id="tcard-${t.id}"
                                 ${!isSoldOut ? `onclick="selectTicket(${t.id}, ${t.price})"` : ''}
                                 style="${isSoldOut ? 'opacity:0.5; cursor:not-allowed;' : 'cursor:pointer;'}">
                                <div style="font-weight:bold;">${t.name}</div>
                                <div class="ticket-price">Rp ${parseFloat(t.price).toLocaleString('id-ID')}</div>
                                <div class="text-muted" style="font-size:0.8rem;">Sisa: ${t.quota}</div>
                            </div>
                        `;
                    });
                }

                document.getElementById('loading-state').style.display = 'none';
                document.getElementById('event-content').style.display = 'block';
            })
            .catch(err => {
                document.getElementById('loading-state').innerHTML = '<p class="text-danger">Event tidak ditemukan.</p>';
            });
    }

    function loadSeats(venueId) {
        axios.get(`${API_URL}/venues/${venueId}/seats?event_id=${eventId}`)
            .then(res => {
                const seats = res.data.seats || [];
                if (seats.length > 0) {
                    document.getElementById('seat-map-section').style.display = 'block';
                    const container = document.getElementById('seat-grid-container');
                    
                    let sections = {};
                    seats.forEach(st => {
                        if (!sections[st.section]) sections[st.section] = [];
                        sections[st.section].push(st);
                    });

                    let html = '';
                    for (let sec in sections) {
                        html += `<p class="mb-1" style="font-weight:600; font-size:0.9rem;">${sec}</p><div class="seat-grid mb-3">`;
                        sections[sec].forEach(st => {
                            const cls = st.is_available ? 'available' : 'booked';
                            html += `<div class="seat ${cls}" data-sid="${st.id}" ${st.is_available ? `onclick="pickSeat(${st.id}, this)"` : ''} title="${st.row}-${st.seat_number}">${st.row}${st.seat_number}</div>`;
                        });
                        html += '</div>';
                    }
                    container.innerHTML = html;
                }
            })
            .catch(e => console.error('Failed to load seats', e));
    }

    function pickSeat(sid, el) {
        document.querySelectorAll('.seat').forEach(s => s.classList.remove('selected-seat'));
        el.classList.add('selected-seat');
        selectedSeatId = sid;
        document.getElementById('seat-info').textContent = `[ Terpilih ] Kursi #${sid}`;
    }

    function selectTicket(id, price) {
        document.querySelectorAll('.ticket-card').forEach(el => el.classList.remove('selected'));
        document.getElementById(`tcard-${id}`).classList.add('selected');
        selectedTicketId = id;
        selectedPrice = price;
        
        document.getElementById('cart-total').textContent = `Rp ${parseFloat(price).toLocaleString('id-ID')}`;
        document.getElementById('checkout-btn').disabled = false;
    }

    function selectPaymentGateway(gw, el) {
        document.querySelectorAll('.payment-card').forEach(c => c.classList.remove('selected'));
        el.classList.add('selected');
        selectedGateway = gw;
        const btn = document.getElementById('btn-pay-confirm');
        btn.disabled = false;
        btn.textContent = `Bayar via ${gw} Sekarang`;
    }

    // --- Checkout Flow (UC7) ---
    function checkout() {
        if(!selectedTicketId) return;
        
        UI.showLoader();
        
        const payload = {
            event_id: eventId,
            tickets: [
                { ticket_type_id: selectedTicketId, quantity: 1 }
            ]
        };
        if(selectedSeatId) {
            payload.tickets[0].seat_ids = [selectedSeatId];
        }

        axios.post(`${API_URL}/orders`, payload)
        .then(res => {
            UI.hideLoader();
            currentOrderId = res.data.order.id;
            
            // Open Payment Modal
            document.getElementById('pay-amount').textContent = `Rp ${parseFloat(selectedPrice).toLocaleString('id-ID')}`;
            document.getElementById('payment-modal').style.display = 'flex';
        })
        .catch(err => {
            UI.hideLoader();
            alert('Gagal melakukan checkout: ' + (err.response?.data?.message || 'Error'));
        });
    }

    function closePayment() {
        document.getElementById('payment-modal').style.display = 'none';
    }

    // --- Payment Flow (UC8) ---
    function processPayment() {
        if(!currentOrderId || !selectedGateway) {
            alert('Silakan pilih metode pembayaran terlebih dahulu.');
            return;
        }
        
        UI.showLoader();
        
        axios.post(`${API_URL}/orders/${currentOrderId}/pay`, {
            gateway: selectedGateway,
            status: 'success'
        })
        .then(res => {
            const transactionId = res.data.transaction_id;
            return axios.post(`${API_URL}/webhook/payment`, {
                transaction_id: transactionId,
                status: 'success'
            });
        })
        .then(res => {
            UI.hideLoader();
            alert('Pembayaran Berhasil! E-Ticket Anda telah diterbitkan.');
            window.location.href = '/customer/tickets';
        })
        .catch(err => {
            UI.hideLoader();
            alert('Pembayaran gagal diproses: ' + (err.response?.data?.message || 'Error'));
        });
    }
</script>
@endpush
