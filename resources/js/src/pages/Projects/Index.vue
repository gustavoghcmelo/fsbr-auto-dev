<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '../../layouts/AuthenticatedLayout.vue'

defineProps({
    projects: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
})

function openProject(id) {
    router.visit(`/projects/${id}`)
}
</script>

<template>
    <Head title="Projetos" />

    <AuthenticatedLayout>
        <q-page padding>
            <div class="q-pa-md">
                <div class="row items-center q-mb-lg">
                    <div class="text-h5">Projetos</div>
                    <q-space />
                    <q-btn
                        v-if="can.create"
                        color="primary"
                        label="Novo projeto"
                        icon="add"
                        no-caps
                        @click="router.visit('/projects/create')"
                    />
                </div>

                <q-card v-if="projects.length === 0" flat bordered>
                    <q-card-section class="text-grey-7 text-center q-pa-lg">
                        Nenhum projeto visível.
                    </q-card-section>
                </q-card>

                <div v-else class="row q-col-gutter-md">
                    <div
                        v-for="project in projects"
                        :key="project.id"
                        class="col-12 col-md-6 col-lg-4"
                    >
                        <q-card
                            flat
                            bordered
                            class="cursor-pointer full-height"
                            @click="openProject(project.id)"
                        >
                            <q-card-section>
                                <div class="row items-center no-wrap">
                                    <div class="text-subtitle1 text-weight-medium ellipsis">
                                        {{ project.name }}
                                    </div>
                                    <q-space />
                                    <q-badge
                                        :color="project.status_color"
                                        :label="project.status_label"
                                    />
                                </div>
                                <div class="text-caption text-grey-7 q-mt-xs">
                                    Responsável: {{ project.owner?.name ?? '—' }}
                                </div>
                            </q-card-section>
                            <q-separator />
                            <q-card-section class="q-py-sm text-caption text-grey-8">
                                <div>Início: {{ project.start_date ?? '—' }}</div>
                                <div>Entrega: {{ project.delivery_date ?? '—' }}</div>
                                <div>{{ project.members_count }} membro(s)</div>
                            </q-card-section>
                        </q-card>
                    </div>
                </div>
            </div>
        </q-page>
    </AuthenticatedLayout>
</template>
