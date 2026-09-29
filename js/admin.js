document.addEventListener('DOMContentLoaded', () => {
	const form = document.getElementById('dossier-form');
	if (!form) {
		return;
	}
	const status = document.getElementById('dossier-status');
	const list = document.getElementById('dossier-scopes');
	const headers = () => ({
		'Content-Type': 'application/json',
		requesttoken: document.head.getAttribute('data-requesttoken'),
	});

	const collect = () => [...list.querySelectorAll('.dossier-scope-row')]
		.map((row) => ({
			owner: row.querySelector('.dossier-scope-owner').value.trim(),
			folder: row.querySelector('.dossier-scope-folder').value.trim(),
		}))
		.filter((s) => s.owner !== '' && s.folder !== '');

	list.addEventListener('click', (ev) => {
		const btn = ev.target.closest('.dossier-scope-remove');
		if (!btn) {
			return;
		}
		ev.preventDefault();
		if (list.querySelectorAll('.dossier-scope-row').length > 1) {
			btn.closest('.dossier-scope-row').remove();
		}
	});

	document.getElementById('dossier-scope-add').addEventListener('click', (ev) => {
		ev.preventDefault();
		const template = list.querySelector('.dossier-scope-row');
		if (!template) {
			return;
		}
		const row = template.cloneNode(true);
		row.querySelector('.dossier-scope-owner').value = '';
		row.querySelector('.dossier-scope-folder').value = '';
		list.appendChild(row);
	});

	document.getElementById('dossier-save').addEventListener('click', async (ev) => {
		ev.preventDefault();
		const scopes = collect();
		status.textContent = 'Speichere …';
		try {
			const r = await fetch(form.dataset.saveUrl, {
				method: 'POST',
				headers: headers(),
				body: JSON.stringify({ scopes: JSON.stringify(scopes) }),
			});
			const j = await r.json();
			status.textContent = j.status === 'ok'
				? 'Gespeichert: ' + j.scopes.length + ' Suchbereich(e)'
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
				status.textContent = 'Fertig (' + (s.scopes || 0) + ' Bereiche): '
					+ (s.indexed || 0) + ' neu/aktualisiert, '
					+ (s.unchanged || 0) + ' unverändert, ' + (s.removed || 0) + ' entfernt'
					+ ((s.errors && s.errors.length) ? ' — ' + s.errors.join('; ') : '');
			} else {
				status.textContent = 'Fehler: ' + (j.message || r.statusText);
			}
		} catch (e) {
			status.textContent = 'Fehler: ' + e;
		}
	});
});
