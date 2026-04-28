<script setup>
import { computed } from 'vue'

const props = defineProps({
    form: { type: Object, required: true },
    users: { type: Array, default: () => [] },
    profiles: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
})

const statusOptions = computed(() =>
    props.statuses.map((s) => ({ label: s.label, value: s.value }))
)

const profileOptions = computed(() => [
    { label: 'Usar perfil global', value: null },
    ...props.profiles.map((p) => ({ label: p.name, value: p.slug })),
])

const userOptions = computed(() =>
    props.users.map((u) => ({
        label: `${u.name} (${u.profile?.name ?? '—'})`,
        value: u.id,
    }))
)

const selectedUserIds = computed({
    get: () => props.form.members.map((m) => m.user_id),
    set: (ids) => {
        const existing = new Map(props.form.members.map((m) => [m.user_id, m]))
        props.form.members = ids.map((id) =>
            existing.get(id) ?? { user_id: id, role_override: null }
        )
    },
})

function userName(id) {
    const u = props.users.find((u) => u.id === id)
    return u ? u.name : `Usuário #${id}`
}

function userProfile(id) {
    return props.users.find((u) => u.id === id)?.profile?.name ?? '—'
}
</script>

<template>
    <q-form class="q-gutter-md" @submit.prevent>
        <q-input
            v-model="form.name"
            label="Nome do projeto"
            outlined
            :error="!!form.errors.name"
            :error-message="form.errors.name"
        />

        <q-input
            v-model="form.description"
            label="Descrição"
            type="textarea"
            outlined
            autogrow
            :error="!!form.errors.description"
            :error-message="form.errors.description"
        />

        <q-input
            v-model="form.github_repo_url"
            label="Repositório GitHub"
            outlined
            placeholder="https://github.com/org/repo"
            :error="!!form.errors.github_repo_url"
            :error-message="form.errors.github_repo_url"
        />

        <div class="row q-col-gutter-md">
            <div class="col-12 col-md-4">
                <q-input
                    v-model="form.start_date"
                    label="Data de início"
                    outlined
                    type="date"
                    :error="!!form.errors.start_date"
                    :error-message="form.errors.start_date"
                />
            </div>
            <div class="col-12 col-md-4">
                <q-input
                    v-model="form.delivery_date"
                    label="Data de entrega"
                    outlined
                    type="date"
                    :error="!!form.errors.delivery_date"
                    :error-message="form.errors.delivery_date"
                />
            </div>
            <div class="col-12 col-md-4">
                <q-input
                    v-model.number="form.forecast_hours"
                    label="Forecast (horas)"
                    outlined
                    type="number"
                    step="0.5"
                    min="0"
                    :error="!!form.errors.forecast_hours"
                    :error-message="form.errors.forecast_hours"
                />
            </div>
        </div>

        <q-select
            v-model="form.status"
            :options="statusOptions"
            label="Status"
            outlined
            emit-value
            map-options
            :error="!!form.errors.status"
            :error-message="form.errors.status"
        />

        <q-separator class="q-my-md" />

        <div class="text-subtitle1">Equipe do projeto</div>

        <q-select
            v-model="selectedUserIds"
            :options="userOptions"
            label="Membros"
            outlined
            multiple
            use-chips
            emit-value
            map-options
        />

        <q-list
            v-if="form.members.length > 0"
            bordered
            separator
            class="rounded-borders"
        >
            <q-item v-for="member in form.members" :key="member.user_id">
                <q-item-section>
                    <q-item-label>{{ userName(member.user_id) }}</q-item-label>
                    <q-item-label caption>
                        Perfil global: {{ userProfile(member.user_id) }}
                    </q-item-label>
                </q-item-section>
                <q-item-section side style="min-width: 240px">
                    <q-select
                        v-model="member.role_override"
                        :options="profileOptions"
                        label="Papel no projeto"
                        dense
                        outlined
                        emit-value
                        map-options
                    />
                </q-item-section>
            </q-item>
        </q-list>
    </q-form>
</template>
