<script setup>
import { useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    projectId: { type: Number, required: true },
    requirementId: { type: Number, required: true },
    scope: {
        type: String,
        required: true,
        validator: (v) => ['requirement_approval', 'qa_approval'].includes(v),
    },
})

const emit = defineEmits(['update:modelValue'])

const open = ref(props.modelValue)
watch(() => props.modelValue, (v) => (open.value = v))
watch(open, (v) => emit('update:modelValue', v))

const form = useForm({
    scope: props.scope,
    reason: '',
})

watch(open, (v) => {
    if (v) {
        form.scope = props.scope
        form.reason = ''
        form.clearErrors()
    }
})

const title = computed(() =>
    props.scope === 'requirement_approval'
        ? 'Solicitar reabertura ao QA'
        : 'Solicitar reabertura ao PM'
)

const helper = computed(() =>
    props.scope === 'requirement_approval'
        ? 'O QA do projeto receberá um e-mail e decidirá se libera o requisito para você editar.'
        : 'O PM do projeto receberá um e-mail e decidirá se libera sua aprovação para revisão.'
)

function submit() {
    form.post(
        `/projects/${props.projectId}/requirements/${props.requirementId}/reopen-requests`,
        {
            preserveScroll: true,
            onSuccess: () => (open.value = false),
        }
    )
}
</script>

<template>
    <q-dialog v-model="open" persistent>
        <q-card style="min-width: 480px; max-width: 640px">
            <q-card-section>
                <div class="text-h6">{{ title }}</div>
                <div class="text-caption text-grey-7 q-mt-xs">
                    {{ helper }}
                </div>
            </q-card-section>

            <q-card-section>
                <q-input
                    v-model="form.reason"
                    label="Motivo da solicitação"
                    type="textarea"
                    outlined
                    autofocus
                    autogrow
                    :error="!!form.errors.reason"
                    :error-message="form.errors.reason"
                />
            </q-card-section>

            <q-card-actions align="right" class="q-pa-md">
                <q-btn flat no-caps label="Cancelar" @click="open = false" />
                <q-btn
                    color="primary"
                    no-caps
                    label="Enviar solicitação"
                    :loading="form.processing"
                    :disable="!form.reason.trim()"
                    @click="submit"
                />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>
