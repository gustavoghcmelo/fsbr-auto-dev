import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { Quasar, Notify, Loading, Dialog, ClosePopup } from 'quasar'
import quasarLang from 'quasar/lang/pt-BR'
import '@quasar/extras/material-icons/material-icons.css'
import 'quasar/src/css/index.sass'
import './echo'

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob('./src/pages/**/*.vue', { eager: true })
        return pages[`./src/pages/${name}.vue`]
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(Quasar, {
                plugins: { Notify, Loading, Dialog },
                directives: { ClosePopup },
                lang: quasarLang,
            })
            .mount(el)
    },
})
