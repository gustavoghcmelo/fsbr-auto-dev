<?php

namespace App\Services\AI;

use OpenAI\Laravel\Facades\OpenAI;
use RuntimeException;

/**
 * Pega o texto de um documento de requisitos de cliente e
 * devolve um array de features estruturadas em Gherkin + HTML
 * pronto para o QEditor.
 */
class RequirementGenerator
{
    /**
     * @return list<array{
     *     title: string,
     *     description: string,
     *     context: string,
     *     acceptance_criteria: list<string>,
     *     gherkin: string
     * }>
     */
    public function generate(string $sourceText): array
    {
        $response = OpenAI::chat()->create([
            'model' => config('openai.model', 'gpt-4o-mini'),
            'messages' => [
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user', 'content' => $this->userPrompt($sourceText)],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'requirements',
                    'strict' => true,
                    'schema' => $this->jsonSchema(),
                ],
            ],
        ]);

        $content = $response->choices[0]->message->content ?? null;

        if (! $content) {
            throw new RuntimeException('OpenAI retornou resposta vazia.');
        }

        $decoded = json_decode($content, true, flags: JSON_THROW_ON_ERROR);

        return $decoded['requirements'] ?? [];
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Você é um analista de requisitos especialista e sua função é transformar
documentos de negócio em features de software estruturadas no padrão Gherkin,
prontas para serem implementadas por desenvolvedores e testadas por QA.

Receberá como entrada um texto bruto (requisitos acordados com o cliente) e
deve produzir um array JSON de "requirements". Cada item do array representa
uma feature coesa e independente.

Para cada feature, gere:
- title: título curto (máx. 80 caracteres).
- description: narrativa em formato "Como <papel>, eu quero <ação>,
  para que <benefício>".
- context: contexto de negócio e regras relevantes para a feature.
- acceptance_criteria: array de strings testáveis, no imperativo
  ("O sistema deve ...").
- gherkin: TEXTO PURO no formato de um arquivo .feature, em português
  brasileiro, usando os keywords oficiais do Gherkin em pt-BR. Estrutura:

    Funcionalidade: <título>

      Como <papel>
      Quero <ação>
      Para <benefício>

      Contexto:
        Dado <pré-condição>
        E <pré-condição>

      Cenário: <nome do cenário>
        Dado <contexto>
        Quando <ação>
        Então <resultado esperado>
        E <verificação adicional>

  Inclua pelo menos um Cenário por feature. Use Contexto (Background)
  quando fizer sentido. Use 2 espaços de indentação por nível. NÃO
  retorne HTML, Markdown, blocos de código ou texto fora da estrutura
  .feature — apenas o conteúdo puro do arquivo.

Regras:
- Seja fiel ao documento. NÃO invente requisitos que não estejam no texto.
- Divida em múltiplas features quando o escopo for amplo.
- Escreva sempre em português brasileiro.
PROMPT;
    }

    private function userPrompt(string $sourceText): string
    {
        return "Documento de requisitos do cliente:\n\n---\n\n{$sourceText}\n\n---\n\n"
            ."Gere as features em JSON conforme o schema definido.";
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'requirements' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'context' => ['type' => 'string'],
                            'acceptance_criteria' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                            ],
                            'gherkin' => ['type' => 'string'],
                        ],
                        'required' => [
                            'title', 'description', 'context',
                            'acceptance_criteria', 'gherkin',
                        ],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['requirements'],
            'additionalProperties' => false,
        ];
    }
}
