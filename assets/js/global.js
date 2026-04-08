/**
 * RANDIS - Global JavaScript
 * Common functions and event handlers for all pages
 */

$(document).ready(function() {
    // Initialize all components
    // Sidebar JS handled by assets/js/sidebar.js
    initModals();
    initForms();
    initTables();
    initNotifications();
    initTooltips();
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
});

// Legacy initSidebar removed; sidebar behavior handled entirely in assets/js/sidebar.js

/**
 * Modal functionality
 */
function initModals() {
    // Global confirmation modal
    window.showConfirmation = function(message, callback) {
        $('#confirmationMessage').text(message);
        $('#confirmationModal').modal('show');
        
        $('#confirmationOk').off('click').on('click', function() {
            $('#confirmationModal').modal('hide');
            if (typeof callback === 'function') {
                callback();
            }
        });
    };
    
    // Photo modal for image viewing
    $(document).on('click', '.photo-thumb, .vehicle-photo', function() {
        const imgSrc = $(this).find('img').attr('src') || $(this).attr('src');
        if (imgSrc) {
            showPhotoModal(imgSrc);
        }
    });
}

/**
 * Form functionality
 */
function initForms() {
    // Auto-save form data to localStorage
    $('form[data-autosave="true"]').each(function() {
        const formId = $(this).attr('id');
        if (formId) {
            loadFormData(formId);
            
            $(this).on('input change', function() {
                saveFormData(formId);
            });
        }
    });
    
    // Form validation
    $('form[data-validate="true"]').on('submit', function(e) {
        if (!validateForm(this)) {
            e.preventDefault();
            return false;
        }
    });
    
    // File upload preview
    $(document).on('change', 'input[type="file"][data-preview]', function() {
        const file = this.files[0];
        const previewId = $(this).data('preview');
        
        if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $(`#${previewId}`).attr('src', e.target.result).show();
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Numeric input formatting
    $(document).on('input', '.currency-input', function() {
        let value = $(this).val().replace(/[^\d]/g, '');
        if (value) {
            value = parseInt(value).toLocaleString('id-ID');
            $(this).val(value);
        }
    });
    
    // Date input validation
    $(document).on('change', '.date-input', function() {
        const date = new Date($(this).val());
        const today = new Date();
        
        if ($(this).hasClass('future-only') && date < today) {
            showNotification('Tanggal harus di masa depan', 'warning');
            $(this).val('');
        }
    });
}

/**
 * Table functionality
 */
function initTables() {
    // Table sorting
    $('.sortable th').on('click', function() {
        const table = $(this).closest('table');
        const columnIndex = $(this).index();
        const isAsc = $(this).hasClass('sort-asc');
        
        // Reset all sorting classes
        table.find('th').removeClass('sort-asc sort-desc');
        
        // Set new sorting class
        $(this).addClass(isAsc ? 'sort-desc' : 'sort-asc');
        
        // Sort table rows
        sortTable(table, columnIndex, !isAsc);
    });
    
    // Row selection
    $('.selectable-table tbody tr').on('click', function() {
        $(this).toggleClass('selected');
        updateBulkActions();
    });
    
    // Select all checkbox
    $('.select-all').on('change', function() {
        const isChecked = $(this).is(':checked');
        $(this).closest('table').find('tbody input[type="checkbox"]').prop('checked', isChecked);
        updateBulkActions();
    });
    
    // Individual row checkbox
    $(document).on('change', '.row-checkbox', function() {
        updateBulkActions();
    });
}

/**
 * Notification system
 */
function initNotifications() {
    // Create notification container if it doesn't exist
    if ($('#notificationContainer').length === 0) {
        $('body').append('<div id="notificationContainer" class="notification-container"></div>');
    }
}

/**
 * Tooltip initialization
 */
function initTooltips() {
    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Utility Functions
 */

// Show notification
function showNotification(message, type = 'info', duration = 5000) {
    const alertClass = `alert-${type}`;
    const notification = $(`
        <div class="alert ${alertClass} alert-dismissible fade show notification-item" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `);
    
    $('#notificationContainer').append(notification);
    
    // Auto-hide notification
    setTimeout(function() {
        notification.fadeOut('slow', function() {
            $(this).remove();
        });
    }, duration);
}

// Show photo modal
function showPhotoModal(imageSrc) {
    const modal = $(`
        <div class="photo-modal" id="photoModal">
            <span class="close">&times;</span>
            <img src="${imageSrc}" alt="Photo">
        </div>
    `);
    
    $('body').append(modal);
    modal.fadeIn(300);
    
    // Close modal events
    modal.on('click', function() {
        $(this).fadeOut(300, function() {
            $(this).remove();
        });
    });
    
    modal.find('img').on('click', function(e) {
        e.stopPropagation();
    });
}

// Loading spinner
function showLoading() {
    $('#loadingSpinner').show();
}

function hideLoading() {
    $('#loadingSpinner').hide();
}

// Form data persistence
function saveFormData(formId) {
    const formData = {};
    $(`#${formId}`).find('input, select, textarea').each(function() {
        const name = $(this).attr('name');
        if (name && $(this).attr('type') !== 'password') {
            formData[name] = $(this).val();
        }
    });
    localStorage.setItem(`form_${formId}`, JSON.stringify(formData));
}

function loadFormData(formId) {
    const savedData = localStorage.getItem(`form_${formId}`);
    if (savedData) {
        const formData = JSON.parse(savedData);
        Object.keys(formData).forEach(name => {
            $(`#${formId} [name="${name}"]`).val(formData[name]);
        });
    }
}

function clearFormData(formId) {
    localStorage.removeItem(`form_${formId}`);
}

// Form validation
function validateForm(form) {
    let isValid = true;
    
    $(form).find('[required]').each(function() {
        if (!$(this).val().trim()) {
            showNotification(`Field ${$(this).attr('name')} harus diisi`, 'danger');
            $(this).focus();
            isValid = false;
            return false;
        }
    });
    
    // Email validation
    $(form).find('input[type="email"]').each(function() {
        const email = $(this).val();
        if (email && !isValidEmail(email)) {
            showNotification('Format email tidak valid', 'danger');
            $(this).focus();
            isValid = false;
            return false;
        }
    });
    
    // Number validation
    $(form).find('input[type="number"]').each(function() {
        const value = $(this).val();
        const min = $(this).attr('min');
        const max = $(this).attr('max');
        
        if (value && min && parseFloat(value) < parseFloat(min)) {
            showNotification(`Nilai minimum adalah ${min}`, 'danger');
            $(this).focus();
            isValid = false;
            return false;
        }
        
        if (value && max && parseFloat(value) > parseFloat(max)) {
            showNotification(`Nilai maksimum adalah ${max}`, 'danger');
            $(this).focus();
            isValid = false;
            return false;
        }
    });
    
    return isValid;
}

// Email validation
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Table sorting
function sortTable(table, columnIndex, ascending) {
    const rows = table.find('tbody tr').toArray();
    
    rows.sort(function(a, b) {
        const aValue = $(a).find('td').eq(columnIndex).text().trim();
        const bValue = $(b).find('td').eq(columnIndex).text().trim();
        
        // Try to parse as numbers
        const aNum = parseFloat(aValue.replace(/[^\d.-]/g, ''));
        const bNum = parseFloat(bValue.replace(/[^\d.-]/g, ''));
        
        if (!isNaN(aNum) && !isNaN(bNum)) {
            return ascending ? aNum - bNum : bNum - aNum;
        }
        
        // Sort as strings
        return ascending ? 
            aValue.localeCompare(bValue) : 
            bValue.localeCompare(aValue);
    });
    
    table.find('tbody').empty().append(rows);
}

// Update bulk actions based on selected rows
function updateBulkActions() {
    const selectedCount = $('.row-checkbox:checked').length;
    const bulkActions = $('.bulk-actions');
    
    if (selectedCount > 0) {
        bulkActions.show();
        bulkActions.find('.selected-count').text(selectedCount);
    } else {
        bulkActions.hide();
    }
}

// Format currency for display
function formatCurrency(amount) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(amount);
}

// Format date for display
function formatDate(dateString, format = 'dd/mm/yyyy') {
    if (!dateString) return '-';
    
    const date = new Date(dateString);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    
    switch (format) {
        case 'dd/mm/yyyy':
            return `${day}/${month}/${year}`;
        case 'yyyy-mm-dd':
            return `${year}-${month}-${day}`;
        case 'dd MMM yyyy':
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
                          'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
            return `${day} ${months[date.getMonth()]} ${year}`;
        default:
            return dateString;
    }
}

// AJAX helper function
function makeAjaxRequest(url, data = {}, method = 'POST') {
    showLoading();
    
    return $.ajax({
        url: url,
        method: method,
        data: data,
        dataType: 'json'
    }).always(function() {
        hideLoading();
    }).fail(function(xhr, status, error) {
        console.error('AJAX Error:', error);
        showNotification('Terjadi kesalahan saat memproses permintaan', 'danger');
    });
}

// Debounce function for search inputs
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Search functionality
function initSearch() {
    const searchInput = $('.search-input');
    if (searchInput.length) {
        const debouncedSearch = debounce(function(query) {
            performSearch(query);
        }, 500);
        
        searchInput.on('input', function() {
            const query = $(this).val().trim();
            debouncedSearch(query);
        });
    }
}

// Print functionality
function printPage() {
    window.print();
}

// Export functionality
function exportTable(format = 'csv') {
    const table = $('.main-table');
    if (table.length === 0) {
        showNotification('Tidak ada tabel untuk diekspor', 'warning');
        return;
    }
    
    switch (format) {
        case 'csv':
            exportToCSV(table);
            break;
        case 'excel':
            exportToExcel(table);
            break;
        default:
            showNotification('Format ekspor tidak didukung', 'warning');
    }
}

// CSV export
function exportToCSV(table) {
    let csv = '';
    
    // Header
    table.find('thead tr').each(function() {
        let row = '';
        $(this).find('th').each(function() {
            row += `"${$(this).text().trim()}",`;
        });
        csv += row.slice(0, -1) + '\n';
    });
    
    // Body
    table.find('tbody tr').each(function() {
        let row = '';
        $(this).find('td').each(function() {
            row += `"${$(this).text().trim()}",`;
        });
        csv += row.slice(0, -1) + '\n';
    });
    
    // Download
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `export_${new Date().getTime()}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
}

// Keyboard shortcuts
$(document).on('keydown', function(e) {
    // Ctrl+S to save form
    if (e.ctrlKey && e.which === 83) {
        e.preventDefault();
        const form = $('form:visible').first();
        if (form.length) {
            form.submit();
        }
    }
    
    // Esc to close modals
    if (e.which === 27) {
        $('.modal.show').modal('hide');
        $('.photo-modal').fadeOut(300, function() {
            $(this).remove();
        });
    }
});

// Window resize handler
$(window).on('resize', function() {
    // Adjust sidebar for mobile
    if ($(window).width() >= 768) {
        $('#sidebar').removeClass('show');
        $('#sidebarOverlay').removeClass('show');
    }
});

// Page visibility change handler
$(document).on('visibilitychange', function() {
    if (document.hidden) {
        // Page is hidden, pause any animations or timers
        clearInterval(window.refreshTimer);
    } else {
        // Page is visible, resume operations
        // You can add auto-refresh logic here if needed
    }
});

// Global error handler
window.addEventListener('error', function(e) {
    console.error('Global error:', e.error);
    showNotification('Terjadi kesalahan pada aplikasi', 'danger');
});

// Prevent multiple form submissions
$(document).on('submit', 'form', function() {
    const submitBtn = $(this).find('button[type="submit"], input[type="submit"]');
    submitBtn.prop('disabled', true);
    
    setTimeout(function() {
        submitBtn.prop('disabled', false);
    }, 3000);
});

// Auto-refresh data every 5 minutes (for dashboard)
if (window.location.pathname.includes('dashboard') || window.location.pathname === '/randis/') {
    setInterval(function() {
        if (!document.hidden) {
            // Refresh dashboard data
            refreshDashboardData();
        }
    }, 300000); // 5 minutes
}

function refreshDashboardData() {
    // This function should be implemented on each page that needs auto-refresh
    if (typeof window.refreshData === 'function') {
        window.refreshData();
    }
}
