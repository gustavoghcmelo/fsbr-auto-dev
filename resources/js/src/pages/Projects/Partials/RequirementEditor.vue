<script setup>
import { useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import GherkinViewer from './GherkinViewer.vue'
import ReopenRequestDialog from './ReopenRequestDialog.vue'

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    projectId: { type: Number, required: true },
    requirement: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue'])
const $q = useQuasar()

const open = ref(props.modelValue)
watch(() => props.modelValue, (v) => (open.value = v))
watch(open, (v) => emit('update:modelValue', v))

const editing = ref(false)
const showReopenDialog = ref(false)

const latestReopen = computed(
    () => props.requirement?.latest_reopen?.requirement_approval ?? null
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

const form = useForm({
    title: '',
    description: '',
    context: '',
    acceptance_criteria: [],
    gherkin: '',
    status: 'draft',
})

const isApproved = computed(() => form.status === 'approved')

const approvedInfo = computed(() => {
    if (!isApproved.value || !props.requirement?.approved_at) return null
    const date = new Date(props.requirement.approved_at)
    const formatted = new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(date)
    const who = props.requirement.approved_by?.name ?? 'usuário desconhecido'
    return `Aprovado por ${who} em ${formatted}`
})

watch(
    () => props.requirement,
    (req) => {
        if (!req) return
        form.title = req.title
        form.description = req.description ?? ''
        form.context = req.context ?? ''
        form.acceptance_criteria = [...(req.acceptance_criteria ?? [])]
        form.gherkin = req.gherkin ?? ''
        form.status = req.status
        form.clearErrors()
        editing.value = false
    },
    { immediate: true }
)

// Se o requisito for aprovado enquanto o dialog está em modo edit, tranca.
watch(isApproved, (approved) => {
    if (approved) editing.value = false
})

const statusOptions = [
    { label: 'Rascunho', value: 'draft', color: 'grey' },
    { label: 'Aprovado', value: 'approved', color: 'positive' },
    { label: 'Rejeitado', value: 'rejected', color: 'negative' },
]

const currentStatus = computed(
    () => statusOptions.find((s) => s.value === form.status) ?? statusOptions[0]
)

function submit() {
    if (!props.requirement) return
    form.put(`/projects/${props.projectId}/requirements/${props.requirement.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = false
        },
    })
}

function cancelEdit() {
    form.gherkin = props.requirement?.gherkin ?? ''
    editing.value = false
}

function setStatus(value) {
    if (!props.requirement) return

    // Aprovar é ação final — conteúdo trava e não há botão de reabrir.
    if (value === 'approved' && form.status !== 'approved') {
        $q.dialog({
            title: 'Aprovar requisito',
            message:
                'Após aprovar, o conteúdo deste requisito ficará bloqueado ' +
                'e não poderá mais ser editado. Deseja continuar?',
            cancel: { label: 'Cancelar', flat: true, noCaps: true },
            ok: {
                label: 'Aprovar',
                color: 'positive',
                noCaps: true,
                unelevated: true,
            },
            persistent: true,
        }).onOk(() => {
            form.status = value
            submit()
        })
        return
    }

    form.status = value
    submit()
}

function destroy() {
    if (!props.requirement) return
    $q.dialog({
        title: 'Remover requisito',
        message: `Tem certeza que deseja remover "${props.requirement.title}"?`,
        cancel: true,
        persistent: true,
    }).onOk(() => {
        form.delete(`/projects/${props.projectId}/requirements/${props.requirement.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false
            },
        })
    })
}

async function copyGherkin() {
    try {
        await navigator.clipboard.writeText(form.gherkin)
        $q.notify({
            type: 'positive',
            message: 'Gherkin copiado.',
            position: 'top',
            timeout: 1500,
        })
    } catch {
        $q.notify({
            type: 'negative',
            message: 'Falha ao copiar.',
            position: 'top',
        })
    }
}
</script>

<template>
    <q-dialog v-model="open" maximized persistent transition-show="slide-up" transition-hide="slide-down">
        <q-card class="gherkin-dialog column">
            <!-- Toolbar -->
            <q-toolbar class="bg-grey-10 text-white q-py-sm">
                <q-btn flat dense round icon="close" @click="open = false" />
                <div class="q-ml-sm">
                    <div class="text-subtitle1 text-weight-medium ellipsis">
                        {{ form.title || 'Requisito' }}
                    </div>
                    <div class="text-caption text-grey-5">
                        Edição Gherkin · Requisito #{{ requirement?.id }}
                    </div>
                </div>

                <q-space />

                <q-chip
                    v-if="isApproved"
                    color="positive"
                    text-color="white"
                    icon="verified"
                    class="q-ma-none"
                >
                    Aprovado
                </q-chip>

                <q-chip
                    v-if="isApproved && hasPendingReopen"
                    color="warning"
                    text-color="white"
                    icon="hourglass_empty"
                    class="q-ma-none"
                >
                    Reabertura aguardando QA
                </q-chip>
                <q-btn
                    v-else-if="isApproved"
                    no-caps
                    flat
                    :icon="hasDeniedReopen ? 'replay' : 'lock_open'"
                    :label="hasDeniedReopen ? 'Solicitar nova reabertura' : 'Solicitar reabertura'"
                    @click="showReopenDialog = true"
                />
                <q-btn-dropdown
                    v-else
                    flat
                    no-caps
                    :color="currentStatus.color === 'grey' ? 'white' : currentStatus.color"
                    :label="currentStatus.label"
                    :disable="form.processing"
                >
                    <q-list>
                        <q-item
                            v-for="s in statusOptions"
                            :key="s.value"
                            clickable
                            v-close-popup
                            @click="setStatus(s.value)"
                        >
                            <q-item-section avatar>
                                <q-icon
                                    :name="form.status === s.value ? 'check' : ''"
                                    :color="s.color"
                                />
                            </q-item-section>
                            <q-item-section>{{ s.label }}</q-item-section>
                        </q-item>
                    </q-list>
                </q-btn-dropdown>

                <q-btn
                    flat
                    dense
                    round
                    icon="content_copy"
                    @click="copyGherkin"
                >
                    <q-tooltip>Copiar Gherkin</q-tooltip>
                </q-btn>

                <template v-if="!isApproved">
                    <q-btn
                        v-if="!editing"
                        no-caps
                        flat
                        icon="edit"
                        label="Editar"
                        @click="editing = true"
                    />
                    <template v-else>
                        <q-btn
                            no-caps
                            flat
                            label="Cancelar"
                            :disable="form.processing"
                            @click="cancelEdit"
                        />
                        <q-btn
                            no-caps
                            color="primary"
                            icon="save"
                            label="Salvar"
                            :loading="form.processing"
                            @click="submit"
                        />
                    </template>
                </template>

                <q-btn
                    v-if="!isApproved"
                    flat
                    dense
                    round
                    icon="delete"
                    class="q-ml-sm"
                    @click="destroy"
                >
                    <q-tooltip>Remover requisito</q-tooltip>
                </q-btn>
            </q-toolbar>

            <!-- Banner de recusa da última reabertura -->
            <q-banner
                v-if="hasDeniedReopen"
                class="bg-red-10 text-white q-px-md q-py-sm"
                dense
            >
                <template #avatar>
                    <q-icon name="block" />
                </template>
                Solicitação de reabertura recusada por
                <strong>{{ latestReopen.decided_by?.name ?? '—' }}</strong>
                em {{ formatReopenDate(latestReopen.decided_at) }}.
                <template v-if="latestReopen.decision_reason">
                    Motivo: {{ latestReopen.decision_reason }}
                </template>
            </q-banner>

            <!-- Banner de aprovação -->
            <q-banner
                v-if="approvedInfo"
                class="bg-green-10 text-white q-px-md q-py-sm"
                dense
            >
                <template #avatar>
                    <q-icon name="verified" />
                </template>
                {{ approvedInfo }} · conteúdo bloqueado para edição.
            </q-banner>

            <ReopenRequestDialog
                v-if="requirement"
                v-model="showReopenDialog"
                :project-id="projectId"
                :requirement-id="requirement.id"
                scope="requirement_approval"
            />

            <!-- Corpo -->
            <q-card-section class="col bg-grey-10 q-pa-md">
                <div class="gherkin-frame">
                    <GherkinViewer
                        v-if="!editing"
                        :text="form.gherkin"
                        class="full-height"
                    />
                    <textarea
                        v-else
                        v-model="form.gherkin"
                        class="gherkin-textarea"
                        spellcheck="false"
                    ></textarea>
                </div>
                <div
                    v-if="form.errors.gherkin"
                    class="text-negative text-caption q-mt-sm"
                >
                    {{ form.errors.gherkin }}
                </div>
            </q-card-section>
        </q-card>
    </q-dialog>
</template>

<style scoped>
.gherkin-dialog {
    background: #111827;
    color: #e2e8f0;
}
.gherkin-frame {
    height: 100%;
    min-height: 60vh;
    display: flex;
}
.gherkin-frame > :deep(.gherkin-pre),
.gherkin-textarea {
    flex: 1;
    width: 100%;
    min-height: 60vh;
    border: none;
    outline: none;
    resize: none;
}
.gherkin-textarea {
    background: #0f172a;
    color: #e2e8f0;
    padding: 20px 24px;
    font-family: 'Cascadia Code', 'Fira Code', 'JetBrains Mono', 'Consolas',
        monospace;
    font-size: 14px;
    line-height: 1.7;
    border-radius: 8px;
    tab-size: 2;
}
</style>
