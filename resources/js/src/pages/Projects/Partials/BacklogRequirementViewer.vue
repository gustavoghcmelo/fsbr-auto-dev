<script setup>
import { computed, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import GherkinViewer from './GherkinViewer.vue'

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    item: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue'])
const $q = useQuasar()

const open = ref(props.modelValue)
watch(() => props.modelValue, (v) => (open.value = v))
watch(open, (v) => emit('update:modelValue', v))

function formatDate(iso) {
    if (!iso) return ''
    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(iso))
}

async function copyGherkin() {
    try {
        await navigator.clipboard.writeText(props.item?.gherkin ?? '')
        $q.notify({
            type: 'positive',
            message: 'Gherkin copiado.',
            position: 'top',
            timeout: 1500,
        })
    } catch {
        $q.notify({
            type: 'negative',
            message: 'Falha ao copiar.',
            position: 'top',
        })
    }
}

const criteria = computed(() => props.item?.acceptance_criteria ?? [])
</script>

<template>
    <q-dialog
        v-model="open"
        maximized
        persistent
        transition-show="slide-up"
        transition-hide="slide-down"
    >
        <q-card v-if="item" class="column bg-grey-10 text-white">
            <!-- Toolbar -->
            <q-toolbar class="bg-grey-10 q-py-sm">
                <q-btn flat dense round icon="close" @click="open = false" />
                <div class="q-ml-sm">
                    <div class="text-subtitle1 text-weight-medium ellipsis">
                        {{ item.title }}
                    </div>
                    <div class="text-caption text-grey-5">
                        Requisito #{{ item.id }} · Pronto para desenvolvimento
                    </div>
                </div>
                <q-space />
                <q-btn
                    flat
                    dense
                    round
                    icon="content_copy"
                    @click="copyGherkin"
                >
                    <q-tooltip>Copiar Gherkin</q-tooltip>
                </q-btn>
            </q-toolbar>

            <!-- Banner de aprovações -->
            <q-banner class="bg-green-10 text-white q-px-md q-py-sm" dense>
                <template #avatar>
                    <q-icon name="verified" />
                </template>
                Aprovado pelo analista
                <strong>{{ item.analyst_approver?.name ?? '—' }}</strong>
                em {{ formatDate(item.analyst_approved_at) }}
                · Aprovado pelo QA
                <strong>{{ item.qa_approver?.name ?? '—' }}</strong>
                em {{ formatDate(item.qa_approved_at) }}
            </q-banner>

            <!-- Corpo -->
            <q-card-section class="col scroll q-pa-md">
                <div class="row q-col-gutter-md">
                    <div class="col-12 col-md-8">
                        <GherkinViewer :text="item.gherkin" />
                    </div>
                    <div class="col-12 col-md-4">
                        <q-card v-if="item.description" flat bordered class="bg-grey-9 text-white">
                            <q-card-section>
                                <div class="text-caption text-grey-5 q-mb-xs">
                                    Descrição
                                </div>
                                <div style="white-space: pre-wrap">{{ item.description }}</div>
                            </q-card-section>
                        </q-card>

                        <q-card
                            v-if="item.context"
                            flat
                            bordered
                            class="bg-grey-9 text-white q-mt-md"
                        >
                            <q-card-section>
                                <div class="text-caption text-grey-5 q-mb-xs">
                                    Contexto
                                </div>
                                <div style="white-space: pre-wrap">{{ item.context }}</div>
                            </q-card-section>
                        </q-card>

                        <q-card
                            v-if="criteria.length > 0"
                            flat
                            bordered
                            class="bg-grey-9 text-white q-mt-md"
                        >
                            <q-card-section>
                                <div class="text-caption text-grey-5 q-mb-xs">
                                    Critérios de aceite
                                </div>
                                <ul class="q-pl-md q-mb-none">
                                    <li v-for="(c, idx) in criteria" :key="idx">
                                        {{ c }}
                                    </li>
                                </ul>
                            </q-card-section>
                        </q-card>
                    </div>
                </div>
            </q-card-section>
        </q-card>
    </q-dialog>
</template>
