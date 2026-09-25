@extends('layouts.app')

@section('title', 'Admin Dashboard')

@push('styles')
<style>
    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .stat-card {
        padding: 1.5rem;
        text-align: center;
    }
    
    .stat-card h3 {
        color: var(--text-muted);
        font-size: 1rem;
        margin-bottom: 0.5rem;
    }
    
    .stat-card .value {
        font-size: 2.5rem;
        font-weight: 800;
        color: var(--primary);
    }
    
    .ai-insight-panel {
        margin-top: 2rem;
        padding: 1.5rem;
        border: 1px solid var(--secondary);
        border-radius: 16px;
    }
    
    .ai-insight-content {
        white-space: pre-wrap;
        font-family: monospace;
        background: rgba(0,0,0,0.3);
        padding: 1rem;
        border-radius: 8px;
        margin-top: 1rem;
        max-height: 400px;
        overflow-y: auto;
    }
</style>
@endpush

@section('content')
<div class="dashboard-header">
    <div>
        <h1>Super Admin Dashboard</h1>
        <p class="text-muted">Platform Overview & AI Security Logs</p>
    </div>
</div>

<div class="stats-grid">
    <div class="glass-panel stat-card">
        <h3>Total Users</h3>
        <div class="value">Loading...</div>
    </div>
    <div class="glass-panel stat-card">
        <h3>Active Organizers</h3>
        <div class="value">Loading...</div>
    </div>
    <div class="glass-panel stat-card">
        <h3>System Health</h3>
        <div class="value" style="color: var(--success);">Optimal</div>
    </div>
</div>

<div style="display: flex; gap: 1rem; margin-bottom: 2rem;">
    <button class="btn btn-primary" onclick="requestAiAdmin('analyze-platform')" style="display:flex; align-items:center; gap:0.5rem;"><i data-lucide="bar-chart-2" style="width: 18px; height: 18px;"></i> AI Platform Analysis</button>
    <button class="btn btn-outline" style="border-color: var(--danger); color: var(--danger); display:flex; align-items:center; gap:0.5rem;" onclick="requestAiAdmin('detect-suspicious')"><i data-lucide="shield-alert" style="width: 18px; height: 18px;"></i> AI Security Check</button>
</div>

<div class="glass-panel ai-insight-panel" id="aiPanel" style="display: none;">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h3 style="color:var(--secondary); display:flex; align-items:center; gap:0.5rem;"><i data-lucide="sparkles" style="width: 20px; height: 20px;"></i> AI System Report</h3>
        <button class="btn btn-outline" style="padding:0.2rem 0.5rem; display:flex; align-items:center; justify-content:center;" onclick="closeAiPanel()"><i data-lucide="x" style="width: 16px; height: 16px;"></i></button>
    </div>
    <div id="aiContent" class="ai-insight-content">Loading report...</div>
</div>

@endsection

@push('scripts')
<script>
    function closeAiPanel() {
        document.getElementById('aiPanel').style.display = 'none';
    }

    function requestAiAdmin(endpoint) {
        const panel = document.getElementById('aiPanel');
        const content = document.getElementById('aiContent');
        
        panel.style.display = 'block';
        content.innerHTML = '<div class="spinner" style="margin: 0 auto;"></div><p class="text-center mt-4">AI is analyzing system data...</p>';
        
        panel.scrollIntoView({ behavior: 'smooth' });

        axios.post(`${API_URL}/admin/ai/${endpoint}`)
            .then(res => {
                let text = '';
                if(endpoint === 'analyze-platform') text = res.data.analysis;
                if(endpoint === 'detect-suspicious') text = res.data.report;
                
                content.textContent = text || 'Laporan selesai.';
            })
            .catch(err => {
                content.innerHTML = '<span class="text-danger">Gagal menghubungi sistem keamanan AI.</span>';
            });
    }
</script>
@endpush
