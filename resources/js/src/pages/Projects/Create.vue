<script setup>
import { Head, router, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '../../layouts/AuthenticatedLayout.vue'
import ProjectForm from './Partials/ProjectForm.vue'

const props = defineProps({
    users: { type: Array, default: () => [] },
    profiles: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
})

const form = useForm({
    name: '',
    description: '',
    github_repo_url: '',
    start_date: '',
    delivery_date: '',
    forecast_hours: null,
    status: 'planning',
    members: [],
})

function submit() {
    form.post('/projects')
}
</script>

<template>
    <Head title="Novo projeto" />

    <AuthenticatedLayout>
        <q-page padding>
            <div class="q-pa-md" style="max-width: 900px; margin: 0 auto">
                <div class="row items-center q-mb-lg">
                    <q-btn flat round icon="arrow_back" @click="router.visit('/projects')" />
                    <div class="text-h5 q-ml-sm">Novo projeto</div>
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
                            @click="router.visit('/projects')"
                        />
                        <q-btn
                            color="primary"
                            no-caps
                            label="Criar projeto"
                            :loading="form.processing"
                            @click="submit"
                        />
                    </q-card-actions>
                </q-card>
            </div>
        </q-page>
    </AuthenticatedLayout>
</template>
