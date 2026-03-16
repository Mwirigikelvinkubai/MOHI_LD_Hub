</div><!-- /.content -->
</div><!-- /.main -->

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
$(document).ready(function () {
    // ── DataTables ──
    $('.datatable').DataTable({
        pageLength: 25,
        language: {
            search: '',
            searchPlaceholder: 'Search...',
        },
        dom: '<"d-flex justify-content-between align-items-center mb-3"fl>rt<"d-flex justify-content-between align-items-center mt-3"ip>',
    });

    // ── Chart.js global defaults — MOHI brand ──
    Chart.defaults.color = '#7aaac8';
    Chart.defaults.borderColor = 'rgba(38,169,224,0.12)';
    Chart.defaults.font.family = "'Barlow', sans-serif";

    // ── Mobile sidebar toggle ──
    const sidebar  = document.getElementById('sidebar');
    const overlay  = document.getElementById('sb-overlay');
    const toggle   = document.getElementById('sb-toggle');

    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('active');
        toggle.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
        toggle.classList.remove('open');
        document.body.style.overflow = '';
    }

    if (toggle)  toggle.addEventListener('click', () => sidebar.classList.contains('open') ? closeSidebar() : openSidebar());
    if (overlay) overlay.addEventListener('click', closeSidebar);

    // Close sidebar on nav link click (mobile)
    document.querySelectorAll('.sidebar-nav a').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 768) closeSidebar();
        });
    });

    // ── CSRF: auto-inject token into every POST form ──
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta) {
        const csrfToken = csrfMeta.getAttribute('content');
        document.querySelectorAll('form[method="POST"], form[method="post"]').forEach(function(form) {
            if (!form.querySelector('input[name="csrf_token"]')) {
                const input = document.createElement('input');
                input.type  = 'hidden';
                input.name  = 'csrf_token';
                input.value = csrfToken;
                form.appendChild(input);
            }
        });
    }
});
</script>

<?php if (isset($extraScript)) echo $extraScript; ?>

</body>
</html>