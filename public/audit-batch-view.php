<?php
declare(strict_types=1);

$sessionId = (int) ($_GET['session_id'] ?? 0);

if ($sessionId <= 0) {
    echo 'Sessão inválida';
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Audit Batch – Próximo Chamado</title>
<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.box { border: 1px solid #ccc; padding: 16px; }
.badge { padding: 4px 8px; border-radius: 4px; }
.HIGH { background: #c62828; color: #fff; }
.MEDIUM { background: #f9a825; }
.LOW { background: #2e7d32; color: #fff; }
button { margin-right: 8px; }
pre { background: #f5f5f5; padding: 10px; }
</style>
</head>
<body>

<h2>🧪 Auditoria em Lote — Próximo Chamado</h2>

<p id="progress" style="font-weight:bold; margin-bottom:10px;"></p>
<div id="summary" style="margin-top:20px;"></div>



<div id="actions" style="margin-top:15px; display:none;">
    <button onclick="saveItem()">✅ Salvar</button>
    <button onclick="skipItem()">⏭ Pular</button>
</div>

<script>
const sessionId = <?= $sessionId ?>;
let currentItemId = null;

async function loadProgress() {
    const res = await fetch(`audit-batch-progress.php?session_id=${sessionId}`);
    const p = await res.json();

    if (p.total === 0) {
        document.getElementById('progress').innerText =
            'Nenhum chamado carregado nesta sessão';
        return;
    }

    document.getElementById('progress').innerText =
        `${p.done} de ${p.total} chamados analisados`;
}

async function loadNext() {
    const res = await fetch(`audit-batch-next.php?session_id=${sessionId}`);
    const item = await res.json();

    if (!item) {
        const resP = await fetch(`audit-batch-progress.php?session_id=${sessionId}`);
        const progress = await resP.json();

        document.getElementById('actions').style.display = 'none';

        // ✅ Sessão vazia
        if (progress.total === 0) {
            document.getElementById('container').innerHTML = `
                <div class="box">
                    <strong>📭 Nenhum chamado carregado.</strong>
                    <p>Esta sessão ainda não possui itens.</p>
                </div>
            `;
            return;
        }

        // ✅ Aguardando APKIA
        if (progress.done === 0 && progress.remaining === progress.total) {
            document.getElementById('container').innerHTML = `
                <div class="box">
                    <strong>⏳ Sessão aguardando análise automatizada (APKIA).</strong>
                    <p>
                        Os chamados desta sessão ainda estão sendo processados.
                        A auditoria será liberada após a conclusão da análise.
                    </p>
                </div>
            `;
            return;
        }

        // ✅ Sessão finalizada
if (progress.remaining === 0) {

    document.getElementById('actions').style.display = 'none';

    // ⚠️ Havendo pulados → NÃO repetir mensagem final aqui
    if (progress.skipped > 0) {
        document.getElementById('container').innerHTML = '';
    } 
    
    /*else {
        // ✅ Sem pulados → sessão realmente concluída
        document.getElementById('container').innerHTML = `
            
        `;
    }*/

    // ✅ O resumo SEMPRE comunica o estado semântico
    if (typeof loadSummary === 'function') {
        loadSummary();
    }

    return;
}
    }

    // ✅ Item normal
    currentItemId = item.id;
    const apkia = item.apkia_result ? JSON.parse(item.apkia_result) : null;

    document.getElementById('container').innerHTML = `
        <div class="box">
            <p><strong>ID:</strong> ${item.id}</p>
            <p><strong>Prioridade:</strong>
                <span class="badge ${item.attention_level}">
                    ${item.attention_level}
                </span>
                | Risco: ${item.risk_score}
            </p>
            <p><strong>Texto:</strong></p>
            <pre>${item.raw_text}</pre>
            <p><strong>Sugestões APKIA:</strong></p>
            <ul>
                ${(apkia?.summary || []).map(s => `<li>${s}</li>`).join('')}
            </ul>
        </div>
    `;

    document.getElementById('actions').style.display = 'block';
}


async function saveItem() {
    await fetch('audit-batch-save.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `item_id=${currentItemId}`
    });
    loadNext();
    loadProgress();
}

async function skipItem() {
    await fetch('audit-batch-skip.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `item_id=${currentItemId}`
    });
    loadNext();
    loadProgress();
}

// ✅ chamadas iniciais
loadNext();
loadProgress();

function formatDuration(seconds) {
    if (!seconds && seconds !== 0) return '-';
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${m}m ${s}s`;
}

async function loadSummary() {
    // 1️⃣ Buscar resumo da sessão
    const res = await fetch(`audit-batch-summary.php?session_id=${sessionId}`);
    const s = await res.json();

    // 2️⃣ Status final baseado em skipped
    let finalStatusHtml = '';

    if (s.skipped > 0) {
        finalStatusHtml = `
            <p style="margin-top:10px; color:#b26a00;">
                ⚠️ <strong>Sessão concluída com pendências.</strong><br>
                Existem <strong>${s.skipped}</strong> chamados pulados aguardando revisão.
            </p>
        `;
    } else {
        finalStatusHtml = `
            <p style="margin-top:10px; color:#2e7d32;">
                ✅ <strong>Auditoria concluída.</strong><br>
                Todos os chamados receberam decisão final.
            </p>
        `;
    }

    // 3️⃣ Montar resumo completo
    document.getElementById('summary').innerHTML = `
        <h3>📊 Resumo da Sessão</h3>
        <ul>
            <li><strong>Total de chamados:</strong> ${s.total}</li>
            <li><strong>Saved:</strong> ${s.saved}</li>
            <li><strong>Skipped:</strong> ${s.skipped}</li>
            <li><strong>Prioridade HIGH:</strong> ${s.high}</li>
            <li><strong>Prioridade MEDIUM:</strong> ${s.medium}</li>
            <li><strong>Prioridade LOW:</strong> ${s.low}</li>
            <li><strong>Duração da sessão:</strong> ${formatDuration(s.duration_seconds)}</li>
        </ul>
        ${finalStatusHtml}
    `;
}
</script>

</body>
</html>