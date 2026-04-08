/**
 * RANDIS - Main JavaScript File
 * Contains all common JavaScript functions and AJAX handlers
 */

// SweetAlert2 wrapper functions
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
    }
});

// Show success message
function showSuccess(message) {
    Toast.fire({
        icon: 'success',
        title: message
    });
}

// Show error message
function showError(message) {
    Toast.fire({
        icon: 'error',
        title: message
    });
}

// Show warning message
function showWarning(message) {
    Toast.fire({
        icon: 'warning',
        title: message
    });
}

// Show confirmation dialog
function showConfirm(title, text, callback) {
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
}

// AJAX helper function
function ajaxRequest(url, data, method = 'POST', callback = null) {
    $.ajax({
        url: url,
        type: method,
        data: data,
        dataType: 'json',
        beforeSend: function() {
            // Show loading
            $('.loading-overlay').show();
        },
        success: function(response) {
            $('.loading-overlay').hide();
            
            if (response.success) {
                showSuccess(response.message);
                if (callback) callback(response);
            } else {
                showError(response.message || 'Terjadi kesalahan');
            }
        },
        error: function(xhr, status, error) {
            $('.loading-overlay').hide();
            showError('Terjadi kesalahan koneksi: ' + error);
        }
    });
}

// Helper to build ajax URL relative to site root (works when pages loaded via index.php?page=...)
function ajaxUrl(path) {
    // If path already absolute (starts with http or /), return as-is
    if (/^(?:https?:)?\//.test(path)) return path;
    // Determine base path (folder of current script, usually /randis/)
    var base = window.location.pathname;
    // If current path ends with index.php or file, strip filename
    if (base.indexOf('/') !== -1 && base.lastIndexOf('/') > 0) {
        base = base.substring(0, base.lastIndexOf('/') + 1);
    }
    return base + path;
}

// Jadwal Perawatan Functions
const JadwalPerawatan = {
    // Update status jadwal
    updateStatus: function(id, status) {
        const confirmMessages = {
            'Selesai': 'Jadwal akan dipindahkan ke riwayat perawatan dan dihapus dari daftar jadwal. Lanjutkan?',
            'Dibatalkan': 'Yakin ingin membatalkan jadwal perawatan ini?',
            'Terlewat': 'Tandai jadwal sebagai terlewat?',
            'Dalam Proses': 'Ubah status menjadi dalam proses?'
        };
        
        const message = confirmMessages[status] || `Yakin ingin mengubah status menjadi "${status}"?`;
        
        showConfirm(
            'Konfirmasi Perubahan Status',
            message,
            function() {
                ajaxRequest('ajax/update_jadwal_status.php', {
                    id: id,
                    status: status,
                    csrf_token: $('meta[name="csrf-token"]').attr('content')
                }, 'POST', function(response) {
                    // Reload jadwal table
                    JadwalPerawatan.reloadTable();
                    // If marked finished, refresh riwayat table so moved record appears
                    if (status === 'Selesai') {
                        // If riwayat container exists on page, reload via AJAX; otherwise no-op
                        if (document.getElementById('riwayat-container')) {
                            RiwayatPerawatan.loadData();
                        }
                        showSuccess('Jadwal perawatan telah selesai dan dipindahkan ke riwayat perawatan');
                    } else {
                        showSuccess(response.message || 'Status berhasil diperbarui');
                    }
                });
            }
        );
    },

    // Delete jadwal
    delete: function(id) {
        showConfirm(
            'Yakin ingin menghapus jadwal ini?',
            'Data yang dihapus tidak dapat dikembalikan!',
            function() {
                ajaxRequest('ajax/delete_jadwal.php', {
                    id: id,
                    csrf_token: $('meta[name="csrf-token"]').attr('content')
                }, 'POST', function(response) {
                    JadwalPerawatan.reloadTable();
                });
            }
        );
    },

    // Submit form
    submitForm: function(formElement) {
        const formData = new FormData(formElement);
        
        ajaxRequest('ajax/save_jadwal.php', formData, 'POST', function(response) {
            setTimeout(function() {
                window.location.href = 'index.php?page=jadwal_perawatan';
            }, 1500);
        });
        
        return false;
    },

    // Reload table content
    reloadTable: function() {
        $('#jadwal-table-container').load('ajax/load_jadwal_table.php', function() {
            JadwalPerawatan.initStatusDropdowns();
            JadwalPerawatan.initDeleteButtons();
        });
    },

    // Initialize status dropdowns
    initStatusDropdowns: function() {
        $('.status-dropdown').off('change').on('change', function() {
            const id = $(this).data('id');
            const status = $(this).val();
            JadwalPerawatan.updateStatus(id, status);
        });
    },

    // Initialize delete buttons
    initDeleteButtons: function() {
        $('.btn-hapus-jadwal').off('click').on('click', function(e) {
            e.preventDefault();
            const id = $(this).data('id');
            JadwalPerawatan.delete(id);
        });
    },

    // Load table
    loadTable: function() {
        this.reloadTable();
    },

    // Save jadwal (for form submission)
    saveJadwal: function(formData) {
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                $('.loading-overlay').show();
            },
            success: function(response) {
                $('.loading-overlay').hide();
                // Page will reload with SweetAlert message
                window.location.reload();
            },
            error: function(xhr, status, error) {
                $('.loading-overlay').hide();
                showError('Terjadi kesalahan: ' + error);
            }
        });
    }
};

// Riwayat Perawatan Functions
const RiwayatPerawatan = {
    // Load riwayat data
    loadData: function(page = 1, filters = {}) {
        const data = {
            page: page,
            ...filters
        };
        
        ajaxRequest(ajaxUrl('ajax/load_riwayat_perawatan.php'), data, 'GET', function(response) {
             $('#riwayat-container').html(response.html);
         });
    },

    // Show detail modal
    showDetail: function(id) {
        ajaxRequest(ajaxUrl('ajax/get_riwayat_detail.php'), { id: id }, 'GET', function(response) {
             $('#detailModal .modal-body').html(response.html);
             $('#detailModal').modal('show');
         });
    }
};

// Log Aktivitas Functions
const LogAktivitas = {
    // Show user detail modal
    showUserDetail: function(userId) {
        ajaxRequest(ajaxUrl('ajax/get_log_user.php'), { user_id: userId }, 'GET', function(response) {
            $('#userLogModal .modal-body').html(response.html);
            $('#userLogModal').modal('show');
        });
    },

    // Show user timeline modal
    showUserTimeline: function(userId) {
        ajaxRequest(ajaxUrl('ajax/get_timeline_log.php'), { user_id: userId }, 'GET', function(response) {
            $('#timelineModal .modal-body').html(response.html);
            $('#timelineModal').modal('show');
        });
    },

    // Export log data (csv/xlsx)
    exportData: function(userId = null, startDate = null, endDate = null, format = 'csv') {
        let url = ajaxUrl('ajax/export_log.php');
        let params = [];
        if (userId) params.push('user_id=' + encodeURIComponent(userId));
        if (startDate) params.push('start_date=' + encodeURIComponent(startDate));
        if (endDate) params.push('end_date=' + encodeURIComponent(endDate));
        if (format) params.push('format=' + encodeURIComponent(format));
        if (params.length > 0) url += '?' + params.join('&');
        window.open(url, '_blank');
        showSuccess('Export ' + format.toUpperCase() + ' dimulai, file akan didownload');
    },

    // Clean old logs with confirmation
    cleanOldLogs: function(days = 90) {
        // First, get preview
        ajaxRequest(ajaxUrl('ajax/clear_old_logs.php'), { action: 'preview', days: days }, 'GET', function(response) {
            if (response.total_records > 0) {
                const actionBreakdown = response.action_breakdown.map(item => 
                    `${item.aksi}: ${item.count} record`
                ).join('<br>');
                
                Swal.fire({
                    title: 'Konfirmasi Pembersihan Log',
                    html: `
                        <div class="text-left">
                            <p><strong>Total record yang akan dihapus:</strong> ${response.total_records}</p>
                            <p><strong>Periode:</strong> Lebih lama dari ${days} hari (sebelum ${new Date(response.cutoff_date).toLocaleDateString('id-ID')})</p>
                            <p><strong>User yang terpengaruh:</strong> ${response.affected_users}</p>
                            <hr>
                            <p><strong>Breakdown berdasarkan aksi:</strong></p>
                            <div style="font-size: 0.9em;">${actionBreakdown}</div>
                        </div>
                    `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Ya, Hapus!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Execute deletion
                        ajaxRequest(ajaxUrl('ajax/clear_old_logs.php'), { action: 'execute', days: days }, 'POST', function(executeResponse) {
                            if (executeResponse.success) {
                                showSuccess(executeResponse.message);
                                // Reload page to update table
                                setTimeout(() => window.location.reload(), 1500);
                            }
                        });
                    }
                });
            } else {
                showWarning('Tidak ada record log yang perlu dihapus');
            }
        });
    }
};

// Log BBM Functions
const LogBBM = {
    // Show vehicle BBM detail modal
    showDetailBBM: function(kendaraanId) {
        ajaxRequest(ajaxUrl('ajax/get_detail_bbm.php'), { kendaraan_id: kendaraanId }, 'GET', function(response) {
            $('#detailBbmModal .modal-body').html(response.html);
            $('#detailBbmModal').modal('show');
        });
    }
};

// Loading overlay styles
const loadingCSS = `
<style>
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    display: flex;
    justify-content: center;
    align-items: center;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #3498db;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>
`;

// Document ready
$(document).ready(function() {
    // Add loading CSS to head
    $('head').append(loadingCSS);
    
    // Add CSRF token to meta tag
    if ($('meta[name="csrf-token"]').length === 0) {
        $('head').append('<meta name="csrf-token" content="' + $('input[name="csrf_token"]').val() + '">');
    }

    // Initialize jadwal perawatan functions
    if ($('#jadwal-perawatan-page').length > 0) {
        JadwalPerawatan.initStatusDropdowns();
        JadwalPerawatan.initDeleteButtons();
        
        $('#jadwal-form').on('submit', function(e) {
            e.preventDefault();
            return JadwalPerawatan.submitForm(this);
        });
    }

    // Initialize riwayat perawatan functions
    if ($('#riwayat-perawatan-page').length > 0) {
        // Page already has server-side rendered content
        // Only initialize event handlers
        console.log('Riwayat perawatan page initialized');
    }

    // Initialize log aktivitas functions
    if ($('#log-aktivitas-page').length > 0) {
        // Event handlers for log aktivitas
        $(document).on('click', '.btn-user-detail', function() {
            const userId = $(this).data('user-id');
            LogAktivitas.showUserDetail(userId);
        });

        $(document).on('click', '.btn-user-timeline', function() {
            const userId = $(this).data('user-id');
            LogAktivitas.showUserTimeline(userId);
        });

        $(document).on('click', '.btn-export-log', function() {
            const userId = $(this).data('user-id') || null;
            LogAktivitas.exportData(userId, null, null, 'csv');
        });
        $(document).on('click', '.btn-export-log-xlsx', function() {
            const userId = $(this).data('user-id') || null;
            LogAktivitas.exportData(userId, null, null, 'xlsx');
        });

        $(document).on('click', '.btn-clean-logs', function() {
            const days = $(this).data('days') || 90;
            LogAktivitas.cleanOldLogs(days);
        });

        console.log('Log aktivitas page initialized');
    }

    // Initialize log BBM functions
    if ($('#log-bbm-page').length > 0) {
        // Event handlers for log BBM
        $(document).on('click', '.btn-detail-bbm', function() {
            const kendaraanId = $(this).data('kendaraan-id');
            LogBBM.showDetailBBM(kendaraanId);
        });

        console.log('Log BBM page initialized');
    }

    // Initialize loading overlay
    if ($('.loading-overlay').length === 0) {
        $('body').append('<div class="loading-overlay" style="display:none;"><div class="spinner"></div></div>');
    }
});
