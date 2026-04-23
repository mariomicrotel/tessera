<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import HeroSection from '@/Components/PublicSite/HeroSection.vue';
import ChiSiamoSection from '@/Components/PublicSite/ChiSiamoSection.vue';
import EventiSection from '@/Components/PublicSite/EventiSection.vue';
import RendicontiSection from '@/Components/PublicSite/RendicontiSection.vue';
import StatutoSection from '@/Components/PublicSite/StatutoSection.vue';
import ModuloIscrizioneSection from '@/Components/PublicSite/ModuloIscrizioneSection.vue';

const sectionOrder = ['hero', 'chi_siamo', 'eventi', 'rendiconti', 'statuto', 'modulo_iscrizione'];

function buildSectionStyle(sectionStyle) {
    if (!sectionStyle || typeof sectionStyle !== 'object') return {};
    const bgColor = sectionStyle.bg_color ?? sectionStyle.bgColor ?? '';
    const textColor = sectionStyle.text_color ?? sectionStyle.textColor ?? '';
    const bgImageUrl = sectionStyle.background_image_url ?? sectionStyle.backgroundImageUrl ?? '';
    const s = {};
    if (bgColor) s.backgroundColor = bgColor;
    if (textColor) s.color = textColor;
    if (bgImageUrl) {
        s.backgroundImage = `url(${bgImageUrl})`;
        s.backgroundSize = 'cover';
        s.backgroundPosition = 'center';
    }
    return s;
}

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    sections: {
        type: Array,
        default: () => [],
    },
    site: {
        type: Object,
        default: () => ({}),
    },
    events: {
        type: Array,
        default: () => [],
    },
    rendiconti_years: {
        type: Array,
        default: () => [],
    },
    rendiconti_urls: {
        type: Object,
        default: () => ({}),
    },
    statuto_url: {
        type: String,
        default: null,
    },
});

const footerSectionStyle = computed(() => buildSectionStyle(props.site?.section_styles?.footer));

const visibleSections = sectionOrder.filter((id) => props.sections.includes(id));

const sectionAnchors = {
    chi_siamo: '#chi-siamo',
    eventi: '#eventi',
    rendiconti: '#rendiconti',
    statuto: '#statuto',
    modulo_iscrizione: '#modulo-iscrizione',
};
const navLinks = visibleSections
    .filter((id) => sectionAnchors[id])
    .map((id) => ({ id, href: sectionAnchors[id], label: sectionLabel(id) }));

function sectionLabel(id) {
    const labels = {
        chi_siamo: 'Chi siamo',
        eventi: 'Eventi',
        rendiconti: 'Rendiconti',
        statuto: 'Statuto',
        modulo_iscrizione: 'Iscriviti',
    };
    return labels[id] || id;
}
</script>

<template>
    <Head :title="site.nome_associazione || 'Home'" />
    <div class="min-h-screen bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100">
        <!-- Header -->
        <header class="sticky top-0 z-10 border-b border-gray-200 dark:border-gray-800 bg-white/95 dark:bg-gray-900/95 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
                <Link href="/" class="flex shrink-0 items-center gap-2">
                    <img
                        v-if="site.logo_url"
                        :src="site.logo_url"
                        alt="Logo"
                        class="h-9 w-auto max-w-[160px] object-contain object-left"
                    />
                    <ApplicationMark v-else class="block h-9 w-auto" />
                    <span v-if="site.nome_associazione" class="hidden font-semibold text-gray-900 dark:text-white sm:block">
                        {{ site.nome_associazione }}
                    </span>
                </Link>
                <nav class="flex items-center gap-4">
                    <a
                        v-for="link in navLinks"
                        :key="link.id"
                        :href="link.href"
                        class="hidden text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white sm:block"
                    >
                        {{ link.label }}
                    </a>
                    <template v-if="canLogin">
                        <Link
                            v-if="$page.props.auth?.user"
                            :href="route('dashboard')"
                            class="rounded-md px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800"
                        >
                            Dashboard
                        </Link>
                        <template v-else>
                            <Link
                                :href="route('login')"
                                class="rounded-md px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white"
                            >
                                Accedi
                            </Link>
                            <Link
                                v-if="canRegister"
                                :href="route('register')"
                                class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500"
                            >
                                Registrati
                            </Link>
                        </template>
                    </template>
                </nav>
            </div>
        </header>

        <main>
            <HeroSection v-if="visibleSections.includes('hero')" :site="site" :section-style="site.section_styles?.hero" />
            <ChiSiamoSection v-if="visibleSections.includes('chi_siamo')" :site="site" :section-style="site.section_styles?.chi_siamo" />
            <EventiSection v-if="visibleSections.includes('eventi')" :events="events" :section-style="site.section_styles?.eventi" />
            <RendicontiSection
                v-if="visibleSections.includes('rendiconti')"
                :rendiconti-years="rendiconti_years"
                :rendiconti-urls="rendiconti_urls"
                :section-style="site.section_styles?.rendiconti"
            />
            <StatutoSection v-if="visibleSections.includes('statuto')" :statuto-url="statuto_url" :section-style="site.section_styles?.statuto" />
            <ModuloIscrizioneSection
                v-if="visibleSections.includes('modulo_iscrizione')"
                :can-register="canRegister"
                :section-style="site.section_styles?.modulo_iscrizione"
            />
        </main>

        <footer
            class="border-t border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 py-8"
            :style="footerSectionStyle"
        >
            <div class="mx-auto max-w-6xl px-4 sm:px-6 text-center text-sm text-gray-500 dark:text-gray-400">
                <div v-if="site.footer_text" class="whitespace-pre-line">{{ site.footer_text }}</div>
                <template v-else>
                    <p v-if="site.nome_associazione">{{ site.nome_associazione }}</p>
                    <p v-if="site.indirizzo_associazione" class="mt-1">{{ site.indirizzo_associazione }}</p>
                </template>
            </div>
        </footer>
    </div>
</template>
