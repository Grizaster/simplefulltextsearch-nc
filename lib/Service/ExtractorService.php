<?php

declare(strict_types=1);

namespace OCA\SimpleFullTextFileSearch\Service;

/**
 * Text-Extraktion ohne PHP-Extensions: pdftotext (schnell), ocrmypdf --sidecar
 * (OCR fuer Scans), unzip fuer docx/odt, Klaertext direkt.
 */
class ExtractorService {
	private const MAX_BYTES = 26214400; // 25 MB
	private const MAX_CHARS = 400000;   // Index-Budget je Dokument

	public function extract(string $localPath, string $name): string {
		if (!is_file($localPath) || filesize($localPath) > self::MAX_BYTES) {
			return '';
		}
		$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
		$text = match (true) {
			$ext === 'pdf' => $this->fromPdf($localPath),
			in_array($ext, ['txt', 'md', 'markdown', 'csv', 'json', 'log', 'xml', 'yaml', 'yml'], true)
				=> (string)@file_get_contents($localPath),
			in_array($ext, ['html', 'htm'], true)
				=> html_entity_decode(strip_tags((string)@file_get_contents($localPath)), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
			$ext === 'docx' => $this->fromZip($localPath, 'word/document.xml'),
			$ext === 'odt' => $this->fromZip($localPath, 'content.xml'),
			default => '',
		};
		$text = preg_replace('/\s+/u', ' ', $text) ?? '';
		return mb_substr(trim($text), 0, self::MAX_CHARS);
	}

	private function fromPdf(string $p): string {
		// 1) digitale PDFs: schneller Textlayer
		$t = $this->sh(['pdftotext', '-enc', 'UTF-8', '-nopgbrk', $p, '-']);
		if (trim($t) !== '') {
			return $t;
		}
		// 2) Scans: OCR ueber ocrmypdf (Sidecar = Gesamttext)
		$sidecar = tempnam(sys_get_temp_dir(), 'elds_') . '.txt';
		$outPdf = tempnam(sys_get_temp_dir(), 'elds_') . '.pdf';
		$t = $this->sh(['ocrmypdf', '--skip-text', '--quiet', '--sidecar', $sidecar, $p, $outPdf]);
		$side = is_file($sidecar) ? (string)@file_get_contents($sidecar) : '';
		@unlink($sidecar);
		@unlink($outPdf);
		return $side !== '' ? $side : $t;
	}

	private function fromZip(string $p, string $member): string {
		$xml = $this->sh(['unzip', '-p', $p, $member]);
		if ($xml === '') {
			return '';
		}
		return html_entity_decode(strip_tags(str_replace('</w:p>', "\n", $xml)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}

	/** @param list<string> $args */
	private function sh(array $args): string {
		$cmd = 'timeout 120 ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>/dev/null';
		$out = [];
		$rc = 0;
		exec($cmd, $out, $rc);
		return $rc === 0 ? implode("\n", $out) : '';
	}
}
