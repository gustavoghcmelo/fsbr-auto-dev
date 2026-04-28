<script setup>
import { Link, router, usePage } from '@inertiajs/vue3'
import { computed, watch } from 'vue'
import { useQuasar } from 'quasar'

const page = usePage()
const $q = useQuasar()

const user = computed(() => page.props.auth?.user)
const flashStatus = computed(() => page.props.flash?.status)

watch(flashStatus, (msg) => {
    if (msg) {
        $q.notify({ type: 'positive', message: msg, position: 'top' })
    }
})

function logout() {
    router.post('/logout')
}
</script>

<template>
    <q-layout view="hHh lpR fFf">
        <q-header elevated class="bg-primary text-white">
            <q-toolbar>
                <q-toolbar-title class="row items-center q-gutter-md">
                    <Link href="/dashboard" class="text-white" style="text-decoration: none">
                        FSBR Auto
                    </Link>
                    <q-btn
                        flat
                        no-caps
                        label="Projetos"
                        @click="router.visit('/projects')"
                    />
                </q-toolbar-title>

                <div v-if="user" class="row items-center q-gutter-sm">
                    <div class="column items-end">
                        <div class="text-body2">{{ user.name }}</div>
                        <div v-if="user.profile" class="text-caption">
                            {{ user.profile.name }}
                        </div>
                    </div>
                    <q-btn
                        flat
                        dense
                        round
                        icon="logout"
                        aria-label="Sair"
                        @click="logout"
                    >
                        <q-tooltip>Sair</q-tooltip>
                    </q-btn>
                </div>
            </q-toolbar>
        </q-header>

        <q-page-container>
            <slot />
        </q-page-container>
    </q-layout>
</template>
