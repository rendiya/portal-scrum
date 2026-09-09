// assets/js/app.js - Interactive JavaScript for ScrumVibe

document.addEventListener('DOMContentLoaded', () => {
    initDragAndDrop();
});

// 1. Kanban Drag and Drop
function initDragAndDrop() {
    const cards = document.querySelectorAll('.kanban-card');
    const cols = document.querySelectorAll('.kanban-col');

    cards.forEach(card => {
        card.addEventListener('dragstart', (e) => {
            window.isDraggingCard = true;
            e.dataTransfer.setData('text/plain', card.dataset.id);
            card.classList.add('opacity-50');
        });

        card.addEventListener('dragend', () => {
            card.classList.remove('opacity-50');
            setTimeout(() => {
                window.isDraggingCard = false;
            }, 150);
        });
    });

    cols.forEach(col => {
        col.addEventListener('dragover', (e) => {
            e.preventDefault();
            col.classList.add('drag-over');
        });

        col.addEventListener('dragleave', () => {
            col.classList.remove('drag-over');
        });

        col.addEventListener('drop', async (e) => {
            e.preventDefault();
            col.classList.remove('drag-over');
            const taskId = e.dataTransfer.getData('text/plain');
            const targetStatus = col.dataset.status;

            if (taskId && targetStatus) {
                await updateTaskStatus(taskId, targetStatus);
            }
        });
    });
}

// 2. Update Task Status via AJAX
async function updateTaskStatus(taskId, targetStatus) {
    try {
        const res = await fetch('api.php?action=update_task_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: taskId, status: targetStatus })
        });
        const data = await res.json();
        if (data.success) {
            if (targetStatus === 'done' && typeof confetti === 'function') {
                confetti({
                    particleCount: 80,
                    spread: 70,
                    origin: { y: 0.6 }
                });
            }
            window.location.reload();
        } else {
            await showAppAlert(data.error || 'Gagal mengubah status');
        }
    } catch (err) {
        console.error('Error updating task:', err);
    }
}

// 3. Quick Move Left/Right
async function moveTaskQuick(taskId, targetStatus) {
    await updateTaskStatus(taskId, targetStatus);
}

// 4. Team Switcher Helper (Guru only)
function switchTeam(teamId) {
    localStorage.setItem('scrumvibe_team', teamId);
    const url = new URL(window.location.href);
    url.searchParams.set('team_id', teamId);
    window.location.href = url.toString();
}

// 5. Claim / Self-Assign Task by Student
async function claimTask(taskId, studentId, studentName) {
    try {
        const res = await fetch('api.php?action=claim_task', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: taskId, assignee_id: studentId, assignee_name: studentName })
        });
        const data = await res.json();
        if (data.success) {
            window.location.reload();
        } else {
            await showAppAlert(data.error || 'Gagal mengambil tugas');
        }
    } catch (err) {
        console.error('Error claiming task:', err);
    }
}

// 6. WhatsApp Modal Controls
function openWhatsAppModal(name, phone, message) {
    const modal = document.getElementById('waModal');
    if (!modal) return;

    document.getElementById('waModalName').innerText = name || 'Siswa';
    document.getElementById('waModalPhone').value = phone || '';
    document.getElementById('waModalMessage').value = message || '';
    document.getElementById('waGatewayStatus').classList.add('hidden');

    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeWhatsAppModal() {
    const modal = document.getElementById('waModal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function sendViaWaMe() {
    const phone = document.getElementById('waModalPhone').value.trim();
    const message = document.getElementById('waModalMessage').value;
    if (!phone) {
        showAppAlert('Nomor HP tidak boleh kosong!');
        return;
    }

    let cleaned = phone.replace(/[^0-9]/g, '');
    if (cleaned.startsWith('08')) cleaned = '628' + cleaned.substring(2);
    else if (cleaned.startsWith('8')) cleaned = '628' + cleaned.substring(1);

    const link = `https://wa.me/${cleaned}?text=${encodeURIComponent(message)}`;
    window.open(link, '_blank', 'noopener,noreferrer');
}

async function sendViaGateway() {
    const phone = document.getElementById('waModalPhone').value.trim();
    const message = document.getElementById('waModalMessage').value;
    const statusDiv = document.getElementById('waGatewayStatus');
    const sendBtn = document.getElementById('waGatewaySendBtn');

    if (!phone) {
        showAppAlert('Nomor HP tidak boleh kosong!');
        return;
    }

    sendBtn.disabled = true;
    sendBtn.innerText = 'Mengirim...';
    statusDiv.classList.add('hidden');

    try {
        const res = await fetch('api.php?action=send_whatsapp', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ phone, message, use_gateway: true })
        });
        const data = await res.json();

        statusDiv.classList.remove('hidden');
        if (data.success) {
            statusDiv.className = 'p-3 rounded-lg text-xs bg-emerald-50 border border-emerald-200 text-emerald-900 font-semibold';
            statusDiv.innerText = '[Sukses] ' + (data.message || 'Berhasil dikirim melalui Gateway!');
        } else {
            statusDiv.className = 'p-3 rounded-lg text-xs bg-amber-50 border border-amber-200 text-amber-900 font-semibold';
            statusDiv.innerText = '[Peringatan] ' + (data.message || 'Gagal mengirim melalui Gateway');
        }
    } catch (err) {
        statusDiv.classList.remove('hidden');
        statusDiv.className = 'p-3 rounded-lg text-xs bg-rose-50 border border-rose-200 text-rose-900 font-semibold';
        statusDiv.innerText = '[Error] ' + err.message;
    } finally {
        sendBtn.disabled = false;
        sendBtn.innerText = 'Kirim via Gateway';
    }
}

// =========================================================================
// Universal Custom Modal Dialogs (Zero native browser popups: alert/confirm)
// =========================================================================
window._appConfirmResolver = null;
window._appAlertResolver = null;

window.showAppConfirm = function(message, options = {}) {
    return new Promise((resolve) => {
        window._appConfirmResolver = resolve;
        const modal = document.getElementById('appConfirmModal');
        const titleEl = document.getElementById('appConfirmTitle');
        const msgEl = document.getElementById('appConfirmMessage');
        const okBtn = document.getElementById('appConfirmBtnOk');
        const cancelBtn = document.getElementById('appConfirmBtnCancel');

        if (titleEl) titleEl.innerText = options.title || 'Konfirmasi Tindakan';
        if (msgEl) msgEl.innerText = message || '';
        if (okBtn) {
            okBtn.innerText = options.confirmText || 'Ya, Lanjutkan';
            if (options.confirmColor === 'navy') {
                okBtn.className = 'px-5 py-2.5 rounded-xl bg-[#043399] hover:bg-[#021f5c] text-white font-black text-xs shadow-xs transition active:scale-95';
            } else {
                okBtn.className = 'px-5 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 text-white font-black text-xs shadow-xs transition active:scale-95';
            }
        }
        if (cancelBtn) cancelBtn.innerText = options.cancelText || 'Batal';

        if (modal) {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
            if (okBtn) okBtn.focus();
        } else {
            resolve(true);
        }
    });
};

window._resolveAppConfirm = function(result) {
    const modal = document.getElementById('appConfirmModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
    if (typeof window._appConfirmResolver === 'function') {
        const resolver = window._appConfirmResolver;
        window._appConfirmResolver = null;
        resolver(Boolean(result));
    }
};

window.showAppAlert = function(message, options = {}) {
    return new Promise((resolve) => {
        window._appAlertResolver = resolve;
        const modal = document.getElementById('appAlertModal');
        const titleEl = document.getElementById('appAlertTitle');
        const msgEl = document.getElementById('appAlertMessage');
        const okBtn = document.getElementById('appAlertBtnOk');

        if (titleEl) titleEl.innerText = options.title || 'Pemberitahuan Sistem';
        if (msgEl) msgEl.innerText = message || '';
        if (okBtn) okBtn.innerText = options.okText || 'OK / Mengerti';

        if (modal) {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
            if (okBtn) okBtn.focus();
        } else {
            resolve();
        }
    });
};

window._resolveAppAlert = function() {
    const modal = document.getElementById('appAlertModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
    if (typeof window._appAlertResolver === 'function') {
        const resolver = window._appAlertResolver;
        window._appAlertResolver = null;
        resolver();
    }
};

// Override native window.alert so no code ever triggers native browser popup
window.alert = function(msg) {
    return window.showAppAlert(msg);
};

// =========================================================================
// Account Dropdown & Profile Modal Controls
// =========================================================================
function toggleAccountDropdown(e) {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }
    const menu = document.getElementById('accountDropdownMenu');
    if (!menu) return;
    if (menu.style.display === 'none' || menu.style.display === '') {
        menu.style.display = 'block';
    } else {
        menu.style.display = 'none';
    }
}

// Close account dropdown only when clicking outside of it
document.addEventListener('click', function(e) {
    const container = document.getElementById('accountDropdownContainer');
    const menu = document.getElementById('accountDropdownMenu');
    if (container && menu && !container.contains(e.target)) {
        menu.style.display = 'none';
    }
});

function openEditAccountModal() {
    const modal = document.getElementById('editAccountModal');
    if (modal) {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
}

function closeEditAccountModal() {
    const modal = document.getElementById('editAccountModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

async function handleSaveAccount(e) {
    e.preventDefault();
    const form = e.target;
    const data = Object.fromEntries(new FormData(form).entries());

    if (data.new_password) {
        if (data.new_password.length < 6) {
            await showAppAlert('Kata sandi baru minimal harus 6 karakter.');
            return;
        }
        if (data.new_password !== data.confirm_password) {
            await showAppAlert('Konfirmasi kata sandi baru tidak cocok. Silakan periksa kembali.');
            return;
        }
    }

    try {
        const res = await fetch('api.php?action=edit_account', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.success) {
            closeEditAccountModal();
            await showAppAlert(result.message || 'Profil akun berhasil diperbarui!');
            window.location.reload();
        } else {
            await showAppAlert(result.error || 'Gagal memperbarui akun');
        }
    } catch (err) {
        await showAppAlert('Terjadi kesalahan: ' + err.message);
    }
}
