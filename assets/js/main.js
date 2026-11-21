/**
 * CampusFix - Core Client-Side Interactivity
 * Handles responsive sidebar toggling, toasts, form validation, and demo autofills.
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Mobile Sidebar Toggle
    const sidebar = document.querySelector('.app-sidebar');
    const toggleBtn = document.querySelector('.btn-sidebar-toggle');
    const overlay = document.querySelector('.sidebar-overlay');

    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function () {
            sidebar.classList.toggle('show');
            if (overlay) {
                overlay.classList.toggle('show');
            }
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function () {
            if (sidebar) sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }

    // 2. Initialize Bootstrap 5 Tooltips & Popovers
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // 3. Auto-dismissing Flash Alerts
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 6000);
    });

    // 4. Form Client-Side Validation
    const forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    // 5. Demo Account Autofill Pills on Login Page
    const autofillPills = document.querySelectorAll('.btn-autofill');
    autofillPills.forEach(function (pill) {
        pill.addEventListener('click', function () {
            const email = this.getAttribute('data-email');
            const password = this.getAttribute('data-password');
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');

            if (emailInput && passwordInput) {
                emailInput.value = email;
                passwordInput.value = password;
                emailInput.focus();
            }
        });
    });

    // 6. Image File Upload Previewer
    const imageUploadInput = document.getElementById('ticket_photos');
    const previewContainer = document.getElementById('photo_preview_container');

    if (imageUploadInput && previewContainer) {
        imageUploadInput.addEventListener('change', function () {
            previewContainer.innerHTML = '';
            const files = Array.from(this.files);

            if (files.length > 3) {
                alert('You can upload a maximum of 3 photos.');
                this.value = '';
                return;
            }

            files.forEach(function (file) {
                if (!file.type.startsWith('image/')) {
                    alert('File ' + file.name + ' is not an image.');
                    return;
                }

                if (file.size > 5 * 1024 * 1024) {
                    alert('File ' + file.name + ' exceeds 5MB limit.');
                    return;
                }

                const reader = new FileReader();
                reader.onload = function (e) {
                    const col = document.createElement('div');
                    col.className = 'col-4 position-relative';
                    col.innerHTML = `
                        <div class="border rounded overflow-hidden shadow-sm" style="height: 90px;">
                            <img src="${e.target.result}" class="w-100 h-100" style="object-fit: cover;">
                        </div>
                    `;
                    previewContainer.appendChild(col);
                };
                reader.readAsDataURL(file);
            });
        });
    }
});

