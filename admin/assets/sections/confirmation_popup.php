<div class="modal" id="confirmation-popup" style="display: none;">
	<div class="modal-container">
		<div class="modal-container-header">
			<h1 class="modal-container-title">
				-שגיאה-
			</h1>
			<button class="icon-button" id="exit-btn">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
					<path fill="none" d="M0 0h24v24H0z" />
					<path fill="currentColor" d="M12 10.586l4.95-4.95 1.414 1.414-4.95 4.95 4.95 4.95-1.414 1.414-4.95-4.95-4.95 4.95-1.414-1.414 4.95-4.95-4.95-4.95L7.05 5.636z" />
				</svg>
			</button>
		</div>
		<div class="modal-container-footer">
			<button class="button is-ghost" id="cancel-btn">ביטול</button>
			<button class="button is-primary" id="confirm-btn">אישור</button>
		</div>
	</div>
</div>

<script>
	document.querySelectorAll('.confirmation').forEach(link => {
		link.addEventListener('click', function(event) {
			event.preventDefault();

			const href = this.href;
			const confirmText = this.getAttribute('data-confirm-text');
			const popup = document.getElementById('confirmation-popup');

			document.querySelector('.modal-container-title').textContent = confirmText;

			popup.style.display = 'block';

			document.getElementById('confirm-btn').onclick = function() {
				window.location.href = href;
			};

			document.getElementById('cancel-btn').onclick = function() {
				popup.style.display = 'none';
			};

			document.getElementById('exit-btn').onclick = function() {
				popup.style.display = 'none';
			};

			popup.addEventListener('click', function(event) {
				if (event.target === popup) {
					popup.style.display = 'none';
				}
			});
		});
	});
</script>