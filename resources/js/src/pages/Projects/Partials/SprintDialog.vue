<script setup>
import { useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    projectId: { type: Number, required: true },
    sprint: { type: Object, default: null },
    /** Requisitos do backlog (não alocados em sprint alguma) */
    backlog: { type: Array, default: () => [] },
    /** Próximo número que será atribuído no create (informativo) */
    nextNumber: { type: Number, default: 1 },
})

const emit = defineEmits(['update:modelValue'])

const open = ref(props.modelValue)
watch(() => props.modelValue, (v) => (open.value = v))
watch(open, (v) => emit('update:modelValue', v))

const isEdit = computed(() => !!props.sprint)

const form = useForm({
    start_date: '',
    end_date: '',
    requirement_ids: [],
})

watch(
    [() => props.sprint, () => props.modelValue],
    ([sprint, opening]) => {
        if (!opening) return
        if (sprint) {
            form.start_date = sprint.start_date ?? ''
            form.end_date = sprint.end_date ?? ''
            form.requirement_ids = (sprint.requirements ?? []).map((r) => r.id)
        } else {
            form.start_date = ''
            form.end_date = ''
            form.requirement_ids = []
        }
        form.clearErrors()
    },
    { immediate: true }
)

/** Pool de requisitos selecionáveis:
 *  - No edit: backlog atual + os já alocados nesta sprint
 *  - No create: só o backlog
 */
const availableRequirements = computed(() => {
    if (!isEdit.value) return props.backlog
    const inThisSprint = props.sprint?.requirements ?? []
    const byId = new Map()
    for (const r of [...inThisSprint, ...props.backlog]) {
        if (!byId.has(r.id)) byId.set(r.id, r)
    }
    return Array.from(byId.values())
})

function toggle(id) {
    const idx = form.requirement_ids.indexOf(id)
    if (idx >= 0) form.requirement_ids.splice(idx, 1)
    else form.requirement_ids.push(id)
}

function submit() {
    if (isEdit.value) {
        form.put(`/projects/${props.projectId}/sprints/${props.sprint.id}`, {
            preserveScroll: true,
            onSuccess: () => (open.value = false),
        })
    } else {
        form.post(`/projects/${props.projectId}/sprints`, {
            preserveScroll: true,
            onSuccess: () => (open.value = false),
        })
    }
}
</script>

<template>
    <q-dialog v-model="open" persistent>
        <q-card style="min-width: 640px; max-width: 800px">
            <q-card-section>
                <div class="text-h6">
                    {{ isEdit ? `Editar Sprint #${sprint.number}` : `Nova Sprint #${nextNumber}` }}
                </div>
                <div class="text-caption text-grey-7">
                    Organize os requisitos liberados em ciclos de desenvolvimento.
                </div>
            </q-card-section>

            <q-separator />

            <q-card-section>
                <div class="row q-col-gutter-md">
                    <div class="col-6">
                        <q-input
                            v-model="form.start_date"
                            label="Início"
                            type="date"
                            outlined
                            :error="!!form.errors.start_date"
                            :error-message="form.errors.start_date"
                        />
                    </div>
                    <div class="col-6">
                        <q-input
                            v-model="form.end_date"
                            label="Fim"
                            type="date"
                            outlined
                            :error="!!form.errors.end_date"
                            :error-message="form.errors.end_date"
                        />
                    </div>
                </div>
            </q-card-section>

            <q-separator />

            <q-card-section>
                <div class="text-subtitle2 q-mb-sm">
                    Requisitos da sprint
                    <span class="text-caption text-grey-7">
                        ({{ form.requirement_ids.length }} selecionado(s))
                    </span>
                </div>

                <q-card
                    v-if="availableRequirements.length === 0"
                    flat
                    bordered
                >
                    <q-card-section class="text-grey-7 text-center q-py-md">
                        Nenhum requisito disponível no backlog.
                    </q-card-section>
                </q-card>

                <q-list
                    v-else
                    bordered
                    separator
                    class="rounded-borders"
                    style="max-height: 320px; overflow: auto"
                >
                    <q-item
                        v-for="req in availableRequirements"
                        :key="req.id"
                        clickable
                        @click="toggle(req.id)"
                    >
                        <q-item-section avatar>
                            <q-checkbox
                                :model-value="form.requirement_ids.includes(req.id)"
                                @update:model-value="toggle(req.id)"
                                @click.stop
                            />
                        </q-item-section>
                        <q-item-section>
                            <q-item-label class="text-weight-medium">
                                {{ req.title }}
                            </q-item-label>
                            <q-item-label caption class="ellipsis">
                                {{ req.description }}
                            </q-item-label>
                        </q-item-section>
                    </q-item>
                </q-list>
            </q-card-section>

            <q-separator />

            <q-card-actions align="right" class="q-pa-md">
                <q-btn flat no-caps label="Cancelar" @click="open = false" />
                <q-btn
                    color="primary"
                    no-caps
                    :label="isEdit ? 'Salvar' : 'Criar sprint'"
                    :loading="form.processing"
                    @click="submit"
                />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>
