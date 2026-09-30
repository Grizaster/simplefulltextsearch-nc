document.addEventListener('DOMContentLoaded', () => {
	const form = document.getElementById('simplefulltextsearch-form');
	if (!form) {
		return;
	}
	const status = document.getElementById('simplefulltextsearch-status');
	const list = document.getElementById('simplefulltextsearch-scopes');
	const headers = () => ({
		'Content-Type': 'application/json',
		requesttoken: document.head.getAttribute('data-requesttoken'),
	});

	const collect = () => [...list.querySelectorAll('.simplefulltextsearch-scope-row')]
		.map((row) => ({
			owner: row.querySelector('.simplefulltextsearch-scope-owner').value.trim(),
			folder: row.querySelector('.simplefulltextsearch-scope-folder').value.trim(),
		}))
		.filter((s) => s.owner !== '' && s.folder !== '');

	list.addEventListener('click', (ev) => {
		const btn = ev.target.closest('.simplefulltextsearch-scope-remove');
		if (!btn) {
			return;
		}
		ev.preventDefault();
		if (list.querySelectorAll('.simplefulltextsearch-scope-row').length > 1) {
			btn.closest('.simplefulltextsearch-scope-row').remove();
		}
	});

	document.getElementById('simplefulltextsearch-scope-add').addEventListener('click', (ev) => {
		ev.preventDefault();
		const template = list.querySelector('.simplefulltextsearch-scope-row');
		if (!template) {
			return;
		}
		const row = template.cloneNode(true);
		row.querySelector('.simplefulltextsearch-scope-owner').value = '';
		row.querySelector('.simplefulltextsearch-scope-folder').value = '';
		list.appendChild(row);
	});

	document.getElementById('simplefulltextsearch-save').addEventListener('click', async (ev) => {
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

	document.getElementById('simplefulltextsearch-reindex').addEventListener('click', async (ev) => {
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
