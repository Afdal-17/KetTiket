@extends('layouts.app')

@section('title', 'Organizer Dashboard - KetTiket')

@push('styles')
<script src="{{ asset('js/lucide.min.js') }}"></script>
<style>
    /* Soft Brutalist / Minimalist Theme Overrides */
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

    /* Reset some layout app styles just for this dashboard container if needed */
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
        padding: 0.5rem 1rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
        border: 1px solid var(--border);
        background: var(--surface);
        color: var(--ink);
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

    .brutalist-btn-danger {
        color: var(--accent);
        border-color: var(--accent-soft);
        background: var(--accent-soft);
    }

    .brutalist-input {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid var(--border);
        border-radius: 0.75rem;
        background: var(--surface-subtle);
        color: var(--ink);
        font-family: 'Inter', sans-serif;
        font-size: 0.875rem;
        margin-top: 0.25rem;
    }

    .brutalist-input:focus {
        outline: none;
        border-color: var(--ink-muted);
        background: var(--surface);
    }

    /* Tabs */
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

    /* Tab Content Panels */
    .tab-content {
        display: none;
    }
    .tab-content.active {
        display: block;
    }

    /* Modal / Drawer for Forms */
    .brutalist-modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(28, 25, 23, 0.5);
        backdrop-filter: blur(4px);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }
    .brutalist-modal.active {
        display: flex;
    }
    .modal-content-box {
        background: var(--surface);
        width: 100%;
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
        border-radius: 1.5rem;
        padding: 2rem;
        position: relative;
    }

    .ai-insight-box {
        background: var(--surface-subtle);
        border: 1px solid var(--border);
        border-radius: 1rem;
        padding: 1.5rem;
        font-family: monospace;
        font-size: 0.85rem;
        white-space: pre-wrap;
        color: var(--ink);
        margin-top: 1rem;
    }

    .ticket-type-row {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
        align-items: end;
    }
</style>
@endpush

@section('content')
<div class="dashboard-container">
    <header class="hairline-b pb-6 mb-8 flex items-end justify-between">
        <div>
            <h1 class="font-serif-custom text-4xl sm:text-5xl italic tracking-tight block font-normal" style="color: var(--ink); margin:0;">Dashboard.</h1>
            <span class="text-xs font-medium tracking-widest uppercase block mt-2" style="color: var(--ink-muted);">Editorial Organizer Space</span>
        </div>
        <div>
            <button class="brutalist-btn brutalist-btn-primary" onclick="openEventModal()">+ Buat Event Baru</button>
        </div>
    </header>

    <!-- Unified Tabs -->
    <div class="tabs hairline-b pb-0">
        <div class="tab active" onclick="switchTab('events')">Kelola Konser</div>
        <div class="tab" onclick="switchTab('reports')">Laporan & AI Insights</div>
    </div>

    <!-- Tab 1: Events List -->
    <div id="tab-events" class="tab-content active">
        <div id="events-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;">
            <div style="color: var(--ink-muted); font-size: 0.875rem;">Loading events...</div>
        </div>
    </div>

    <!-- Tab 2: Reports & Insights -->
    <div id="tab-reports" class="tab-content">
        <div class="card-soft mb-6">
            <h2 class="font-serif-custom text-2xl mb-2">AI Event Analysis</h2>
            <p style="font-size: 0.875rem; color: var(--ink-muted); margin-bottom: 1rem;">
                Pilih event di bawah untuk menghasilkan laporan penjualan atau tren menggunakan AI.
            </p>
            <select id="ai-event-select" class="brutalist-input" style="max-width: 300px; margin-bottom: 1rem;">
                <option value="">Pilih Event...</option>
            </select>
            
            <div style="display: flex; gap: 0.5rem;">
                <button class="brutalist-btn" onclick="generateAiReport('analyze-sales')">Analisa Penjualan</button>
                <button class="brutalist-btn" onclick="generateAiReport('analyze-trends')">Analisa Tren Audiens</button>
            </div>
            
            <div id="ai-report-output" class="ai-insight-box" style="display: none;"></div>
        </div>
    </div>

    <!-- Unified Modal for Create/Edit Event -->
    <div id="eventModal" class="brutalist-modal">
        <div class="modal-content-box">
            <button onclick="closeEventModal()" style="position:absolute; right:1.5rem; top:1.5rem; background:none; border:none; cursor:pointer;">
                <i data-lucide="x" class="w-5 h-5" style="color:var(--ink-muted);"></i>
            </button>
            <h2 id="modal-title" class="font-serif-custom text-3xl mb-6">Buat Konser Baru</h2>
            
            <form id="event-form" onsubmit="submitEvent(event)">
                <input type="hidden" id="event_id">
                
                <div style="margin-bottom: 1rem;">
                    <label style="font-size: 0.75rem; font-weight: 600; color: var(--ink-muted); text-transform:uppercase;">Judul Konser</label>
                    <input type="text" id="event_title" class="brutalist-input" required>
                </div>

                <div style="margin-bottom: 1rem;">
                    <label style="font-size: 0.75rem; font-weight: 600; color: var(--ink-muted); text-transform:uppercase;">Venue</label>
                    <select id="event_venue_id" class="brutalist-input" required>
                        <option value="">Loading venues...</option>
                    </select>
                </div>

                <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                    <div style="flex:1;">
                        <label style="font-size: 0.75rem; font-weight: 600; color: var(--ink-muted); text-transform:uppercase;">Mulai</label>
                        <input type="datetime-local" id="event_start" class="brutalist-input" required>
                    </div>
                    <div style="flex:1;">
                        <label style="font-size: 0.75rem; font-weight: 600; color: var(--ink-muted); text-transform:uppercase;">Selesai</label>
                        <input type="datetime-local" id="event_end" class="brutalist-input" required>
                    </div>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label style="font-size: 0.75rem; font-weight: 600; color: var(--ink-muted); text-transform:uppercase;">Deskripsi</label>
                    <textarea id="event_description" class="brutalist-input" rows="3" required></textarea>
                </div>

                <div class="hairline-b mb-4"></div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                    <h3 class="font-serif-custom text-xl">Kategori Tiket</h3>
                    <button type="button" class="brutalist-btn" onclick="addTicketRow()">+ Tambah</button>
                </div>

                <div id="ticket-rows-container">
                    <!-- Ticket rows will be added here -->
                </div>

                <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                    <button type="submit" class="brutalist-btn brutalist-btn-primary" style="flex:1; padding: 0.75rem;">Simpan Konser & Tiket</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        if(typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
        loadEvents();
        loadVenues();
        addTicketRow(); // Add one initial ticket row
    });

    function switchTab(tabId) {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        
        event.target.classList.add('active');
        document.getElementById('tab-' + tabId).classList.add('active');
        
        if (tabId === 'reports') {
            populateAiEventSelect();
        }
    }

    let allEvents = [];
    let venuesList = [];

    async function loadEvents() {
        try {
            const res = await axios.get(`${API_URL}/organizer/events`);
            allEvents = res.data.events || res.data.data || [];
            renderEventsGrid();
        } catch (e) {
            document.getElementById('events-grid').innerHTML = '<div style="color:var(--accent);">Gagal memuat event.</div>';
        }
    }

    async function loadVenues() {
        try {
            const res = await axios.get(`${API_URL}/venues`);
            venuesList = res.data.venues || res.data.data || [];
            const select = document.getElementById('event_venue_id');
            select.innerHTML = '<option value="">Pilih Venue...</option>';
            venuesList.forEach(v => {
                select.innerHTML += `<option value="${v.id}">${v.name}</option>`;
            });
        } catch(e) {}
    }

    function renderEventsGrid() {
        const grid = document.getElementById('events-grid');
        if(allEvents.length === 0) {
            grid.innerHTML = '<div style="color:var(--ink-muted);">Belum ada konser.</div>';
            return;
        }

        grid.innerHTML = allEvents.map(ev => `
            <div class="card-soft card-hover flex flex-col justify-between" style="display:flex; flex-direction:column; justify-content:space-between;">
                <div>
                    <div style="display:flex; justify-content:space-between; margin-bottom: 0.5rem;">
                        <span style="font-size:0.65rem; background:var(--surface-subtle); padding:0.2rem 0.5rem; border-radius:1rem; font-weight:600; text-transform:uppercase;">${ev.status}</span>
                        <span style="font-size:0.75rem; color:var(--ink-muted); font-family:monospace;">${new Date(ev.start_date).toLocaleDateString()}</span>
                    </div>
                    <h3 class="font-serif-custom text-2xl" style="margin-bottom:0.5rem;">${ev.title}</h3>
                    <p style="font-size:0.75rem; color:var(--ink-muted); margin-bottom:1.5rem; line-height:1.4;">
                        ${ev.description ? ev.description.substring(0, 80) + '...' : ''}
                    </p>
                </div>
                <div style="display:flex; gap:0.5rem; border-top:1px solid var(--border); padding-top:1rem;">
                    <button class="brutalist-btn" style="flex:1;" onclick="editEvent(${ev.id})">Edit</button>
                    <button class="brutalist-btn brutalist-btn-danger" onclick="deleteEvent(${ev.id})">Hapus</button>
                </div>
            </div>
        `).join('');
    }

    function populateAiEventSelect() {
        const select = document.getElementById('ai-event-select');
        select.innerHTML = '<option value="">Pilih Event...</option>' + allEvents.map(ev => `<option value="${ev.id}">${ev.title}</option>`).join('');
    }

    async function generateAiReport(endpoint) {
        const eventId = document.getElementById('ai-event-select').value;
        if(!eventId) return alert('Silakan pilih event terlebih dahulu');
        
        const output = document.getElementById('ai-report-output');
        output.style.display = 'block';
        output.innerHTML = 'AI sedang menganalisa data, mohon tunggu...';
        
        try {
            const res = await axios.post(`${API_URL}/organizer/ai/${endpoint}`, { event_id: eventId });
            output.innerHTML = res.data.analysis || res.data.trends || JSON.stringify(res.data, null, 2);
        } catch (e) {
            output.innerHTML = 'Gagal menghasilkan laporan AI. Pastikan layanan AI tersedia.';
        }
    }

    // Modal Logic
    function openEventModal() {
        document.getElementById('eventModal').classList.add('active');
        document.getElementById('modal-title').textContent = 'Buat Konser Baru';
        document.getElementById('event-form').reset();
        document.getElementById('event_id').value = '';
        document.getElementById('ticket-rows-container').innerHTML = '';
        addTicketRow();
    }
    
    function closeEventModal() {
        document.getElementById('eventModal').classList.remove('active');
    }

    async function editEvent(id) {
        openEventModal();
        document.getElementById('modal-title').textContent = 'Edit Konser';
        
        try {
            const res = await axios.get(`${API_URL}/events/${id}`);
            const ev = res.data.event || res.data.data;
            document.getElementById('event_id').value = ev.id;
            document.getElementById('event_title').value = ev.title;
            document.getElementById('event_venue_id').value = ev.venue_id;
            document.getElementById('event_start').value = ev.start_date ? ev.start_date.substring(0, 16) : '';
            document.getElementById('event_end').value = ev.end_date ? ev.end_date.substring(0, 16) : '';
            document.getElementById('event_description').value = ev.description;
            
            // Wait, we need an endpoint to fetch ticket types for edit, or assume they can only add new ones for now.
            // For simplicity in this UI revamp, we will let them add new tickets.
        } catch(e) {
            alert('Gagal mengambil data event');
            closeEventModal();
        }
    }

    async function deleteEvent(id) {
        if(!confirm('Yakin ingin menghapus konser ini?')) return;
        try {
            await axios.delete(`${API_URL}/organizer/events/${id}`);
            loadEvents();
        } catch(e) {
            alert('Gagal menghapus');
        }
    }

    let ticketRowCounter = 0;
    function addTicketRow() {
        const container = document.getElementById('ticket-rows-container');
        const id = ticketRowCounter++;
        container.insertAdjacentHTML('beforeend', `
            <div class="ticket-type-row" id="ticket-row-${id}">
                <div style="flex:2">
                    <input type="text" class="brutalist-input t-name" placeholder="Nama (e.g. VIP)" required>
                </div>
                <div style="flex:1">
                    <input type="number" class="brutalist-input t-price" placeholder="Harga" required>
                </div>
                <div style="flex:1">
                    <input type="number" class="brutalist-input t-quota" placeholder="Kuota" required>
                </div>
                <button type="button" class="brutalist-btn brutalist-btn-danger" style="padding: 0.5rem;" onclick="document.getElementById('ticket-row-${id}').remove()">X</button>
            </div>
        `);
    }

    async function submitEvent(e) {
        e.preventDefault();
        
        const id = document.getElementById('event_id').value;
        const payload = {
            title: document.getElementById('event_title').value,
            description: document.getElementById('event_description').value,
            start_date: document.getElementById('event_start').value.replace('T', ' ') + ':00',
            end_date: document.getElementById('event_end').value.replace('T', ' ') + ':00',
            venue_id: document.getElementById('event_venue_id').value,
            status: 'published'
        };

        try {
            let eventId = id;
            if (id) {
                await axios.put(`${API_URL}/organizer/events/${id}`, payload);
            } else {
                const res = await axios.post(`${API_URL}/events`, payload);
                eventId = res.data.event.id;
                
                // Add tickets only if it's a new event
                const rows = document.querySelectorAll('.ticket-type-row');
                for (let row of rows) {
                    const tPayload = {
                        name: row.querySelector('.t-name').value,
                        price: row.querySelector('.t-price').value,
                        quota: row.querySelector('.t-quota').value,
                    };
                    await axios.post(`${API_URL}/events/${eventId}/ticket-types`, tPayload);
                }
            }
            closeEventModal();
            loadEvents();
        } catch(e) {
            alert('Gagal menyimpan konser');
        }
    }
</script>
@endpush
