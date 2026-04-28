<script setup>
import { Head, router, useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import AuthenticatedLayout from '../../../layouts/AuthenticatedLayout.vue'
import TestPlanDialog from '../Partials/TestPlanDialog.vue'
import TestCaseEditor from './Partials/TestCaseEditor.vue'

const props = defineProps({
    project: { type: Object, required: true },
    plan: { type: Object, required: true },
    requirements: {
        type: Object,
        default: () => ({ approved_total: 0, pending_generation: [] }),
    },
    statuses: { type: Array, default: () => [] },
})

const $q = useQuasar()

const editingCase = ref(null)
const showCaseEditor = computed({
    get: () => !!editingCase.value,
    set: (v) => {
        if (!v) editingCase.value = null
    },
})

const showPlanDialog = ref(false)

const casesByRequirement = computed(() => {
    const groups = new Map()
    for (const c of props.plan.cases) {
        const key = c.requirement?.id ?? 'manual'
        const label = c.requirement?.title ?? 'Criados manualmente'
        if (!groups.has(key)) {
            groups.set(key, {
                key,
                label,
                requirement: c.requirement,
                cases: [],
            })
        }
        groups.get(key).cases.push(c)
    }
    return Array.from(groups.values())
})

function formatDate(iso) {
    if (!iso) return ''
    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(iso))
}

function latestReopenFor(group) {
    return group.requirement?.latest_reopen?.qa_approval ?? { status: null }
}

// Mantém o case aberto no dialog sincronizado quando o payload recarregar
// (após aprovar pro dev, solicitar reabertura, editar, etc.).
watch(
    () => props.plan.cases,
    (cases) => {
        if (!editingCase.value) return
        const fresh = cases.find((c) => c.id === editingCase.value.id)
        if (fresh) editingCase.value = fresh
    },
    { deep: true }
)

const generating = ref(false)

function generateCases() {
    generating.value = true
    router.post(
        `/projects/${props.project.id}/test-plans/${props.plan.id}/generate-cases`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                generating.value = false
            },
        }
    )
}

function openCase(tc) {
    editingCase.value = tc
}

function destroyPlan() {
    $q.dialog({
        title: 'Remover plano',
        message: `Remover o plano "${props.plan.name}"? Todos os casos vão junto (soft delete). A ação é auditada.`,
        cancel: true,
        persistent: true,
    }).onOk(() => {
        router.delete(`/projects/${props.project.id}/test-plans/${props.plan.id}`)
    })
}
</script>

<template>
    <Head :title="`${plan.name} · ${project.name}`" />

    <AuthenticatedLayout>
        <q-page padding>
            <div class="q-pa-md" style="max-width: 1200px; margin: 0 auto">
                <!-- Breadcrumb / header -->
                <div class="row items-center q-mb-lg">
                    <q-btn
                        flat
                        round
                        icon="arrow_back"
                        @click="router.visit(`/projects/${project.id}`)"
                    />
                    <div class="col q-ml-sm">
                        <div class="text-caption text-grey-7">
                            {{ project.name }} · Planos de teste
                        </div>
                        <div class="row items-center q-gutter-sm">
                            <div class="text-h5">{{ plan.name }}</div>
                            <q-badge
                                :color="plan.status_color"
                                :label="plan.status_label"
                            />
                        </div>
                    </div>
                    <q-btn
                        flat
                        no-caps
                        icon="edit"
                        label="Editar"
                        @click="showPlanDialog = true"
                    />
                    <q-btn
                        flat
                        no-caps
                        color="negative"
                        icon="delete"
                        label="Remover"
                        @click="destroyPlan"
                    />
                </div>

                <q-card v-if="plan.description" flat bordered class="q-mb-md">
                    <q-card-section>
                        <div class="text-caption text-grey-7 q-mb-xs">
                            Descrição
                        </div>
                        <div style="white-space: pre-wrap">{{ plan.description }}</div>
                    </q-card-section>
                </q-card>

                <!-- Geração -->
                <q-card flat bordered class="q-mb-md">
                    <q-card-section class="row items-center">
                        <div>
                            <div class="text-subtitle2">
                                Geração a partir dos requisitos aprovados
                            </div>
                            <div class="text-caption text-grey-7">
                                {{ requirements.approved_total }} requisito(s) aprovado(s) ·
                                {{ requirements.pending_generation.length }} pendente(s) de geração
                            </div>
                        </div>
                        <q-space />
                        <q-btn
                            color="primary"
                            no-caps
                            icon="auto_awesome"
                            label="Gerar casos pendentes"
                            :loading="generating"
                            :disable="requirements.pending_generation.length === 0"
                            @click="generateCases"
                        />
                    </q-card-section>
                    <q-separator v-if="requirements.pending_generation.length > 0" />
                    <q-card-section
                        v-if="requirements.pending_generation.length > 0"
                        class="q-py-sm"
                    >
                        <div class="text-caption text-grey-7 q-mb-xs">
                            Pendentes:
                        </div>
                        <q-chip
                            v-for="r in requirements.pending_generation"
                            :key="r.id"
                            dense
                            outline
                            color="primary"
                            text-color="primary"
                        >
                            {{ r.title }}
                        </q-chip>
                    </q-card-section>
                </q-card>

                <!-- Lista de casos -->
                <q-card flat bordered>
                    <q-card-section>
                        <div class="row items-center">
                            <div class="text-subtitle1">
                                Casos de teste ({{ plan.cases.length }})
                            </div>
                        </div>
                    </q-card-section>
                    <q-separator />

                    <q-card-section v-if="plan.cases.length === 0" class="text-grey-7 text-center q-pa-lg">
                        Nenhum caso ainda. Use o botão acima para gerar a partir
                        dos requisitos aprovados.
                    </q-card-section>

                    <template v-else>
                        <div
                            v-for="group in casesByRequirement"
                            :key="group.key"
                        >
                            <div
                                class="q-px-md q-py-sm bg-grey-2 row items-center"
                                style="gap: 8px"
                            >
                                <div class="text-caption text-weight-medium text-grey-8">
                                    {{ group.label }}
                                    <span class="text-grey-6">
                                        · {{ group.cases.length }} caso(s)
                                    </span>
                                </div>
                                <q-space />
                                <template v-if="group.requirement">
                                    <q-badge
                                        v-if="group.requirement.qa_approved"
                                        color="positive"
                                        class="q-py-xs"
                                    >
                                        <q-icon
                                            name="check_circle"
                                            size="14px"
                                            class="q-mr-xs"
                                        />
                                        Pronto para desenvolvimento
                                    </q-badge>
                                    <q-badge
                                        v-if="latestReopenFor(group).status === 'pending'"
                                        color="warning"
                                        class="q-py-xs q-ml-xs"
                                    >
                                        <q-icon
                                            name="hourglass_empty"
                                            size="14px"
                                            class="q-mr-xs"
                                        />
                                        Reabertura aguardando PM
                                    </q-badge>
                                </template>
                            </div>
                            <div
                                v-if="group.requirement?.qa_approved"
                                class="q-px-md q-py-xs bg-green-1 text-caption text-grey-8"
                            >
                                <q-icon name="verified" color="positive" size="14px" class="q-mr-xs" />
                                Aprovado por {{ group.requirement.qa_approver?.name ?? '—' }}
                                em {{ formatDate(group.requirement.qa_approved_at) }}
                            </div>
                            <div
                                v-if="
                                    group.requirement?.qa_approved &&
                                    latestReopenFor(group).status === 'denied'
                                "
                                class="q-px-md q-py-sm bg-red-1 text-caption text-red-10"
                            >
                                <q-icon name="block" color="negative" size="14px" class="q-mr-xs" />
                                Solicitação de reabertura recusada por
                                <strong>
                                    {{ latestReopenFor(group).decided_by?.name ?? '—' }}
                                </strong>
                                em
                                {{ formatDate(latestReopenFor(group).decided_at) }}.
                                <span
                                    v-if="latestReopenFor(group).decision_reason"
                                >
                                    Motivo: {{ latestReopenFor(group).decision_reason }}
                                </span>
                            </div>
                            <q-list separator>
                                <q-item
                                    v-for="c in group.cases"
                                    :key="c.id"
                                    clickable
                                    @click="openCase(c)"
                                >
                                    <q-item-section>
                                        <q-item-label class="text-weight-medium">
                                            {{ c.title }}
                                        </q-item-label>
                                        <q-item-label caption>
                                            {{ c.steps.length }} passo(s)
                                            <span v-if="c.created_by">
                                                · por {{ c.created_by.name }}
                                            </span>
                                        </q-item-label>
                                    </q-item-section>
                                    <q-item-section side>
                                        <q-badge
                                            :color="c.priority_color"
                                            :label="c.priority_label"
                                        />
                                    </q-item-section>
                                    <q-item-section side>
                                        <q-badge
                                            :color="c.status_color"
                                            :label="c.status_label"
                                        />
                                    </q-item-section>
                                </q-item>
                            </q-list>
                        </div>
                    </template>
                </q-card>
            </div>

            <TestPlanDialog
                v-model="showPlanDialog"
                :project-id="project.id"
                :plan="plan"
                :statuses="statuses.map((s) => ({ label: s.label, value: s.value }))"
            />

            <TestCaseEditor
                v-if="editingCase"
                v-model="showCaseEditor"
                :project-id="project.id"
                :plan-id="plan.id"
                :test-case="editingCase"
            />
        </q-page>
    </AuthenticatedLayout>
</template>
