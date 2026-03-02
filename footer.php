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
    // Auto-init DataTables
    $('.datatable').DataTable({
        pageLength: 25,
        language: {
            search: '',
            searchPlaceholder: 'Search...',
        },
        dom: '<"d-flex justify-content-between align-items-center mb-3"fl>rt<"d-flex justify-content-between align-items-center mt-3"ip>',
    });

    // Chart.js global defaults — MOHI brand
    Chart.defaults.color = '#7aaac8';
    Chart.defaults.borderColor = 'rgba(38,169,224,0.12)';
    Chart.defaults.font.family = "'Barlow', sans-serif";
});
</script>

<?php if (isset($extraScript)) echo $extraScript; ?>

</body>
</html>
