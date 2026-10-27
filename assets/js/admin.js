document.addEventListener('DOMContentLoaded', function () {
    const toastContainer = document.querySelector('.toast-container');

    function showToast(message, type = 'success') {
        const toastEl = document.createElement('div');
        toastEl.className = `toast align-items-center text-bg-${type} border-0`;
        toastEl.setAttribute('role', 'alert');
        toastEl.setAttribute('aria-live', 'assertive');
        toastEl.setAttribute('aria-atomic', 'true');
        toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;
        toastContainer.appendChild(toastEl);
        const toast = new bootstrap.Toast(toastEl, { delay: 5000 });
        toast.show();
        toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
    }

    window.adminShowToast = showToast;

    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.toggle('open');
            document.body.classList.toggle('sidebar-open');
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function () {
            sidebar.classList.remove('open');
            document.body.classList.remove('sidebar-open');
        });
    }

    const currentDateTime = document.getElementById('currentDateTime');
    if (currentDateTime) {
        function updateDateTime() {
            const now = new Date();
            const options = { weekday: 'long', month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' };
            currentDateTime.textContent = now.toLocaleDateString('en-US', options);
        }
        updateDateTime();
        setInterval(updateDateTime, 60000);
    }

    if (window.jQuery && $.fn.dataTable) {
        $('.datatable').each(function () {
            const $table = $(this);
            const thCount = $table.find('thead th').length || $table.find('tbody tr:first td').length || 0;
            const columns = Array.from({ length: thCount }, () => null);
            const hasCertificateNumber = $table.data('certificate-number') === true || $table.data('certificate-number') === 'true';
            const options = {
                responsive: true,
                pageLength: 10,
                lengthChange: false,
                searching: true,
                ordering: true,
                info: false,
                language: { emptyTable: 'No records available in the table.' },
                columns: columns
            };

            if (hasCertificateNumber) {
                options.columnDefs = [{ targets: 1, orderable: false, searchable: true }];
                options.drawCallback = function (settings) {
                    const api = this.api();
                    const info = api.page.info();
                    api.rows({ page: 'current' }).every(function (rowIndex, tableLoop, rowLoop) {
                        const node = $(this.node()).find('.certificate-number');
                        node.text(info.start + rowLoop + 1);
                    });
                };
            }

            $table.DataTable(options);
        });
    }
});
