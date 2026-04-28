<script setup>
import { useForm } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    projectId: { type: Number, required: true },
    plan: { type: Object, default: null },
    statuses: {
        type: Array,
        default: () => [
            { label: 'Rascunho', value: 'draft' },
            { label: 'Ativo', value: 'active' },
            { label: 'Arquivado', value: 'archived' },
        ],
    },
})

const emit = defineEmits(['update:modelValue'])

const open = ref(props.modelValue)
watch(() => props.modelValue, (v) => (open.value = v))
watch(open, (v) => emit('update:modelValue', v))

const form = useForm({
    name: '',
    description: '',
    status: 'draft',
})

watch(
    () => props.plan,
    (plan) => {
        form.name = plan?.name ?? ''
        form.description = plan?.description ?? ''
        form.status = plan?.status ?? 'draft'
        form.clearErrors()
    },
    { immediate: true }
)

function submit() {
    if (props.plan) {
        form.put(`/projects/${props.projectId}/test-plans/${props.plan.id}`, {
            preserveScroll: true,
            onSuccess: () => (open.value = false),
        })
    } else {
        form.post(`/projects/${props.projectId}/test-plans`, {
            preserveScroll: true,
            onSuccess: () => (open.value = false),
        })
    }
}
</script>

<template>
    <q-dialog v-model="open">
        <q-card style="min-width: 480px">
            <q-card-section>
                <div class="text-h6">
                    {{ plan ? 'Editar plano' : 'Novo plano de testes' }}
                </div>
            </q-card-section>

            <q-card-section class="q-gutter-md">
                <q-input
                    v-model="form.name"
                    label="Nome"
                    outlined
                    autofocus
                    :error="!!form.errors.name"
                    :error-message="form.errors.name"
                />
                <q-input
                    v-model="form.description"
                    label="Descrição"
                    type="textarea"
                    outlined
                    autogrow
                />
                <q-select
                    v-model="form.status"
                    :options="statuses"
                    label="Status"
                    outlined
                    emit-value
                    map-options
                />
            </q-card-section>

            <q-card-actions align="right" class="q-pa-md">
                <q-btn flat no-caps label="Cancelar" @click="open = false" />
                <q-btn
                    color="primary"
                    no-caps
                    :label="plan ? 'Salvar' : 'Criar'"
                    :loading="form.processing"
                    @click="submit"
                />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>
