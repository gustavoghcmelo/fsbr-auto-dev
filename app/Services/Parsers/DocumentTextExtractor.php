<?php

namespace App\Services\Parsers;

use RuntimeException;
use Smalot\PdfParser\Parser as PdfParser;
use ZipArchive;

/**
 * Extrai texto plano de documentos enviados pelos analistas.
 * Formatos suportados: PDF, DOCX, TXT, MD.
 */
class DocumentTextExtractor
{
    public function extract(string $absolutePath, string $mimeType, string $originalFilename): string
    {
        return match (true) {
            $this->isPdf($mimeType, $originalFilename) => $this->extractPdf($absolutePath),
            $this->isWord($mimeType, $originalFilename) => $this->extractDocx($absolutePath),
            $this->isPlainText($mimeType, $originalFilename) => $this->extractPlain($absolutePath),
            default => throw new RuntimeException(
                "Formato de arquivo não suportado ({$mimeType})."
            ),
        };
    }

    private function isPdf(string $mime, string $name): bool
    {
        return $mime === 'application/pdf' || str_ends_with(strtolower($name), '.pdf');
    }

    private function isWord(string $mime, string $name): bool
    {
        return $mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            || str_ends_with(strtolower($name), '.docx');
    }

    private function isPlainText(string $mime, string $name): bool
    {
        $lower = strtolower($name);

        return str_starts_with($mime, 'text/')
            || str_ends_with($lower, '.txt')
            || str_ends_with($lower, '.md');
    }

    private function extractPdf(string $path): string
    {
        $parser = new PdfParser();
        $pdf = $parser->parseFile($path);

        return $this->normalise($pdf->getText());
    }

    private function extractDocx(string $path): string
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Não foi possível abrir o arquivo DOCX.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new RuntimeException('DOCX inválido: document.xml ausente.');
        }

        // Preserva quebras de parágrafo antes de remover as tags.
        $xml = preg_replace('/<\/w:p>/', "\n", $xml) ?? $xml;
        $xml = preg_replace('/<w:br\s*\/>/', "\n", $xml) ?? $xml;

        return $this->normalise(html_entity_decode(strip_tags($xml)));
    }

    private function extractPlain(string $path): string
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException('Falha ao ler o arquivo texto.');
        }

        return $this->normalise($content);
    }

    private function normalise(string $text): string
    {
        $text = preg_replace("/\r\n?/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
