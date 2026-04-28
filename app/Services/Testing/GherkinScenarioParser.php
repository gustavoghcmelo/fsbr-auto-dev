<?php

namespace App\Services\Testing;

/**
 * Quebra um texto Gherkin em "cenários" e classifica cada linha em
 * precondition / action / assertion conforme as keywords pt-BR/en.
 */
class GherkinScenarioParser
{
    private const SCENARIO_RE = '/^(\s*)(Esquema do Cenário|Scenario Outline|Cenário|Scenario|Exemplo|Example)\s*:\s*(.*)$/u';

    /**
     * Mapeia palavras-chave canônicas para o tipo do passo.
     *
     * @var array<string, string>
     */
    private const KEYWORDS = [
        'Dado que' => 'precondition',
        'Dado' => 'precondition',
        'Given' => 'precondition',
        'Quando' => 'action',
        'When' => 'action',
        'Então' => 'assertion',
        'Entao' => 'assertion',
        'Then' => 'assertion',
    ];

    private const CONTINUATION = ['E', 'And', 'Mas', 'But'];

    /**
     * Recebe o Gherkin completo de uma feature e devolve os cenários
     * encontrados, cada um com seus passos estruturados.
     *
     * @return list<array{
     *     title: string,
     *     gherkin: string,
     *     steps: list<array{keyword: string, text: string, type: string}>,
     *     preconditions: string,
     *     expected_result: string
     * }>
     */
    public function parse(string $gherkin): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $gherkin) ?: [];
        $scenarios = [];
        $current = null;

        foreach ($lines as $rawLine) {
            if (preg_match(self::SCENARIO_RE, $rawLine, $m)) {
                if ($current !== null) {
                    $scenarios[] = $this->finalize($current);
                }
                $current = [
                    'title' => trim($m[3]),
                    'lines' => [$rawLine],
                    'steps' => [],
                    'lastType' => null,
                ];

                continue;
            }

            if ($current === null) {
                // Ainda estamos nas seções Funcionalidade / Como / Contexto.
                continue;
            }

            $current['lines'][] = $rawLine;

            $step = $this->extractStep($rawLine, $current['lastType']);
            if ($step !== null) {
                $current['steps'][] = $step;
                $current['lastType'] = $step['type'];
            }
        }

        if ($current !== null) {
            $scenarios[] = $this->finalize($current);
        }

        return $scenarios;
    }

    /**
     * @param  array{lines: list<string>, steps: list<array<string, string>>, title: string, lastType: ?string}  $state
     * @return array<string, mixed>
     */
    private function finalize(array $state): array
    {
        $gherkin = rtrim(implode("\n", $state['lines']));

        $preconditions = [];
        $assertions = [];
        foreach ($state['steps'] as $step) {
            if ($step['type'] === 'precondition') {
                $preconditions[] = "{$step['keyword']} {$step['text']}";
            } elseif ($step['type'] === 'assertion') {
                $assertions[] = "{$step['keyword']} {$step['text']}";
            }
        }

        return [
            'title' => $state['title'] ?: 'Cenário sem título',
            'gherkin' => $gherkin,
            'steps' => $state['steps'],
            'preconditions' => implode("\n", $preconditions),
            'expected_result' => implode("\n", $assertions),
        ];
    }

    /**
     * @return array{keyword: string, text: string, type: string}|null
     */
    private function extractStep(string $line, ?string $lastType): ?array
    {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#') || str_starts_with($trimmed, '@')) {
            return null;
        }

        foreach (self::KEYWORDS as $keyword => $type) {
            if (stripos($trimmed, $keyword.' ') === 0) {
                return [
                    'keyword' => $keyword,
                    'text' => trim(substr($trimmed, strlen($keyword) + 1)),
                    'type' => $type,
                ];
            }
        }

        foreach (self::CONTINUATION as $keyword) {
            if (stripos($trimmed, $keyword.' ') === 0) {
                return [
                    'keyword' => $keyword,
                    'text' => trim(substr($trimmed, strlen($keyword) + 1)),
                    'type' => $lastType ?? 'action',
                ];
            }
        }

        return null;
    }
}
