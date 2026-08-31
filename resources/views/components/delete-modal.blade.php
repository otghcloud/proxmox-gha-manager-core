{{-- Shared confirmation dialog driven by [data-action="delete-modal"] links. --}}
<div class="modal modal-blur fade" id="delete-modal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-sm modal-dialog-centered" role="document">
		<div class="modal-content">
			<button aria-label="Close" class="btn-close" data-bs-dismiss="modal" type="button"></button>
			<div class="modal-status bg-danger"></div>
			<div class="modal-body text-center py-4">
				<i class="fa-solid fa-triangle-exclamation fa-3x text-danger mb-3"></i>
				<h3>Are you sure?</h3>
				<div class="text-secondary" data-delete-message></div>
			</div>
			<div class="modal-footer">
				<div class="w-100">
					<div class="row">
						<div class="col">
							<button class="btn w-100" data-bs-dismiss="modal" type="button">Cancel</button>
						</div>
						<div class="col">
							<form method="POST" action="">
								@csrf
								@method('DELETE')
								<button class="btn btn-danger w-100" type="submit">Delete</button>
							</form>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
