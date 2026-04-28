<script setup>
import { router, useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import ReopenRequestDialog from '../../Partials/ReopenRequestDialog.vue'

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    projectId: { type: Number, required: true },
    planId: { type: Number, required: true },
    testCase: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue'])
const $q = useQuasar()

const open = ref(props.modelValue)
watch(() => props.modelValue, (v) => (open.value = v))
watch(open, (v) => emit('update:modelValue', v))

const form = useForm({
    title: '',
    preconditions: '',
    steps: [],
    expected_result: '',
    gherkin: '',
    priority: 'medium',
    status: 'active',
})

watch(
    () => props.testCase,
    (tc) => {
        if (!tc) return
        form.title = tc.title
        form.preconditions = tc.preconditions ?? ''
        form.steps = Array.isArray(tc.steps)
            ? tc.steps.map((s) => ({ ...s }))
            : []
        form.expected_result = tc.expected_result ?? ''
        form.gherkin = tc.gherkin ?? ''
        form.priority = tc.priority
        form.status = tc.status
        form.clearErrors()
    },
    { immediate: true }
)

const priorityOptions = [
    { label: 'Baixa', value: 'low' },
    { label: 'Média', value: 'medium' },
    { label: 'Alta', value: 'high' },
    { label: 'Crítica', value: 'critical' },
]

const statusOptions = [
    { label: 'Ativo', value: 'active' },
    { label: 'Descontinuado', value: 'deprecated' },
]

const typeOptions = [
    { label: 'Pré-condição', value: 'precondition' },
    { label: 'Ação', value: 'action' },
    { label: 'Verificação', value: 'assertion' },
]

const typeColor = (type) =>
    ({ precondition: 'orange', action: 'blue', assertion: 'green' })[type] ?? 'grey'

// ========== Ações sobre o requisito de origem ==========
const requirement = computed(() => props.testCase?.requirement ?? null)

const qaApproving = ref(false)
const showReopenDialog = ref(false)

const latestReopen = computed(
    () => requirement.value?.latest_reopen?.qa_approval ?? null
)
const hasPendingReopen = computed(
    () => latestReopen.value?.status === 'pending'
)
const hasDeniedReopen = computed(
    () => latestReopen.value?.status === 'denied'
)

function formatReopenDate(iso) {
    if (!iso) return ''
    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(iso))
}

function qaApprove() {
    if (!requirement.value) return
    $q.dialog({
        title: 'Liberar para desenvolvimento',
        message:
            'Após liberar, esta aprovação do QA fica registrada definitivamente ' +
            'e não poderá ser revogada pela tela. Deseja continuar?',
        cancel: { label: 'Cancelar', flat: true, noCaps: true },
        ok: {
            label: 'Liberar',
            color: 'positive',
            noCaps: true,
            unelevated: true,
        },
        persistent: true,
    }).onOk(() => {
        qaApproving.value = true
        router.post(
            `/projects/${props.projectId}/requirements/${requirement.value.id}/qa-approve`,
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    qaApproving.value = false
                },
            }
        )
    })
}

function addStep(type = 'action') {
    const keyword =
        type === 'precondition' ? 'Dado' : type === 'action' ? 'Quando' : 'Então'
    form.steps.push({ keyword, text: '', type })
}

function removeStep(idx) {
    form.steps.splice(idx, 1)
}

function moveStep(idx, delta) {
    const target = idx + delta
    if (target < 0 || target >= form.steps.length) return
    const tmp = form.steps[idx]
    form.steps[idx] = form.steps[target]
    form.steps[target] = tmp
}

function submit() {
    if (!props.testCase) return
    form.put(
        `/projects/${props.projectId}/test-plans/${props.planId}/test-cases/${props.testCase.id}`,
        {
            preserveScroll: true,
            onSuccess: () => (open.value = false),
        }
    )
}

function destroy() {
    if (!props.testCase) return
    $q.dialog({
        title: 'Remover caso de teste',
        message: `Remover "${props.testCase.title}"? A ação fica auditada.`,
        cancel: true,
        persistent: true,
    }).onOk(() => {
        form.delete(
            `/projects/${props.projectId}/test-plans/${props.planId}/test-cases/${props.testCase.id}`,
            {
                preserveScroll: true,
                onSuccess: () => (open.value = false),
            }
        )
    })
}

const sortedSteps = computed(() => form.steps)
</script>

<template>
    <q-dialog v-model="open" maximized persistent transition-show="slide-up" transition-hide="slide-down">
        <q-card class="column bg-grey-1">
            <!-- Toolbar -->
            <q-toolbar class="bg-grey-10 text-white q-py-sm">
                <q-btn flat dense round icon="close" @click="open = false" />
                <div class="q-ml-sm">
                    <div class="text-subtitle1 text-weight-medium ellipsis">
                        {{ form.title || 'Caso de teste' }}
                    </div>
                    <div class="text-caption text-grey-5">
                        Caso #{{ testCase?.id }}
                    </div>
                </div>
                <q-space />
                <q-btn
                    no-caps
                    color="primary"
                    icon="save"
                    label="Salvar"
                    :loading="form.processing"
                    @click="submit"
                />
                <q-btn
                    flat
                    dense
                    round
                    icon="delete"
                    class="q-ml-sm"
                    @click="destroy"
                >
                    <q-tooltip>Remover</q-tooltip>
                </q-btn>
            </q-toolbar>

            <!-- Ações sobre o requisito de origem -->
            <q-card-section
                v-if="requirement"
                class="bg-grey-3 q-py-sm"
            >
                <div class="row items-center q-gutter-sm">
                    <div class="col">
                        <div class="text-caption text-grey-7">
                            Requisito de origem
                        </div>
                        <div class="text-weight-medium">
                            {{ requirement.title }}
                        </div>
                    </div>

                    <template v-if="requirement.qa_approved">
                        <q-badge color="positive" class="q-py-xs">
                            <q-icon
                                name="check_circle"
                                size="14px"
                                class="q-mr-xs"
                            />
                            Pronto para desenvolvimento
                        </q-badge>
                        <q-badge
                            v-if="hasPendingReopen"
                            color="warning"
                            class="q-py-xs"
                        >
                            <q-icon
                                name="hourglass_empty"
                                size="14px"
                                class="q-mr-xs"
                            />
                            Reabertura aguardando PM
                        </q-badge>
                        <q-btn
                            v-else
                            dense
                            no-caps
                            :icon="hasDeniedReopen ? 'replay' : 'lock_open'"
                            :label="
                                hasDeniedReopen
                                    ? 'Solicitar nova reabertura'
                                    : 'Solicitar reabertura'
                            "
                            @click="showReopenDialog = true"
                        />
                    </template>
                    <q-btn
                        v-else
                        dense
                        no-caps
                        color="positive"
                        icon="verified"
                        label="Sinalizar pronto para desenvolvimento"
                        :loading="qaApproving"
                        :disable="!requirement.analyst_approved"
                        @click="qaApprove"
                    >
                        <q-tooltip v-if="!requirement.analyst_approved">
                            Requisito precisa estar aprovado pelo analista.
                        </q-tooltip>
                    </q-btn>
                </div>

                <q-banner
                    v-if="hasDeniedReopen"
                    class="bg-red-1 text-red-10 q-mt-sm"
                    dense
                >
                    <template #avatar>
                        <q-icon name="block" color="negative" />
                    </template>
                    Solicitação de reabertura recusada por
                    <strong>{{ latestReopen.decided_by?.name ?? '—' }}</strong>
                    em {{ formatReopenDate(latestReopen.decided_at) }}.
                    <template v-if="latestReopen.decision_reason">
                        Motivo: {{ latestReopen.decision_reason }}
                    </template>
                </q-banner>
            </q-card-section>

            <ReopenRequestDialog
                v-if="requirement"
                v-model="showReopenDialog"
                :project-id="projectId"
                :requirement-id="requirement.id"
                scope="qa_approval"
            />

            <!-- Corpo -->
            <q-card-section class="col scroll q-pa-md">
                <div class="row q-col-gutter-md">
                    <div class="col-12 col-md-8">
                        <q-card flat bordered>
                            <q-card-section class="q-gutter-md">
                                <q-input
                                    v-model="form.title"
                                    label="Título"
                                    outlined
                                    :error="!!form.errors.title"
                                    :error-message="form.errors.title"
                                />
                                <q-input
                                    v-model="form.preconditions"
                                    label="Pré-condições"
                                    type="textarea"
                                    outlined
                                    autogrow
                                />
                                <q-input
                                    v-model="form.expected_result"
                                    label="Resultado esperado"
                                    type="textarea"
                                    outlined
                                    autogrow
                                />
                            </q-card-section>
                        </q-card>

                        <q-card flat bordered class="q-mt-md">
                            <q-card-section>
                                <div class="row items-center q-mb-sm">
                                    <div class="text-subtitle2">Passos</div>
                                    <q-space />
                                    <q-btn-dropdown
                                        flat
                                        no-caps
                                        icon="add"
                                        label="Adicionar passo"
                                        color="primary"
                                    >
                                        <q-list>
                                            <q-item
                                                v-for="o in typeOptions"
                                                :key="o.value"
                                                clickable
                                                v-close-popup
                                                @click="addStep(o.value)"
                                            >
                                                <q-item-section>{{ o.label }}</q-item-section>
                                            </q-item>
                                        </q-list>
                                    </q-btn-dropdown>
                                </div>

                                <q-list bordered separator class="rounded-borders">
                                    <q-item
                                        v-for="(step, idx) in sortedSteps"
                                        :key="idx"
                                    >
                                        <q-item-section side top>
                                            <q-badge
                                                :color="typeColor(step.type)"
                                                :label="step.keyword"
                                            />
                                        </q-item-section>
                                        <q-item-section>
                                            <div class="row q-col-gutter-sm">
                                                <div class="col-3">
                                                    <q-select
                                                        v-model="step.keyword"
                                                        :options="[
                                                            'Dado',
                                                            'Quando',
                                                            'Então',
                                                            'E',
                                                            'Mas',
                                                        ]"
                                                        dense
                                                        outlined
                                                    />
                                                </div>
                                                <div class="col">
                                                    <q-input
                                                        v-model="step.text"
                                                        dense
                                                        outlined
                                                        placeholder="Descreva o passo"
                                                    />
                                                </div>
                                                <div class="col-3">
                                                    <q-select
                                                        v-model="step.type"
                                                        :options="typeOptions"
                                                        dense
                                                        outlined
                                                        emit-value
                                                        map-options
                                                    />
                                                </div>
                                            </div>
                                        </q-item-section>
                                        <q-item-section side>
                                            <div class="column q-gutter-xs">
                                                <q-btn
                                                    flat
                                                    dense
                                                    round
                                                    size="sm"
                                                    icon="arrow_upward"
                                                    :disable="idx === 0"
                                                    @click="moveStep(idx, -1)"
                                                />
                                                <q-btn
                                                    flat
                                                    dense
                                                    round
                                                    size="sm"
                                                    icon="arrow_downward"
                                                    :disable="idx === form.steps.length - 1"
                                                    @click="moveStep(idx, 1)"
                                                />
                                                <q-btn
                                                    flat
                                                    dense
                                                    round
                                                    size="sm"
                                                    color="negative"
                                                    icon="close"
                                                    @click="removeStep(idx)"
                                                />
                                            </div>
                                        </q-item-section>
                                    </q-item>
                                    <q-item
                                        v-if="form.steps.length === 0"
                                    >
                                        <q-item-section class="text-grey-7 text-center">
                                            Nenhum passo cadastrado.
                                        </q-item-section>
                                    </q-item>
                                </q-list>
                            </q-card-section>
                        </q-card>
                    </div>

                    <div class="col-12 col-md-4">
                        <q-card flat bordered>
                            <q-card-section class="q-gutter-md">
                                <q-select
                                    v-model="form.priority"
                                    :options="priorityOptions"
                                    label="Prioridade"
                                    outlined
                                    emit-value
                                    map-options
                                />
                                <q-select
                                    v-model="form.status"
                                    :options="statusOptions"
                                    label="Status"
                                    outlined
                                    emit-value
                                    map-options
                                />
                                <div v-if="testCase?.requirement" class="text-caption text-grey-7">
                                    Origem:
                                    <span class="text-primary">
                                        {{ testCase.requirement.title }}
                                    </span>
                                </div>
                            </q-card-section>
                        </q-card>

                        <q-card v-if="form.gherkin" flat bordered class="q-mt-md">
                            <q-card-section>
                                <div class="text-caption text-grey-7 q-mb-xs">
                                    Gherkin original
                                </div>
                                <pre class="gherkin-ref">{{ form.gherkin }}</pre>
                            </q-card-section>
                        </q-card>
                    </div>
                </div>
            </q-card-section>
        </q-card>
    </q-dialog>
</template>

<style scoped>
.gherkin-ref {
    background: #0f172a;
    color: #e2e8f0;
    padding: 12px;
    font-family: 'Cascadia Code', 'Fira Code', monospace;
    font-size: 12px;
    line-height: 1.6;
    border-radius: 6px;
    overflow: auto;
    margin: 0;
    max-height: 260px;
    white-space: pre-wrap;
}
</style>
