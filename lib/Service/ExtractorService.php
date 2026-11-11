<?php

declare(strict_types=1);

namespace OCA\SimpleFullTextFileSearch\Service;

use Psr\Log\LoggerInterface;

/**
 * Text-Extraktion ohne PHP-Extensions: pdftotext (schnell), ocrmypdf --sidecar
 * (OCR fuer Scans), unzip fuer docx/odt, Klaertext direkt.
 */
class ExtractorService {
	public const MAX_BYTES = 26214400; // 25 MB
	private const MAX_CHARS = 400000;   // Index-Budget je Dokument
	private const MAX_OUT = 2097152;    // 2 MB Deckel fuer Konsolen-Ausgabe (Zip-Bomben/Textmassen)

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
				=> $this->readCapped($localPath, self::MAX_CHARS * 4),
			in_array($ext, ['html', 'htm'], true)
				=> html_entity_decode(strip_tags($this->readCapped($localPath, self::MAX_CHARS * 4)), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
			$ext === 'docx' => $this->fromZip($localPath, 'word/document.xml'),
			$ext === 'odt' => $this->fromZip($localPath, 'content.xml'),
			default => '',
		};
		$text = preg_replace('/\s+/u', ' ', $this->ensureUtf8($text)) ?? '';
		return mb_substr(trim($text), 0, self::MAX_CHARS);
	}

	/**
	 * Nicht-UTF8-Dateien (z. B. Windows-1252) duerfen nicht still verloren gehen:
	 * PCRE mit /u-Modifier gibt bei ungueltigem UTF-8 null zurueck und wuerde den
	 * gesamten Text auf '' kollabieren lassen.
	 */
	private function ensureUtf8(string $text): string {
		if ($text !== '' && !mb_check_encoding($text, 'UTF-8')) {
			return mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1, Windows-1252, ASCII');
		}
		return $text;
	}

	private static ?bool $hasOcr = null;

	/** ocrmypdf ist optional — einmal pruefen statt pro Scan ins Log zu muellen. */
	private function hasOcr(): bool {
		if (self::$hasOcr === null) {
			$out = [];
			$rc = 1;
			exec('command -v ocrmypdf >/dev/null 2>&1', $out, $rc);
			self::$hasOcr = $rc === 0;
			if (!self::$hasOcr) {
				$this->logger?->info('simplefulltextsearch: ocrmypdf nicht installiert — Scans ohne Textlayer werden uebersprungen (optional, einmalige Meldung).');
			}
		}
		return self::$hasOcr;
	}

	private function fromPdf(string $p): string {
		// 1) digitale PDFs: schneller Textlayer
		$t = $this->sh(['pdftotext', '-enc', 'UTF-8', '-nopgbrk', $p, '-']);
		if (trim($t) !== '') {
			return $t;
		}
		// 2) Scans: OCR ueber ocrmypdf (Sidecar = Gesamttext)
		if (!$this->hasOcr()) {
			return '';
		}
		// tempnam-Erzeugnisse ohne Dateiendungs-Dopplung nutzen und immer sauber im finally loeschen
		$sidecar = tempnam(sys_get_temp_dir(), 'elds_s_');
		$outPdf = tempnam(sys_get_temp_dir(), 'elds_o_');
		try {
			$t = $this->sh(['ocrmypdf', '--skip-text', '--quiet', '--sidecar', $sidecar, $p, $outPdf]);
			$side = $this->readCapped($sidecar, self::MAX_CHARS * 4);
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
		$outFile = tempnam(sys_get_temp_dir(), 'elds_out_');
		try {
			$errArg = $errFile !== false ? ' 2>' . escapeshellarg($errFile) : ' 2>/dev/null';
			$outArg = $outFile !== false ? ' >' . escapeshellarg($outFile) : ' >/dev/null';
			// Platten-Deckel: 'head -c' begrenzt die BESCHREIBUNG auf der Festplatte
			// (eine Zip-Bombe darf /tmp nicht volllaufen lassen) — nach dem Deckel
			// beendet SIGPIPE den Schreibprozess. pipefail reicht den Exit-Code des
			// Tools durch; 141 (128+SIGPIPE) ist die erwartete, erfolgreiche Deckelung.
			$inner = 'timeout 120 ' . implode(' ', array_map('escapeshellarg', $args))
				. $errArg . ' | head -c ' . self::MAX_OUT . $outArg;
			$rc = 0;
			$ignore = [];
			exec('bash -c ' . escapeshellarg('set -o pipefail; ' . $inner), $ignore, $rc);
			if ($rc !== 0 && $rc !== 141) {
				$err = $this->readCapped($errFile, 4096);
				$this->logger?->warning('simplefulltextsearch: Extractor CLI-Fehler bei {cmd}: Exit-Code {rc}, Fehler: {err}', [
					'cmd' => $args[0] ?? '',
					'rc' => $rc,
					'err' => $err,
				]);
				return '';
			}
			return $this->readCapped($outFile, self::MAX_OUT);
		} finally {
			foreach ([$errFile, $outFile] as $f) {
				if ($f !== false && is_file($f)) {
					@unlink($f);
				}
			}
		}
	}

	/** Begrenzter Datei-Read (nie ungebunden in den Speicher, auch nicht bei Bomben). */
	private function readCapped(string|false $path, int $max): string {
		if ($path === false || !is_file($path)) {
			return '';
		}
		return (string)@file_get_contents($path, false, null, 0, $max);
	}
}
