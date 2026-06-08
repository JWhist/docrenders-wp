(function () {
	'use strict';

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.docrenders-pdf-btn');
		if (!btn) return;

		var postId  = btn.dataset.postId;
		var nonce   = btn.dataset.nonce;
		var wrap    = btn.closest('.docrenders-pdf-wrap');
		var msg     = wrap && wrap.querySelector('.docrenders-pdf-msg');
		var origText = btn.textContent;

		btn.disabled    = true;
		btn.textContent = 'Generating…';
		if (msg) msg.textContent = '';

		fetch(docrendersData.ajaxUrl, {
			method:  'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body:    new URLSearchParams({
				action:  'docrenders_render',
				post_id: postId,
				nonce:   nonce,
			}),
		})
		.then(function (response) {
			if (!response.ok) {
				return response.json().then(function (data) {
					throw new Error(
						(data && data.data && data.data.message) || 'PDF generation failed.'
					);
				});
			}
			return response.blob();
		})
		.then(function (blob) {
			var url = URL.createObjectURL(blob);
			var a   = document.createElement('a');
			a.href     = url;
			a.download = 'document.pdf';
			document.body.appendChild(a);
			a.click();
			document.body.removeChild(a);
			URL.revokeObjectURL(url);
		})
		.catch(function (err) {
			if (msg) {
				msg.textContent = err.message;
				msg.style.color = '#b91c1c';
			}
		})
		.finally(function () {
			btn.disabled    = false;
			btn.textContent = origText;
		});
	});
}());
