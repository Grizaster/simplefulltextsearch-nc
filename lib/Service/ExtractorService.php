<?php

declare(strict_types=1);

namespace OCA\SimpleFullTextFileSearch\Service;

use Psr\Log\LoggerInterface;

/**
 * Text-Extraktion ohne PHP-Extensions: pdftotext (schnell), ocrmypdf --sidecar
 * (OCR fuer Scans), unzip fuer docx/odt, Klaertext direkt.
 */
class ExtractorService {
	private const MAX_BYTES = 26214400; // 25 MB
	private const MAX_CHARS = 400000;   // Index-Budget je Dokument

	public function __construct(
		private ?LoggerInterface $logger = null,
	) {
	}

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
		// tempnam-Erzeugnisse ohne Dateiendungs-Dopplung nutzen und immer sauber im finally loeschen
		$sidecar = tempnam(sys_get_temp_dir(), 'elds_s_');
		$outPdf = tempnam(sys_get_temp_dir(), 'elds_o_');
		try {
			$t = $this->sh(['ocrmypdf', '--skip-text', '--quiet', '--sidecar', $sidecar, $p, $outPdf]);
			$side = ($sidecar !== false && is_file($sidecar)) ? (string)@file_get_contents($sidecar) : '';
			return $side !== '' ? $side : $t;
		} finally {
			if ($sidecar !== false && is_file($sidecar)) {
				@unlink($sidecar);
			}
			if ($outPdf !== false && is_file($outPdf)) {
				@unlink($outPdf);
			}
		}
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
		$errFile = tempnam(sys_get_temp_dir(), 'elds_err_');
		try {
			$errArg = $errFile !== false ? ' 2>' . escapeshellarg($errFile) : ' 2>/dev/null';
			$cmd = 'timeout 120 ' . implode(' ', array_map('escapeshellarg', $args)) . $errArg;
			$out = [];
			$rc = 0;
			exec($cmd, $out, $rc);
			if ($rc !== 0) {
				$err = ($errFile !== false && is_file($errFile)) ? trim((string)@file_get_contents($errFile)) : '';
				$this->logger?->warning('simplefulltextsearch: Extractor CLI-Fehler bei {cmd}: Exit-Code {rc}, Fehler: {err}', [
					'cmd' => $args[0] ?? '',
					'rc' => $rc,
					'err' => $err,
				]);
				return '';
			}
			return implode("\n", $out);
		} finally {
			if ($errFile !== false && is_file($errFile)) {
				@unlink($errFile);
			}
		}
	}
}
