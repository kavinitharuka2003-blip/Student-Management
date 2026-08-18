        </div> <!-- /.app-body -->
        
        <!-- Global App Footer -->
        <footer class="mt-auto py-3 px-4 bg-white border-top text-muted small d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
            <div>
                &copy; <?= date('Y') ?> <strong>Student & Course Management System</strong> — University Coursework Project
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-secondary-subtle text-secondary border">PHP 8.x PDO &bull; MySQL &bull; Bootstrap 5</span>
            </div>
        </footer>
    </main>
</div>

<!-- ==============================================================================
     Global Reusable Delete Confirmation Modal
     Used across Students, Courses, and Enrollments for safe POST-based deletion
     ============================================================================== -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header bg-danger text-white border-0 py-3">
                <h5 class="modal-title font-heading fw-bold d-flex align-items-center gap-2" id="deleteModalLabel">
                    <i class="bi bi-exclamation-triangle-fill"></i> Confirm Deletion
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="globalDeleteForm" method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="deleteModalRecordId" value="">
                
                <div class="modal-body p-4">
                    <p class="mb-2 text-dark fs-6" id="deleteModalMessage">
                        Are you sure you want to permanently delete this record?
                    </p>
                    <div class="alert alert-warning border-0 small mb-0 py-2">
                        <i class="bi bi-info-circle me-1"></i> <strong>Warning:</strong> This operation cannot be undone and may cascade to related records.
                    </div>
                </div>
                
                <div class="modal-footer bg-light border-0 py-3">
                    <button type="button" class="btn btn-light px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4 fw-semibold" id="deleteModalSubmitBtn">
                        <i class="bi bi-trash-fill me-1"></i> Yes, Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap 5.3.3 JS Bundle (Popper included) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom App Scripts -->
<script src="<?= e(get_app_url('assets/js/validation.js')) ?>"></script>
<script src="<?= e(get_app_url('assets/js/main.js')) ?>"></script>

</body>
</html>
