<footer>
    <p>&copy; <?= date('Y'); ?> SI-KENDI</p>
</footer>

<!-- JavaScript Files -->
<script src="assets/js/main.js"></script>

<script>
    // Enhanced Mobile menu functionality
    document.addEventListener('DOMContentLoaded', function() {
        const mobileToggle = document.getElementById('mobileMenuToggle');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        // Show mobile toggle on small screens
        function checkScreenSize() {
            if (window.innerWidth <= 768) {
                if (mobileToggle) mobileToggle.classList.remove('d-none');
            } else {
                if (mobileToggle) mobileToggle.classList.add('d-none');
                if (sidebar) sidebar.classList.remove('show');
                if (overlay) overlay.classList.remove('show');
            }
        }
        
        // Initial check
        checkScreenSize();
        
        // Check on resize
        window.addEventListener('resize', checkScreenSize);
        
        // Mobile menu toggle
        if (mobileToggle) {
            mobileToggle.addEventListener('click', function() {
                if (sidebar) sidebar.classList.toggle('show');
                if (overlay) overlay.classList.toggle('show');
            });
        }
        
        // Close sidebar when overlay is clicked
        if (overlay) {
            overlay.addEventListener('click', function() {
                if (sidebar) sidebar.classList.remove('show');
                overlay.classList.remove('show');
            });
        }
        
        // Enhanced submenu functionality for mobile
        if (window.innerWidth <= 768) {
            const submenuToggles = document.querySelectorAll('.submenu-toggle');
            submenuToggles.forEach(toggle => {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const parentLi = this.closest('li.has-submenu');
                    const submenu = parentLi.querySelector('.submenu');
                    
                    if (submenu) {
                        if (submenu.style.display === 'block') {
                            submenu.style.display = 'none';
                        } else {
                            // Hide all other submenus
                            document.querySelectorAll('.submenu').forEach(sub => {
                                sub.style.display = 'none';
                            });
                            submenu.style.display = 'block';
                        }
                    }
                });
            });
        }
    });
    
    // Global JavaScript functions
    window.showSuccess = function(message) {
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: message,
            timer: 3000,
            timerProgressBar: true
        });
    };
    
    window.showError = function(message) {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: message
        });
    };
    
    window.showConfirm = function(title, text, callback) {
        Swal.fire({
            title: title,
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, lanjutkan!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed && callback) {
                callback();
            }
        });
    };
</script>
<?php if (is_logged_in()): ?>
<script>
    (function(){
        var lastCheck = new Date().toISOString();
        function pollApprovals(){
            $.ajax({
                url: 'ajax/check_surat_approved.php',
                method: 'GET',
                dataType: 'json',
                data: { last_check: lastCheck },
                cache: false,
                success: function(resp){
                    if (!resp) return;
                    if (Array.isArray(resp.items) && resp.items.length > 0) {
                        resp.items.forEach(function(it){
                            var tgl = it.tanggal_berangkat ? it.tanggal_berangkat : '';
                            var msg = 'Surat Tugas ' + (it.nomor_surat || ('#' + it.id)) + ' telah <strong>Disetujui</strong>.\nTujuan: ' + (it.tujuan || '-') + '\nTanggal: ' + (tgl || '-');
                            Swal.fire({
                                title: 'Surat Tugas Disetujui',
                                html: msg,
                                icon: 'info',
                                timer: 15000,
                                showConfirmButton: true
                            });
                        });
                    }
                    if (resp.last_check) lastCheck = resp.last_check;
                }
            });
        }
        // Start polling every 12 seconds
        setInterval(pollApprovals, 12000);
        // Run once on load after short delay
        setTimeout(pollApprovals, 3000);
    })();
</script>
<?php endif; ?>
</body>
</html>
