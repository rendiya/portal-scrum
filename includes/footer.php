    </main>

    <!-- Global WhatsApp Modal -->
    <div id="waModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden border-2 border-[#043399]">
            <div class="bg-[#043399] text-white px-6 py-4 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-base text-white">Kirim Pesan via WhatsApp</h3>
                    <p class="text-xs text-amber-200 font-medium">Penerima: <span id="waModalName" class="font-bold text-white">Siswa</span></p>
                </div>
                <button type="button" onclick="closeWhatsAppModal()" class="text-white hover:text-amber-300 font-bold text-xl p-1 leading-none">&times;</button>
            </div>

            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-800 mb-1">Nomor WhatsApp Penerima</label>
                    <input type="text" id="waModalPhone" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm text-slate-900 font-semibold focus:outline-none focus:ring-2 focus:ring-[#043399]">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-800 mb-1">Isi Pesan WhatsApp</label>
                    <textarea id="waModalMessage" rows="6" class="w-full p-3 border border-slate-300 rounded-lg text-xs font-mono text-slate-900 bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#043399]"></textarea>
                </div>

                <div id="waGatewayStatus" class="hidden p-3 rounded-lg text-xs font-semibold"></div>

                <div class="pt-2 flex flex-col sm:flex-row items-center gap-2">
                    <button type="button" onclick="sendViaWaMe()" class="w-full sm:flex-1 py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center justify-center shadow-sm transition">
                        <span>Buka WA Web / App (wa.me)</span>
                    </button>

                    <button type="button" id="waGatewaySendBtn" onclick="sendViaGateway()" class="w-full sm:w-auto py-2.5 px-4 rounded-xl bg-[#043399] hover:bg-[#021f5c] text-white font-bold text-xs flex items-center justify-center transition">
                        <span>Kirim via Gateway API</span>
                    </button>
                </div>

                <p class="text-[11px] text-slate-600 font-medium text-center">
                    Gunakan opsi <b>Buka WA Web / App</b> untuk pengiriman instan tanpa kuota gateway.
                </p>
            </div>
        </div>
    </div>

    <!-- Global Custom Confirm Modal (Replaces browser confirm() popup) -->
    <div id="appConfirmModal" class="no-print fixed inset-0 z-[9999] overflow-y-auto bg-black/60 backdrop-blur-xs p-4" style="display: none;">
        <div class="min-h-full flex items-center justify-center p-2">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden border-2 border-[#043399] transform transition-all animate-in fade-in zoom-in-95 duration-150">
                <div class="bg-[#043399] text-white px-5 py-3.5 flex items-center justify-between">
                    <h3 id="appConfirmTitle" class="font-black text-sm text-white">Konfirmasi Tindakan</h3>
                    <button type="button" onclick="window._resolveAppConfirm(false)" class="text-white hover:text-amber-300 font-bold text-2xl p-1 leading-none">&times;</button>
                </div>
                <div class="p-5 space-y-4">
                    <p id="appConfirmMessage" class="text-xs text-black font-semibold leading-relaxed">
                        Apakah Anda yakin ingin melanjutkan tindakan ini?
                    </p>
                    <div class="pt-2 border-t border-slate-200 flex justify-end gap-2">
                        <button type="button" id="appConfirmBtnCancel" onclick="window._resolveAppConfirm(false)" class="px-4 py-2 rounded-xl border border-slate-300 font-bold text-xs text-black hover:bg-slate-100 transition active:scale-95">
                            Batal
                        </button>
                        <button type="button" id="appConfirmBtnOk" onclick="window._resolveAppConfirm(true)" class="px-5 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white font-black text-xs shadow-xs transition active:scale-95">
                            Ya, Lanjutkan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Global Custom Alert Modal (Replaces browser alert() popup) -->
    <div id="appAlertModal" class="no-print fixed inset-0 z-[9999] overflow-y-auto bg-black/60 backdrop-blur-xs p-4" style="display: none;">
        <div class="min-h-full flex items-center justify-center p-2">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden border-2 border-[#043399] transform transition-all animate-in fade-in zoom-in-95 duration-150">
                <div class="bg-[#043399] text-white px-5 py-3.5 flex items-center justify-between">
                    <h3 id="appAlertTitle" class="font-black text-sm text-white">Pemberitahuan Sistem</h3>
                    <button type="button" onclick="window._resolveAppAlert()" class="text-white hover:text-amber-300 font-bold text-2xl p-1 leading-none">&times;</button>
                </div>
                <div class="p-5 space-y-4">
                    <p id="appAlertMessage" class="text-xs text-black font-semibold leading-relaxed">
                        Pesan informasi sistem.
                    </p>
                    <div class="pt-2 border-t border-slate-200 flex justify-end">
                        <button type="button" id="appAlertBtnOk" onclick="window._resolveAppAlert()" class="px-6 py-2.5 rounded-xl bg-[#043399] hover:bg-[#021f5c] text-white font-black text-xs shadow-xs transition active:scale-95">
                            OK / Mengerti
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Global Custom Edit Account Modal -->
    <div id="editAccountModal" class="no-print fixed inset-0 z-[9999] overflow-y-auto bg-black/60 backdrop-blur-xs p-4" style="display: none;">
        <div class="min-h-full flex items-center justify-center p-2">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden border-2 border-[#043399] animate-in fade-in zoom-in-95 duration-150">
                <div class="bg-[#043399] text-white px-5 py-3.5 flex items-center justify-between">
                    <div>
                        <h3 class="font-black text-sm text-white">Edit Account</h3>
                        <p class="text-[11px] text-amber-300 font-medium">Perbarui profil identitas akun Anda</p>
                    </div>
                    <button type="button" onclick="closeEditAccountModal()" class="text-white hover:text-amber-300 font-bold text-2xl p-1 leading-none">&times;</button>
                </div>

                <form id="editAccountForm" onsubmit="handleSaveAccount(event)" class="p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-800 mb-1">Nama Lengkap *</label>
                        <input type="text" name="name" id="accountModalName" required value="<?= htmlspecialchars($_SESSION['scrumvibe_user_name'] ?? ($currentMember['name'] ?? '')) ?>" class="w-full px-3 py-2 border-2 border-slate-300 rounded-lg text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#043399]">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-800 mb-1">Alamat Email</label>
                        <input type="email" name="email" id="accountModalEmail" value="<?= htmlspecialchars($currentMember['email'] ?? '') ?>" placeholder="nama@email.com" class="w-full px-3 py-2 border-2 border-slate-300 rounded-lg text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#043399]">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-800 mb-1">Nomor WhatsApp</label>
                        <input type="text" name="phone" id="accountModalPhone" value="<?= htmlspecialchars($currentMember['phone'] ?? '') ?>" placeholder="08xxxxxxxxxx" class="w-full px-3 py-2 border-2 border-slate-300 rounded-lg text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#043399]">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-800 mb-1">Asal Kampus / Sekolah</label>
                            <input type="text" name="university" id="accountModalUniv" value="<?= htmlspecialchars($currentMember['university'] ?? '') ?>" placeholder="Universitas..." class="w-full px-3 py-2 border-2 border-slate-300 rounded-lg text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#043399]">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-800 mb-1">Jurusan / Program Studi</label>
                            <input type="text" name="major" id="accountModalMajor" value="<?= htmlspecialchars($currentMember['major'] ?? '') ?>" placeholder="Teknik Informatika..." class="w-full px-3 py-2 border-2 border-slate-300 rounded-lg text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#043399]">
                        </div>
                    </div>

                    <!-- Ubah Password Section -->
                    <div class="pt-3 border-t-2 border-slate-100 space-y-3 bg-slate-50/70 p-3 rounded-xl border border-slate-200">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-black text-[#043399] flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                Ubah Kata Sandi (Password)
                            </span>
                            <span class="text-[10px] text-slate-500 font-medium">Opsional</span>
                        </div>

                        <?php if (!empty($currentMember['password_hash'])): ?>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-800 mb-1">Kata Sandi Saat Ini (Lama) *</label>
                            <input type="password" name="current_password" id="accountModalCurrentPass" placeholder="Masukkan kata sandi lama Anda" class="w-full px-3 py-2 border-2 border-slate-300 rounded-lg text-xs font-mono text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#043399]">
                        </div>
                        <?php endif; ?>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-800 mb-1">Kata Sandi Baru</label>
                                <input type="password" name="new_password" id="accountModalNewPass" minlength="6" placeholder="Min. 6 karakter" class="w-full px-3 py-2 border-2 border-slate-300 rounded-lg text-xs font-mono text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#043399]">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-800 mb-1">Ulangi Kata Sandi</label>
                                <input type="password" name="confirm_password" id="accountModalConfirmPass" minlength="6" placeholder="Ketik ulang kata sandi" class="w-full px-3 py-2 border-2 border-slate-300 rounded-lg text-xs font-mono text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#043399]">
                            </div>
                        </div>
                        <p class="text-[10px] text-slate-500 leading-tight">Kosongkan kolom kata sandi di atas jika Anda hanya ingin memperbarui data profil tanpa mengganti kata sandi.</p>
                    </div>

                    <div class="pt-3 border-t border-slate-200 flex justify-end gap-2">
                        <button type="button" onclick="closeEditAccountModal()" class="px-4 py-2 rounded-xl border border-slate-300 font-bold text-xs text-black hover:bg-slate-100 transition active:scale-95">
                            Batal
                        </button>
                        <button type="submit" class="px-5 py-2 rounded-xl bg-[#043399] hover:bg-[#021f5c] text-white font-black text-xs shadow-xs transition active:scale-95">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-white border-t border-slate-200 py-6 text-center text-xs text-slate-700 font-medium">
        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <span class="font-black text-[#043399]">VINIX<span class="text-[#f59e0b]">7</span></span>
                <span class="text-slate-600">• PT VINIX SEVEN AURUM</span>
            </div>
            <div class="text-slate-600">
                Program Fast Track &copy; <?= date('Y') ?> • Web Development
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="assets/js/app.js"></script>
</body>
</html>
