document.addEventListener('DOMContentLoaded', () => {
	const form = document.getElementById('dossier-form');
	if (!form) {
		return;
	}
	const status = document.getElementById('dossier-status');
	const headers = () => ({
		'Content-Type': 'application/json',
		requesttoken: document.head.getAttribute('data-requesttoken'),
	});

	document.getElementById('dossier-save').addEventListener('click', async (ev) => {
		ev.preventDefault();
		status.textContent = 'Speichere …';
		try {
			const r = await fetch(form.dataset.saveUrl, {
				method: 'POST',
				headers: headers(),
				body: JSON.stringify({
					scopeOwner: document.getElementById('dossier-owner').value,
					scopeFolder: document.getElementById('dossier-folder').value,
				}),
			});
			const j = await r.json();
			status.textContent = j.status === 'ok'
				? 'Gespeichert: ' + j.owner + ' → ' + j.folder
				: 'Fehler: ' + (j.message || r.statusText);
		} catch (e) {
			status.textContent = 'Fehler: ' + e;
		}
	});

	document.getElementById('dossier-reindex').addEventListener('click', async (ev) => {
		ev.preventDefault();
		status.textContent = 'Indiziere … (kann bei vielen Dokumenten etwas dauern)';
		try {
			const r = await fetch(form.dataset.reindexUrl, { method: 'POST', headers: headers() });
			const j = await r.json();
			if (j.status === 'ok') {
				const s = j.stats || {};
				status.textContent = 'Fertig: ' + (s.indexed || 0) + ' neu/aktualisiert, '
					+ (s.unchanged || 0) + ' unverändert, ' + (s.removed || 0) + ' entfernt'
					+ (s.error ? ' — ' + s.error : '');
			} else {
				status.textContent = 'Fehler: ' + (j.message || r.statusText);
			}
		} catch (e) {
			status.textContent = 'Fehler: ' + e;
		}
	});
});
