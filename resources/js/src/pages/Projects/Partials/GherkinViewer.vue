<script setup>
import { computed } from 'vue'

const props = defineProps({
    text: { type: String, default: '' },
})

const PATTERNS = [
    { re: /^(\s*)(Funcionalidade|Feature)(:\s*)/, cls: 'gh-feature' },
    { re: /^(\s*)(Contexto|Background)(:\s*)/, cls: 'gh-bg' },
    {
        re: /^(\s*)(Esquema do Cenário|Scenario Outline|Cenário|Scenario|Exemplo|Example)(:\s*)/,
        cls: 'gh-scenario',
    },
    { re: /^(\s*)(Dado que|Dado|Given)(\s+)/, cls: 'gh-given' },
    { re: /^(\s*)(Quando|When)(\s+)/, cls: 'gh-when' },
    { re: /^(\s*)(Então|Entao|Then)(\s+)/, cls: 'gh-then' },
    { re: /^(\s*)(E|And)(\s+)/, cls: 'gh-and' },
    { re: /^(\s*)(Mas|But)(\s+)/, cls: 'gh-but' },
]

function escape(s) {
    return s
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
}

function highlightStrings(text) {
    return text.replace(
        /&quot;([^&]*?)&quot;/g,
        '<span class="gh-string">&quot;$1&quot;</span>'
    )
}

function highlightLine(rawLine) {
    if (/^\s*#/.test(rawLine)) {
        return `<span class="gh-comment">${escape(rawLine)}</span>`
    }

    for (const p of PATTERNS) {
        const m = rawLine.match(p.re)
        if (m) {
            const [full, indent, keyword, separator] = m
            const rest = rawLine.slice(full.length)
            return (
                escape(indent) +
                `<span class="${p.cls}">${escape(keyword)}</span>` +
                escape(separator) +
                highlightStrings(escape(rest))
            )
        }
    }

    // Destaca linhas de tabela (|...|)
    if (/^\s*\|/.test(rawLine)) {
        return `<span class="gh-pipe">${escape(rawLine)}</span>`
    }

    return highlightStrings(escape(rawLine))
}

const highlighted = computed(() =>
    (props.text ?? '').split('\n').map(highlightLine).join('\n')
)
</script>

<template>
    <pre class="gherkin-pre" v-html="highlighted"></pre>
</template>

<style scoped>
.gherkin-pre {
    background: #0f172a;
    color: #e2e8f0;
    padding: 20px 24px;
    font-family: 'Cascadia Code', 'Fira Code', 'JetBrains Mono', 'Consolas',
        monospace;
    font-size: 14px;
    line-height: 1.7;
    border-radius: 8px;
    overflow: auto;
    margin: 0;
    white-space: pre;
    tab-size: 2;
}
</style>

<style>
.gherkin-pre .gh-feature {
    color: #c084fc;
    font-weight: 700;
}
.gherkin-pre .gh-bg {
    color: #60a5fa;
    font-weight: 700;
}
.gherkin-pre .gh-scenario {
    color: #34d399;
    font-weight: 700;
}
.gherkin-pre .gh-given {
    color: #fb923c;
    font-weight: 600;
}
.gherkin-pre .gh-when {
    color: #60a5fa;
    font-weight: 600;
}
.gherkin-pre .gh-then {
    color: #4ade80;
    font-weight: 600;
}
.gherkin-pre .gh-and {
    color: #a5b4fc;
    font-weight: 600;
}
.gherkin-pre .gh-but {
    color: #f87171;
    font-weight: 600;
}
.gherkin-pre .gh-comment {
    color: #64748b;
    font-style: italic;
}
.gherkin-pre .gh-string {
    color: #fde68a;
}
.gherkin-pre .gh-pipe {
    color: #94a3b8;
}
</style>
