<script setup>
import { useForm } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    projectId: { type: Number, required: true },
})

const emit = defineEmits(['update:modelValue', 'uploaded'])

const open = ref(props.modelValue)
watch(() => props.modelValue, (v) => (open.value = v))
watch(open, (v) => emit('update:modelValue', v))

const form = useForm({ file: null })

function submit() {
    form.post(`/projects/${props.projectId}/requirement-documents`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('file')
            open.value = false
            emit('uploaded')
        },
    })
}
</script>

<template>
    <q-dialog v-model="open">
        <q-card style="min-width: 420px">
            <q-card-section>
                <div class="text-h6">Enviar documento de requisitos</div>
                <div class="text-caption text-grey-7 q-mt-xs">
                    Formatos aceitos: PDF, DOCX, TXT, MD (até 20 MB).
                </div>
            </q-card-section>

            <q-card-section>
                <q-file
                    v-model="form.file"
                    label="Selecione o arquivo"
                    outlined
                    accept=".pdf,.docx,.txt,.md"
                    :error="!!form.errors.file"
                    :error-message="form.errors.file"
                >
                    <template #prepend>
                        <q-icon name="attach_file" />
                    </template>
                </q-file>
            </q-card-section>

            <q-card-actions align="right" class="q-pa-md">
                <q-btn
                    flat
                    no-caps
                    label="Cancelar"
                    @click="open = false"
                />
                <q-btn
                    color="primary"
                    no-caps
                    label="Enviar e gerar"
                    :loading="form.processing"
                    :disable="!form.file"
                    @click="submit"
                />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>
