<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import AuthenticatedLayout from '../../layouts/AuthenticatedLayout.vue'
import UploadRequirementDialog from './Partials/UploadRequirementDialog.vue'
import RequirementEditor from './Partials/RequirementEditor.vue'
import TestPlanDialog from './Partials/TestPlanDialog.vue'
import BacklogRequirementViewer from './Partials/BacklogRequirementViewer.vue'
import SprintDialog from './Partials/SprintDialog.vue'

const props = defineProps({
    project: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
    backlog: { type: Array, default: () => [] },
    sprints: { type: Array, default: () => [] },
    pending_reopen_requests: { type: Array, default: () => [] },
})

const pendingReopenRequests = computed(() => props.pending_reopen_requests)

const $q = useQuasar()

function formatDate(iso) {
    if (!iso) return ''
    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(iso))
}

const tab = ref(
    props.can.manageRequirements
        ? 'requirements'
        : props.can.manageTestPlans
          ? 'tests'
          : props.can.viewBacklog
            ? 'backlog'
            : 'team'
)
const showUpload = ref(false)
const editingRequirement = ref(null)
const retryingId = ref(null)
const showPlanDialog = ref(false)

const viewingBacklogItem = ref(null)
const showBacklogViewer = computed({
    get: () => !!viewingBacklogItem.value,
    set: (v) => {
        if (!v) viewingBacklogItem.value = null
    },
})

function openBacklogItem(item) {
    viewingBacklogItem.value = item
}

// Sprints
const showSprintDialog = ref(false)
const editingSprint = ref(null)

const nextSprintNumber = computed(() => {
    const max = props.sprints.reduce((acc, s) => Math.max(acc, s.number), 0)
    return max + 1
})

function openNewSprint() {
    editingSprint.value = null
    showSprintDialog.value = true
}

function openEditSprint(sprint) {
    editingSprint.value = sprint
    showSprintDialog.value = true
}

function destroySprint(sprint) {
    $q.dialog({
        title: `Remover Sprint #${sprint.number}`,
        message: `Os ${sprint.requirements.length} requisito(s) voltam para o backlog. A ação fica auditada.`,
        cancel: true,
        persistent: true,
    }).onOk(() => {
        router.delete(`/projects/${props.project.id}/sprints/${sprint.id}`, {
            preserveScroll: true,
        })
    })
}
const showEditor = computed({
    get: () => !!editingRequirement.value,
    set: (v) => {
        if (!v) editingRequirement.value = null
    },
})

const pendingDocs = computed(() =>
    props.project.documents.filter((d) =>
        ['pending', 'processing'].includes(d.status)
    )
)

function openRequirement(req) {
    editingRequirement.value = req
}

// Mantém o requirement aberto no dialog sincronizado com o state novo
// sempre que a lista do projeto é recarregada (após save/approve/etc.).
watch(
    () => props.project.requirements,
    (reqs) => {
        if (!editingRequirement.value) return
        const fresh = reqs.find((r) => r.id === editingRequirement.value.id)
        if (fresh) editingRequirement.value = fresh
    },
    { deep: true }
)

function refreshPage() {
    router.reload({ only: ['project'], preserveScroll: true })
}

function retryDocument(doc) {
    retryingId.value = doc.id
    router.post(
        `/projects/${props.project.id}/requirement-documents/${doc.id}/retry`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                retryingId.value = null
            },
        }
    )
}

const channelName = `projects.${props.project.id}`
let channel = null

onMounted(() => {
    if (!window.Echo) return

    channel = window.Echo.private(channelName)
        .listen('.requirement-document.updated', (payload) => {
            const doc = payload?.document
            router.reload({ only: ['project'], preserveScroll: true })

            if (doc?.status === 'generated') {
                $q.notify({
                    type: 'positive',
                    message: `Requisitos gerados a partir de "${doc.filename}".`,
                    position: 'top',
                })
            } else if (doc?.status === 'failed') {
                $q.notify({
                    type: 'negative',
                    message: `Falha ao gerar requisitos: ${doc.failure_reason ?? 'erro desconhecido'}.`,
                    position: 'top',
                    timeout: 6000,
                })
            }
        })
        .listen('.reopen-request.created', (payload) => {
            router.reload({
                only: ['project', 'backlog', 'sprints', 'pending_reopen_requests'],
                preserveScroll: true,
            })
            $q.notify({
                type: 'info',
                message: `Nova solicitação de reabertura: "${payload.requirement_title}" por ${payload.requested_by}.`,
                position: 'top',
                timeout: 5000,
            })
        })
        .listen('.reopen-request.decided', (payload) => {
            router.reload({
                only: ['project', 'backlog', 'sprints', 'pending_reopen_requests'],
                preserveScroll: true,
            })
            const approved = payload.status === 'approved'
            $q.notify({
                type: approved ? 'positive' : 'warning',
                message: approved
                    ? `Reabertura aprovada em "${payload.requirement_title}".`
                    : `Reabertura recusada em "${payload.requirement_title}".`,
                position: 'top',
            })
        })
})

onBeforeUnmount(() => {
    if (channel) {
        window.Echo.leave(channelName)
        channel = null
    }
})
</script>

<template>
    <Head :title="project.name" />

    <AuthenticatedLayout>
        <q-page padding>
            <div class="q-pa-md" style="max-width: 1200px; margin: 0 auto">
                <!-- Cabeçalho -->
                <div class="row items-start q-mb-lg">
                    <q-btn
                        flat
                        round
                        icon="arrow_back"
                        class="q-mr-sm q-mt-xs"
                        @click="router.visit('/projects')"
                    />
                    <div class="col">
                        <div class="row items-center q-gutter-sm">
                            <div class="text-h5">{{ project.name }}</div>
                            <q-badge
                                :color="project.status_color"
                                :label="project.status_label"
                            />
                        </div>
                        <div class="text-caption text-grey-7 q-mt-xs">
                            Responsável: {{ project.owner?.name ?? '—' }}
                            <span v-if="project.github_repo_url">
                                · <a :href="project.github_repo_url" target="_blank">Repositório</a>
                            </span>
                        </div>
                    </div>
                    <q-btn
                        v-if="can.update"
                        flat
                        no-caps
                        icon="edit"
                        label="Editar"
                        @click="router.visit(`/projects/${project.id}/edit`)"
                    />
                </div>

                <!-- Metadados -->
                <div class="row q-col-gutter-md q-mb-md">
                    <div class="col-12 col-md-4">
                        <q-card flat bordered>
                            <q-card-section>
                                <div class="text-caption text-grey-7">Início</div>
                                <div>{{ project.start_date ?? '—' }}</div>
                            </q-card-section>
                        </q-card>
                    </div>
                    <div class="col-12 col-md-4">
                        <q-card flat bordered>
                            <q-card-section>
                                <div class="text-caption text-grey-7">Entrega</div>
                                <div>{{ project.delivery_date ?? '—' }}</div>
                            </q-card-section>
                        </q-card>
                    </div>
                    <div class="col-12 col-md-4">
                        <q-card flat bordered>
                            <q-card-section>
                                <div class="text-caption text-grey-7">Forecast</div>
                                <div>
                                    {{
                                        project.forecast_hours
                                            ? `${project.forecast_hours} horas`
                                            : '—'
                                    }}
                                </div>
                            </q-card-section>
                        </q-card>
                    </div>
                </div>

                <q-card v-if="project.description" flat bordered class="q-mb-md">
                    <q-card-section>
                        <div class="text-caption text-grey-7 q-mb-xs">Descrição</div>
                        <div style="white-space: pre-wrap">{{ project.description }}</div>
                    </q-card-section>
                </q-card>

                <!-- Tabs -->
                <q-card flat bordered>
                    <q-tabs
                        v-model="tab"
                        dense
                        align="left"
                        active-color="primary"
                        indicator-color="primary"
                        class="text-grey-8"
                    >
                        <q-tab
                            v-if="can.manageRequirements"
                            name="requirements"
                            label="Requisitos"
                            no-caps
                        />
                        <q-tab
                            v-if="can.manageTestPlans"
                            name="tests"
                            label="Testes"
                            no-caps
                        />
                        <q-tab
                            v-if="can.viewBacklog"
                            name="backlog"
                            no-caps
                        >
                            <div class="row items-center q-gutter-xs">
                                <span>Backlog</span>
                                <q-badge
                                    v-if="backlog.length > 0"
                                    color="positive"
                                    :label="backlog.length"
                                />
                            </div>
                        </q-tab>
                        <q-tab
                            v-if="can.viewApprovals"
                            name="approvals"
                            no-caps
                        >
                            <div class="row items-center q-gutter-xs">
                                <span>Aprovações</span>
                                <q-badge
                                    v-if="pendingReopenRequests.length > 0"
                                    color="warning"
                                    :label="pendingReopenRequests.length"
                                />
                            </div>
                        </q-tab>
                        <q-tab name="team" label="Equipe" no-caps />
                    </q-tabs>

                    <q-separator />

                    <q-tab-panels v-model="tab" animated>
                        <!-- REQUISITOS -->
                        <q-tab-panel name="requirements">
                            <div v-if="!can.manageRequirements" class="text-grey-7 q-py-md">
                                Você não tem acesso ao módulo de requisitos deste projeto.
                            </div>

                            <div v-else>
                                <div class="row items-center q-mb-md">
                                    <div class="text-subtitle1">
                                        Documentos e requisitos gerados
                                    </div>
                                    <q-space />
                                    <q-btn
                                        v-if="pendingDocs.length > 0"
                                        flat
                                        dense
                                        no-caps
                                        icon="refresh"
                                        label="Atualizar"
                                        @click="refreshPage"
                                    />
                                    <q-btn
                                        color="primary"
                                        icon="cloud_upload"
                                        label="Gerar requisitos pela IA"
                                        no-caps
                                        class="q-ml-sm"
                                        @click="showUpload = true"
                                    />
                                </div>

                                <div v-if="project.documents.length > 0" class="q-mb-lg">
                                    <div class="text-caption text-grey-7 q-mb-xs">
                                        Documentos enviados
                                    </div>
                                    <q-list bordered separator class="rounded-borders">
                                        <q-item
                                            v-for="doc in project.documents"
                                            :key="doc.id"
                                        >
                                            <q-item-section avatar>
                                                <q-icon name="description" />
                                            </q-item-section>
                                            <q-item-section>
                                                <q-item-label>{{ doc.filename }}</q-item-label>
                                                <q-item-label caption>
                                                    Enviado por {{ doc.uploaded_by?.name ?? '—' }}
                                                </q-item-label>
                                                <q-item-label
                                                    v-if="doc.failure_reason"
                                                    caption
                                                    class="text-negative"
                                                >
                                                    {{ doc.failure_reason }}
                                                </q-item-label>
                                            </q-item-section>
                                            <q-item-section side>
                                                <q-badge
                                                    :color="doc.status_color"
                                                    :label="doc.status_label"
                                                />
                                            </q-item-section>
                                            <q-item-section side>
                                                <div class="row q-gutter-xs">
                                                    <q-btn
                                                        v-if="doc.status === 'failed'"
                                                        flat
                                                        dense
                                                        round
                                                        icon="refresh"
                                                        color="primary"
                                                        :loading="retryingId === doc.id"
                                                        @click="retryDocument(doc)"
                                                    >
                                                        <q-tooltip>Tentar novamente</q-tooltip>
                                                    </q-btn>
                                                    <q-btn
                                                        flat
                                                        dense
                                                        round
                                                        icon="download"
                                                        :href="`/projects/${project.id}/requirement-documents/${doc.id}/download`"
                                                    >
                                                        <q-tooltip>Baixar</q-tooltip>
                                                    </q-btn>
                                                </div>
                                            </q-item-section>
                                        </q-item>
                                    </q-list>
                                </div>

                                <div class="text-caption text-grey-7 q-mb-xs">
                                    Requisitos gerados
                                </div>
                                <q-card v-if="project.requirements.length === 0" flat bordered>
                                    <q-card-section class="text-grey-7 text-center">
                                        Nenhum requisito ainda. Envie um documento para gerar.
                                    </q-card-section>
                                </q-card>
                                <q-list
                                    v-else
                                    bordered
                                    separator
                                    class="rounded-borders"
                                >
                                    <q-item
                                        v-for="req in project.requirements"
                                        :key="req.id"
                                        clickable
                                        @click="openRequirement(req)"
                                    >
                                        <q-item-section>
                                            <q-item-label class="text-weight-medium">
                                                {{ req.title }}
                                            </q-item-label>
                                            <q-item-label caption lines="2">
                                                {{ req.description }}
                                            </q-item-label>
                                        </q-item-section>
                                        <q-item-section side>
                                            <q-badge
                                                :color="req.status_color"
                                                :label="req.status_label"
                                            />
                                        </q-item-section>
                                    </q-item>
                                </q-list>
                            </div>
                        </q-tab-panel>

                        <!-- TESTES -->
                        <q-tab-panel name="tests">
                            <div v-if="!can.manageTestPlans" class="text-grey-7 q-py-md">
                                Você não tem acesso ao módulo de testes deste projeto.
                            </div>

                            <div v-else>
                                <div class="row items-center q-mb-md">
                                    <div class="text-subtitle1">
                                        Planos de teste
                                    </div>
                                    <q-space />
                                    <q-btn
                                        color="primary"
                                        icon="add"
                                        label="Novo plano"
                                        no-caps
                                        @click="showPlanDialog = true"
                                    />
                                </div>

                                <q-card
                                    v-if="project.test_plans.length === 0"
                                    flat
                                    bordered
                                >
                                    <q-card-section class="text-grey-7 text-center q-pa-lg">
                                        Nenhum plano criado. Comece um novo e gere os casos
                                        a partir dos requisitos aprovados.
                                    </q-card-section>
                                </q-card>

                                <q-list
                                    v-else
                                    bordered
                                    separator
                                    class="rounded-borders"
                                >
                                    <q-item
                                        v-for="plan in project.test_plans"
                                        :key="plan.id"
                                        clickable
                                        @click="
                                            router.visit(
                                                `/projects/${project.id}/test-plans/${plan.id}`
                                            )
                                        "
                                    >
                                        <q-item-section avatar>
                                            <q-icon name="fact_check" />
                                        </q-item-section>
                                        <q-item-section>
                                            <q-item-label class="text-weight-medium">
                                                {{ plan.name }}
                                            </q-item-label>
                                            <q-item-label caption>
                                                {{ plan.cases_count }} caso(s) ·
                                                criado por
                                                {{ plan.created_by?.name ?? '—' }}
                                            </q-item-label>
                                        </q-item-section>
                                        <q-item-section side>
                                            <q-badge
                                                :color="plan.status_color"
                                                :label="plan.status_label"
                                            />
                                        </q-item-section>
                                    </q-item>
                                </q-list>
                            </div>
                        </q-tab-panel>

                        <!-- BACKLOG (PM) -->
                        <q-tab-panel v-if="can.viewBacklog" name="backlog">
                            <!-- Sprints organizadas -->
                            <div class="row items-center q-mb-md">
                                <div class="text-subtitle1">Sprints</div>
                                <q-space />
                                <q-btn
                                    color="primary"
                                    no-caps
                                    icon="add"
                                    label="Nova sprint"
                                    :disable="backlog.length === 0 && sprints.length === 0"
                                    @click="openNewSprint"
                                >
                                    <q-tooltip v-if="backlog.length === 0 && sprints.length === 0">
                                        Precisa de ao menos 1 requisito liberado no backlog.
                                    </q-tooltip>
                                </q-btn>
                            </div>

                            <q-card v-if="sprints.length === 0" flat bordered class="q-mb-lg">
                                <q-card-section class="text-grey-7 text-center q-py-md">
                                    Nenhuma sprint criada. Os requisitos ficam no backlog
                                    abaixo até você organizá-los.
                                </q-card-section>
                            </q-card>

                            <div v-else class="q-mb-lg">
                                <q-card
                                    v-for="sprint in sprints"
                                    :key="sprint.id"
                                    flat
                                    bordered
                                    class="q-mb-sm"
                                >
                                    <q-expansion-item
                                        default-opened
                                        header-class="bg-grey-2"
                                    >
                                        <template #header>
                                            <q-item-section avatar>
                                                <q-avatar
                                                    size="32px"
                                                    color="primary"
                                                    text-color="white"
                                                >
                                                    {{ sprint.number }}
                                                </q-avatar>
                                            </q-item-section>
                                            <q-item-section>
                                                <q-item-label class="text-weight-medium">
                                                    Sprint #{{ sprint.number }}
                                                </q-item-label>
                                                <q-item-label caption>
                                                    {{ formatDate(sprint.start_date) }}
                                                    —
                                                    {{ formatDate(sprint.end_date) }}
                                                    · {{ sprint.requirements.length }} requisito(s)
                                                </q-item-label>
                                            </q-item-section>
                                            <q-item-section side>
                                                <div class="row q-gutter-xs">
                                                    <q-btn
                                                        flat
                                                        dense
                                                        no-caps
                                                        icon="edit"
                                                        size="sm"
                                                        @click.stop="openEditSprint(sprint)"
                                                    >
                                                        <q-tooltip>Editar sprint</q-tooltip>
                                                    </q-btn>
                                                    <q-btn
                                                        flat
                                                        dense
                                                        no-caps
                                                        icon="delete"
                                                        color="negative"
                                                        size="sm"
                                                        @click.stop="destroySprint(sprint)"
                                                    >
                                                        <q-tooltip>Remover sprint</q-tooltip>
                                                    </q-btn>
                                                </div>
                                            </q-item-section>
                                        </template>

                                        <q-list separator>
                                            <q-item
                                                v-for="req in sprint.requirements"
                                                :key="req.id"
                                                clickable
                                                @click="openBacklogItem(req)"
                                            >
                                                <q-item-section avatar>
                                                    <q-icon
                                                        name="task_alt"
                                                        color="primary"
                                                    />
                                                </q-item-section>
                                                <q-item-section>
                                                    <q-item-label>
                                                        {{ req.title }}
                                                    </q-item-label>
                                                </q-item-section>
                                            </q-item>
                                        </q-list>
                                    </q-expansion-item>
                                </q-card>
                            </div>

                            <!-- Backlog disponível -->
                            <div class="row items-center q-mb-md">
                                <div class="text-subtitle1">
                                    Backlog disponível
                                </div>
                                <q-space />
                                <div class="text-caption text-grey-7">
                                    {{ backlog.length }} item(ns)
                                </div>
                            </div>

                            <q-card v-if="backlog.length === 0" flat bordered>
                                <q-card-section class="text-grey-7 text-center q-pa-lg">
                                    {{
                                        sprints.length > 0
                                            ? 'Todos os requisitos liberados já estão em alguma sprint.'
                                            : 'Nenhum requisito pronto para desenvolvimento ainda. Aguarde o OK do QA após os testes.'
                                    }}
                                </q-card-section>
                            </q-card>

                            <q-list v-else bordered separator class="rounded-borders">
                                <q-item
                                    v-for="item in backlog"
                                    :key="item.id"
                                    clickable
                                    @click="openBacklogItem(item)"
                                >
                                    <q-item-section avatar>
                                        <q-icon name="playlist_add_check" color="positive" />
                                    </q-item-section>
                                    <q-item-section>
                                        <q-item-label class="text-weight-medium">
                                            {{ item.title }}
                                        </q-item-label>
                                        <q-item-label caption>
                                            <span class="text-grey-8">
                                                Analista: {{ item.analyst_approver?.name ?? '—' }}
                                                em {{ formatDate(item.analyst_approved_at) }}
                                            </span>
                                            ·
                                            <span class="text-positive">
                                                QA: {{ item.qa_approver?.name ?? '—' }}
                                                em {{ formatDate(item.qa_approved_at) }}
                                            </span>
                                        </q-item-label>
                                    </q-item-section>
                                </q-item>
                            </q-list>
                        </q-tab-panel>

                        <!-- APROVAÇÕES (pedidos de reabertura) -->
                        <q-tab-panel v-if="can.viewApprovals" name="approvals">
                            <div class="text-subtitle1 q-mb-md">
                                Solicitações aguardando sua decisão
                            </div>

                            <q-card
                                v-if="pendingReopenRequests.length === 0"
                                flat
                                bordered
                            >
                                <q-card-section class="text-grey-7 text-center q-pa-lg">
                                    Nenhuma solicitação pendente.
                                </q-card-section>
                            </q-card>

                            <q-list v-else bordered separator class="rounded-borders">
                                <q-item
                                    v-for="pr in pendingReopenRequests"
                                    :key="pr.id"
                                    clickable
                                    @click="
                                        router.visit(
                                            `/projects/${project.id}/reopen-requests/${pr.id}`
                                        )
                                    "
                                >
                                    <q-item-section avatar>
                                        <q-icon name="lock_open" color="warning" />
                                    </q-item-section>
                                    <q-item-section>
                                        <q-item-label class="text-weight-medium">
                                            {{ pr.requirement.title }}
                                        </q-item-label>
                                        <q-item-label caption>
                                            {{ pr.scope_label }} · solicitado por
                                            {{ pr.requested_by?.name ?? '—' }}
                                            em {{ formatDate(pr.requested_at) }}
                                        </q-item-label>
                                        <q-item-label
                                            caption
                                            class="q-mt-xs text-grey-9"
                                            lines="2"
                                        >
                                            {{ pr.reason }}
                                        </q-item-label>
                                    </q-item-section>
                                    <q-item-section side>
                                        <q-btn
                                            dense
                                            no-caps
                                            color="primary"
                                            icon-right="arrow_forward"
                                            label="Revisar"
                                        />
                                    </q-item-section>
                                </q-item>
                            </q-list>
                        </q-tab-panel>

                        <!-- EQUIPE -->
                        <q-tab-panel name="team">
                            <q-list bordered separator class="rounded-borders">
                                <q-item v-for="member in project.members" :key="member.id">
                                    <q-item-section>
                                        <q-item-label>{{ member.name }}</q-item-label>
                                        <q-item-label caption>
                                            {{ member.email }}
                                        </q-item-label>
                                    </q-item-section>
                                    <q-item-section side>
                                        <q-item-label>
                                            {{ member.profile?.name ?? '—' }}
                                        </q-item-label>
                                        <q-item-label
                                            v-if="member.role_override"
                                            caption
                                            class="text-primary"
                                        >
                                            Override: {{ member.role_override }}
                                        </q-item-label>
                                    </q-item-section>
                                </q-item>
                            </q-list>
                        </q-tab-panel>
                    </q-tab-panels>
                </q-card>
            </div>

            <UploadRequirementDialog
                v-model="showUpload"
                :project-id="project.id"
                @uploaded="refreshPage"
            />

            <RequirementEditor
                v-if="editingRequirement"
                v-model="showEditor"
                :project-id="project.id"
                :requirement="editingRequirement"
            />

            <TestPlanDialog
                v-model="showPlanDialog"
                :project-id="project.id"
            />

            <BacklogRequirementViewer
                v-if="viewingBacklogItem"
                v-model="showBacklogViewer"
                :item="viewingBacklogItem"
            />

            <SprintDialog
                v-model="showSprintDialog"
                :project-id="project.id"
                :sprint="editingSprint"
                :backlog="backlog"
                :next-number="nextSprintNumber"
            />
        </q-page>
    </AuthenticatedLayout>
</template>
