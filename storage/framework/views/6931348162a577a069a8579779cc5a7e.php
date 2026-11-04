<section data-verification-pdf data-src="<?php echo e(route('admin.verifications.document', $document)); ?>" aria-label="Xem PDF <?php echo e($document->document_name); ?>">
    <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
        <button class="btn btn-sm btn-outline-primary" type="button" data-pdf-previous disabled>Trang trước</button>
        <span role="status" data-pdf-status>Đang tải PDF...</span>
        <button class="btn btn-sm btn-outline-primary" type="button" data-pdf-next disabled>Trang sau</button>
        <label class="d-flex align-items-center gap-2">Phóng to <select class="form-select form-select-sm" data-pdf-zoom aria-label="Phóng to PDF"><option value="1">100%</option><option value="1.5">150%</option><option value="2">200%</option></select></label>
    </div>
    <div style="overflow:auto;max-height:70vh;background:#f2f4f3"><canvas aria-label="Trang PDF <?php echo e($document->document_name); ?>" style="display:block;margin:0 auto;max-width:100%"></canvas></div>
</section>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/admin_edumatchv1/resources/views/admin/verifications/pdf-preview.blade.php ENDPATH**/ ?>