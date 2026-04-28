<script setup>
import { Head, router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AuthenticatedLayout from '../../../layouts/AuthenticatedLayout.vue'
import GherkinViewer from '../Partials/GherkinViewer.vue'

const props = defineProps({
    project: { type: Object, required: true },
    request: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
})

function formatDate(iso) {
    if (!iso) return ''
    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(iso))
}

const isPending = computed(() => props.request.status === 'pending')

const form = useForm({
    decision: '',
    decision_reason: '',
})

const showDenyReason = ref(false)

function approve() {
    form.decision = 'approved'
    form.decision_reason = ''
    form.post(
        `/projects/${props.project.id}/reopen-requests/${props.request.id}/decide`,
        {
            preserveScroll: true,
        }
    )
}

function confirmDeny() {
    form.decision = 'denied'
    form.post(
        `/projects/${props.project.id}/reopen-requests/${props.request.id}/decide`,
        {
            preserveScroll: true,
            onSuccess: () => {
                showDenyReason.value = false
            },
        }
    )
}

function cancelDeny() {
    showDenyReason.value = false
    form.decision_reason = ''
}
</script>

<template>
    <Head :title="`Reabertura · ${request.requirement.title}`" />

    <AuthenticatedLayout>
        <q-page padding>
            <div class="q-pa-md" style="max-width: 1100px; margin: 0 auto">
                <!-- Header -->
                <div class="row items-center q-mb-lg">
                    <q-btn
                        flat
                        round
                        icon="arrow_back"
                        @click="router.visit(`/projects/${project.id}`)"
                    />
                    <div class="col q-ml-sm">
                        <div class="text-caption text-grey-7">
                            {{ project.name }} · Solicitação de reabertura
                        </div>
                        <div class="text-h5">
                            {{ request.requirement.title }}
                        </div>
                    </div>
                    <q-badge
                        :color="request.status_color"
                        :label="request.status_label"
                    />
                </div>

                <!-- Card da solicitação -->
                <q-card flat bordered class="q-mb-md">
                    <q-card-section>
                        <div class="row items-center q-mb-sm">
                            <q-icon name="lock_open" color="primary" />
                            <div class="text-subtitle2 q-ml-sm">
                                {{ request.scope_label }}
                            </div>
                        </div>
                        <div class="text-caption text-grey-7 q-mb-xs">
                            Solicitado por
                            <strong>{{ request.requested_by?.name ?? '—' }}</strong>
                            em {{ formatDate(request.created_at) }}
                        </div>
                        <q-separator class="q-my-md" />
                        <div class="text-caption text-grey-7 q-mb-xs">
                            Motivo informado:
                        </div>
                        <div style="white-space: pre-wrap">{{ request.reason }}</div>
                    </q-card-section>
                </q-card>

                <!-- Decisão (se já decidida) -->
                <q-card
                    v-if="!isPending"
                    flat
                    bordered
                    :class="request.status === 'approved' ? 'bg-green-1' : 'bg-red-1'"
                    class="q-mb-md"
                >
                    <q-card-section>
                        <div class="text-subtitle2 q-mb-xs">
                            <q-icon
                                :name="request.status === 'approved' ? 'verified' : 'cancel'"
                                :color="request.status === 'approved' ? 'positive' : 'negative'"
                            />
                            {{ request.status === 'approved' ? 'Aprovada' : 'Recusada' }}
                            por {{ request.decided_by?.name ?? '—' }}
                            em {{ formatDate(request.decided_at) }}
                        </div>
                        <div
                            v-if="request.decision_reason"
                            style="white-space: pre-wrap"
                            class="q-mt-sm"
                        >
                            {{ request.decision_reason }}
                        </div>
                    </q-card-section>
                </q-card>

                <!-- Conteúdo do requisito -->
                <div class="row q-col-gutter-md q-mb-md">
                    <div class="col-12 col-md-8">
                        <q-card flat bordered>
                            <q-card-section>
                                <div class="text-subtitle2 text-grey-7 q-mb-sm">
                                    Feature
                                </div>
                                <GherkinViewer :text="request.requirement.gherkin" />
                            </q-card-section>
                        </q-card>
                    </div>
                    <div class="col-12 col-md-4">
                        <q-card v-if="request.requirement.description" flat bordered>
                            <q-card-section>
                                <div class="text-caption text-grey-7 q-mb-xs">
                                    Descrição
                                </div>
                                <div style="white-space: pre-wrap">
                                    {{ request.requirement.description }}
                                </div>
                            </q-card-section>
                        </q-card>
                        <q-card
                            v-if="request.requirement.acceptance_criteria?.length"
                            flat
                            bordered
                            class="q-mt-md"
                        >
                            <q-card-section>
                                <div class="text-caption text-grey-7 q-mb-xs">
                                    Critérios de aceite
                                </div>
                                <ul class="q-pl-md q-mb-none">
                                    <li
                                        v-for="(c, idx) in request.requirement.acceptance_criteria"
                                        :key="idx"
                                    >
                                        {{ c }}
                                    </li>
                                </ul>
                            </q-card-section>
                        </q-card>
                    </div>
                </div>

                <!-- Ações -->
                <q-card v-if="isPending && can.decide" flat bordered class="q-mt-md">
                    <q-card-section>
                        <div class="text-subtitle2 q-mb-sm">Sua decisão</div>

                        <div v-if="!showDenyReason" class="row q-gutter-sm">
                            <q-btn
                                color="positive"
                                no-caps
                                icon="check"
                                label="Aprovar"
                                :loading="form.processing && form.decision === 'approved'"
                                @click="approve"
                            />
                            <q-btn
                                color="negative"
                                no-caps
                                outline
                                icon="block"
                                label="Recusar"
                                :disable="form.processing"
                                @click="showDenyReason = true"
                            />
                        </div>

                        <div v-else>
                            <q-input
                                v-model="form.decision_reason"
                                label="Motivo da recusa (obrigatório)"
                                type="textarea"
                                outlined
                                autogrow
                                autofocus
                                :error="!!form.errors.decision_reason"
                                :error-message="form.errors.decision_reason"
                            />
                            <div class="row justify-end q-gutter-sm q-mt-sm">
                                <q-btn
                                    flat
                                    no-caps
                                    label="Voltar"
                                    :disable="form.processing"
                                    @click="cancelDeny"
                                />
                                <q-btn
                                    color="negative"
                                    no-caps
                                    label="Confirmar recusa"
                                    :loading="form.processing"
                                    :disable="!form.decision_reason.trim()"
                                    @click="confirmDeny"
                                />
                            </div>
                        </div>
                    </q-card-section>
                </q-card>

                <q-banner
                    v-else-if="isPending && !can.decide"
                    class="bg-grey-3 text-grey-8 q-mt-md"
                >
                    Apenas o {{ request.scope === 'requirement_approval' ? 'QA' : 'PM' }}
                    do projeto pode decidir esta solicitação.
                </q-banner>
            </div>
        </q-page>
    </AuthenticatedLayout>
</template>
