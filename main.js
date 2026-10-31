/**
 * SubNexa Pro - Main JavaScript Engine
 * Vanilla JavaScript (No heavy framework dependencies, <2s page response)
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Dismiss Preloader smoothly
    const preloader = document.getElementById('page-preloader');
    if (preloader) {
        setTimeout(function () {
            preloader.classList.add('loaded');
        }, 150);
    }

    // 2. Initialize Mobile Sidebar Toggle
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.querySelector('.app-sidebar');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.toggle('show');
        });
    }

    // 3. Instant Live Table Search
    document.querySelectorAll('[data-table-search]').forEach(function (searchInput) {
        const targetTableSelector = searchInput.getAttribute('data-table-search');
        const targetTable = document.querySelector(targetTableSelector);

        if (targetTable) {
            searchInput.addEventListener('input', function () {
                const query = this.value.toLowerCase().trim();
                const rows = targetTable.querySelectorAll('tbody tr');

                rows.forEach(function (row) {
                    const text = row.textContent.toLowerCase();
                    if (text.includes(query)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }
    });
});

/**
 * 1-Click Fast Login Auto-Fill Function
 */
function fillDemoCredentials(role) {
    const emailField = document.getElementById('email');
    const passwordField = document.getElementById('password');

    if (!emailField || !passwordField) return;

    let email = '';
    const password = 'Demo@12345';
    let roleName = '';

    if (role === 'admin') {
        email = 'admin@brightloop.com';
        roleName = 'Super Admin (Executive)';
    } else if (role === 'staff') {
        email = 'staff@brightloop.com';
        roleName = 'Internal Staff (Sales & Billing)';
    } else if (role === 'customer') {
        email = 'client@apex.com';
        roleName = 'Customer (Client Portal)';
    }

    // Gentle typing effect / fill
    emailField.value = email;
    passwordField.value = password;

    // Visual highlight effect
    emailField.style.borderColor = '#0284C7';
    passwordField.style.borderColor = '#0284C7';

    setTimeout(() => {
        emailField.style.borderColor = '';
        passwordField.style.borderColor = '';
    }, 1000);

    // Toast notification
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'info',
            title: `Credentials loaded for: ${roleName}`,
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true
        });
    }
}

/**
 * SweetAlert2 Toast Helper
 */
function showToast(icon, message) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: icon,
            title: message,
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
    } else {
        alert(message);
    }
}

/**
 * Confirm Action Modal
 */
function confirmAction(title, text, confirmButtonText, callback) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: title,
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0284C7',
            cancelButtonColor: '#64748B',
            confirmButtonText: confirmButtonText,
            cancelButtonText: 'Cancel',
            borderRadius: '12px'
        }).then((result) => {
            if (result.isConfirmed && typeof callback === 'function') {
                callback();
            }
        });
    } else {
        if (confirm(text)) {
            callback();
        }
    }
}
