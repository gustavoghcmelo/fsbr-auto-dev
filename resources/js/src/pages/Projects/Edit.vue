<script setup>
import { Head, router, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '../../layouts/AuthenticatedLayout.vue'
import ProjectForm from './Partials/ProjectForm.vue'

const props = defineProps({
    project: { type: Object, required: true },
    users: { type: Array, default: () => [] },
    profiles: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
})

const form = useForm({
    name: props.project.name,
    description: props.project.description ?? '',
    github_repo_url: props.project.github_repo_url ?? '',
    start_date: props.project.start_date ?? '',
    delivery_date: props.project.delivery_date ?? '',
    forecast_hours: props.project.forecast_hours,
    status: props.project.status,
    members: (props.project.members ?? []).map((m) => ({
        user_id: m.user_id,
        role_override: m.role_override ?? null,
    })),
})

function submit() {
    form.put(`/projects/${props.project.id}`)
}
</script>

<template>
    <Head :title="`Editar · ${project.name}`" />

    <AuthenticatedLayout>
        <q-page padding>
            <div class="q-pa-md" style="max-width: 900px; margin: 0 auto">
                <div class="row items-center q-mb-lg">
                    <q-btn flat round icon="arrow_back" @click="router.visit(`/projects/${project.id}`)" />
                    <div class="text-h5 q-ml-sm">Editar projeto</div>
                </div>

                <q-card flat bordered>
                    <q-card-section>
                        <ProjectForm
                            :form="form"
                            :users="users"
                            :profiles="profiles"
                            :statuses="statuses"
                        />
                    </q-card-section>
                    <q-separator />
                    <q-card-actions align="right" class="q-pa-md">
                        <q-btn
                            flat
                            no-caps
                            label="Cancelar"
                            @click="router.visit(`/projects/${project.id}`)"
                        />
                        <q-btn
                            color="primary"
                            no-caps
                            label="Salvar"
                            :loading="form.processing"
                            @click="submit"
                        />
                    </q-card-actions>
                </q-card>
            </div>
        </q-page>
    </AuthenticatedLayout>
</template>
