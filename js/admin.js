document.addEventListener('DOMContentLoaded', () => {
	const form = document.getElementById('simplefulltextsearch-form');
	if (!form) {
		return;
	}
	const status = document.getElementById('simplefulltextsearch-status');
	const list = document.getElementById('simplefulltextsearch-scopes');
	// Serverseitig uebersetzte Texte (l10n) — siehe AdminSettings::getForm().
	const S = JSON.parse(form.dataset.strings || '{}');
	const t = (key, fallback, vars) => {
		let s = S[key] || fallback;
		for (const [k, v] of Object.entries(vars || {})) {
			s = s.replace('{' + k + '}', v);
		}
		return s;
	};
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
		status.textContent = t('saving', 'Saving …');
		try {
			const r = await fetch(form.dataset.saveUrl, {
				method: 'POST',
				headers: headers(),
				body: JSON.stringify({ scopes: JSON.stringify(scopes) }),
			});
			const j = await r.json();
			status.textContent = j.status === 'ok'
				? t('saved', 'Saved: {n} search scope(s)', { n: j.scopes.length })
				: t('error', 'Error: ') + (j.message || r.statusText);
		} catch (e) {
			status.textContent = t('error', 'Error: ') + e;
		}
	});

	document.getElementById('simplefulltextsearch-reindex').addEventListener('click', async (ev) => {
		ev.preventDefault();
		status.textContent = t('indexing', 'Indexing … (this may take a while for many documents)');
		try {
			const r = await fetch(form.dataset.reindexUrl, { method: 'POST', headers: headers() });
			const j = await r.json();
			if (j.status === 'ok') {
				const s = j.stats || {};
				status.textContent = t('done', 'Done ({scopes} scopes): {indexed} new/updated, {unchanged} unchanged, {removed} removed', {
					scopes: s.scopes || 0,
					indexed: s.indexed || 0,
					unchanged: s.unchanged || 0,
					removed: s.removed || 0,
				}) + ((s.errors && s.errors.length) ? ' — ' + s.errors.join('; ') : '');
			} else {
				status.textContent = t('error', 'Error: ') + (j.message || r.statusText);
			}
		} catch (e) {
			status.textContent = t('error', 'Error: ') + e;
		}
	});
});
