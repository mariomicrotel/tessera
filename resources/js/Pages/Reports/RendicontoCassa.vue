<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    rendiconto: Object,
    anno: Number,
    anno_precedente_ha_movimenti: { type: Boolean, default: true },
});

const page = usePage();
const showExportModal = ref(false);
const editSezioni = ref([]);
const dataGenerazione = ref('');

const anni = Array.from({ length: 30 }, (_, i) => new Date().getFullYear() - 15 + i);

const changeAnno = (event) => {
    const y = event.target.value;
    if (y) router.get(route('reports.rendiconto-cassa'), { anno: y }, { preserveState: true });
};

const openExportModal = () => {
    editSezioni.value = JSON.parse(JSON.stringify(props.rendiconto?.sezioni ?? []));
    const now = new Date();
    dataGenerazione.value = now.toISOString().slice(0, 16);
    showExportModal.value = true;
};

const closeExportModal = () => {
    showExportModal.value = false;
};

const scaricaPdfDiretto = () => {
    window.location.href = route('reports.rendiconto-cassa.export-pdf', { anno: props.anno });
};

/** Totali ricalcolati dallo stato modificato (sola lettura in UI). */
const totaliSezioni = computed(() => {
    return editSezioni.value.map((sezione) => {
        let entrate = 0;
        let uscite = 0;
        for (const v of sezione.voci ?? []) {
            const imp = Number(v.importo) || 0;
            if (v.tipo === 'entrata') entrate += imp;
            else uscite += imp;
        }
        return {
            totale_entrate: Math.round(entrate * 100) / 100,
            totale_uscite: Math.round(uscite * 100) / 100,
        };
    });
});

const totaleEntrate = computed(() => {
    return totaliSezioni.value.reduce((sum, s) => sum + s.totale_entrate, 0);
});

const totaleUscite = computed(() => {
    return totaliSezioni.value.reduce((sum, s) => sum + s.totale_uscite, 0);
});

const risultatoPerCassa = computed(() => {
    return Math.round((totaleEntrate.value - totaleUscite.value) * 100) / 100;
});

const formatEuro = (n) => '€ ' + Number(n).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fmtNum = (n) => (n != null && n !== '') ? Number(n).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';

const AREE_LETTERE = ['A', 'B', 'C', 'D', 'E'];

/** Formato voce: solo parte numerica "n) descrizione" (es. "A.1" -> "1) Materie prime", "INV.9" -> "9) Imposte"). */
function voceLabel(code, descrizione) {
    const c = code ?? '';
    const desc = descrizione ?? '';
    let num = c;
    const pos = c.lastIndexOf('.');
    if (pos !== -1) num = c.slice(pos + 1);
    return num ? `${num}) ${desc}` : desc;
}

/** Costruisce blocchi per tabella 6 colonne (come Blade): raggruppa per area, righe con celle vuote. */
function buildBlocchiTabella(sezioni, includeVoceRefs = false) {
    if (!sezioni?.length) return [];
    const byArea = {};
    const miste = [];
    for (const s of sezioni) {
        const area = s.area ?? null;
        const tipo = s.tipo_sezione || '';
        if (AREE_LETTERE.includes(area)) {
            if (!byArea[area]) byArea[area] = { uscite: null, entrate: null };
            if (tipo === 'uscita') byArea[area].uscite = s;
            else if (tipo === 'entrata') byArea[area].entrate = s;
        } else if (tipo === 'misto') miste.push(s);
    }
    const out = [];
    for (const area of AREE_LETTERE) {
        const u = byArea[area]?.uscite;
        const e = byArea[area]?.entrate;
        if (!u && !e) continue;
        const vociU = u?.voci ?? [];
        const vociE = e?.voci ?? [];
        const totU = vociU.reduce((s, v) => s + (Number(v.importo) || 0), 0);
        const totU1 = vociU.reduce((s, v) => s + (Number(v.importo_anno_precedente) || 0), 0);
        const totE = vociE.reduce((s, v) => s + (Number(v.importo) || 0), 0);
        const totE1 = vociE.reduce((s, v) => s + (Number(v.importo_anno_precedente) || 0), 0);
        const n = Math.max(vociU.length, vociE.length, 1);
        const righe = [];
        for (let i = 0; i < n; i++) {
            const vu = vociU[i] ?? null;
            const ve = vociE[i] ?? null;
            const descU = vu ? voceLabel(vu.ministerial_code ?? vu.codice_voce, vu.descrizione ?? '') : '';
            const descE = ve ? voceLabel(ve.ministerial_code ?? ve.codice_voce, ve.descrizione ?? '') : '';
            righe.push({
                desc_u: descU,
                t_u: vu ? Number(vu.importo) || 0 : null,
                t1_u: vu != null ? (Number(vu.importo_anno_precedente) || 0) : null,
                desc_e: descE,
                t_e: ve ? Number(ve.importo) || 0 : null,
                t1_e: ve != null ? (Number(ve.importo_anno_precedente) || 0) : null,
                ...(includeVoceRefs && { voceU: vu, voceE: ve }),
            });
        }
        out.push({
            area,
            titolo_uscite: u?.sezione ?? '',
            titolo_entrate: e?.sezione ?? '',
            righe,
            tot_u: Math.round(totU * 100) / 100,
            tot_u1: Math.round(totU1 * 100) / 100,
            tot_e: Math.round(totE * 100) / 100,
            tot_e1: Math.round(totE1 * 100) / 100,
            avanzo_t: Math.round((totE - totU) * 100) / 100,
            avanzo_t1: Math.round((totE1 - totU1) * 100) / 100,
            mostra_avanzo: ['A', 'B', 'C', 'D'].includes(area),
        });
    }
    for (const m of miste) {
        const voci = m.voci ?? [];
        const vociU = voci.filter((v) => v.tipo === 'uscita');
        const vociE = voci.filter((v) => v.tipo === 'entrata');
        if (vociU.length === 0 && vociE.length === 0) continue;
        const totU = vociU.reduce((s, v) => s + (Number(v.importo) || 0), 0);
        const totU1 = vociU.reduce((s, v) => s + (Number(v.importo_anno_precedente) || 0), 0);
        const totE = vociE.reduce((s, v) => s + (Number(v.importo) || 0), 0);
        const totE1 = vociE.reduce((s, v) => s + (Number(v.importo_anno_precedente) || 0), 0);
        const n = Math.max(vociU.length, vociE.length, 1);
        const righe = [];
        for (let i = 0; i < n; i++) {
            const vu = vociU[i] ?? null;
            const ve = vociE[i] ?? null;
            const descU = vu ? voceLabel(vu.ministerial_code ?? vu.codice_voce, vu.descrizione ?? '') : '';
            const descE = ve ? voceLabel(ve.ministerial_code ?? ve.codice_voce, ve.descrizione ?? '') : '';
            righe.push({
                desc_u: descU,
                t_u: vu ? Number(vu.importo) || 0 : null,
                t1_u: vu != null ? (Number(vu.importo_anno_precedente) || 0) : null,
                desc_e: descE,
                t_e: ve ? Number(ve.importo) || 0 : null,
                t1_e: ve != null ? (Number(ve.importo_anno_precedente) || 0) : null,
                ...(includeVoceRefs && { voceU: vu, voceE: ve }),
            });
        }
        const nome = m.sezione ?? '';
        let titoloU = nome;
        let titoloE = nome;
        if (nome.includes('Entrate/Uscite')) {
            titoloU = nome.replace('Entrate/Uscite', 'Uscite');
            titoloE = nome.replace('Entrate/Uscite', 'Entrate');
        }
        out.push({
            area: 'INV',
            titolo_uscite: titoloU,
            titolo_entrate: titoloE,
            righe,
            tot_u: Math.round(totU * 100) / 100,
            tot_u1: Math.round(totU1 * 100) / 100,
            tot_e: Math.round(totE * 100) / 100,
            tot_e1: Math.round(totE1 * 100) / 100,
            avanzo_t: null,
            avanzo_t1: null,
            mostra_avanzo: false,
        });
    }
    return out;
}

const blocchiTabella = computed(() => buildBlocchiTabella(props.rendiconto?.sezioni ?? [], false));
const editBlocchiTabella = computed(() => buildBlocchiTabella(editSezioni.value, true));

const blocchiAE = computed(() => (blocchiTabella.value ?? []).filter((b) => AREE_LETTERE.includes(b.area)));
const blocchiInvestimenti = computed(() => (blocchiTabella.value ?? []).filter((b) => b.area === 'INV'));

const totaleEntrateAE = computed(() => blocchiAE.value.reduce((s, b) => s + (Number(b.tot_e) || 0), 0));
const totaleUsciteAE = computed(() => blocchiAE.value.reduce((s, b) => s + (Number(b.tot_u) || 0), 0));
const risultatoAE = computed(() => Math.round((totaleEntrateAE.value - totaleUsciteAE.value) * 100) / 100);
const totaleEntrateAE1 = computed(() => blocchiAE.value.reduce((s, b) => s + (Number(b.tot_e1) || 0), 0));
const totaleUsciteAE1 = computed(() => blocchiAE.value.reduce((s, b) => s + (Number(b.tot_u1) || 0), 0));
const risultatoAE1 = computed(() => Math.round((totaleEntrateAE1.value - totaleUsciteAE1.value) * 100) / 100);

const totaleEntrateINV = computed(() => blocchiInvestimenti.value.reduce((s, b) => s + (Number(b.tot_e) || 0), 0));
const totaleUsciteINV = computed(() => blocchiInvestimenti.value.reduce((s, b) => s + (Number(b.tot_u) || 0), 0));
const avanzoINV_t = computed(() => Math.round((totaleEntrateINV.value - totaleUsciteINV.value) * 100) / 100);
const avanzoINV_t1 = computed(() => {
    const e1 = blocchiInvestimenti.value.reduce((s, b) => s + (Number(b.tot_e1) || 0), 0);
    const u1 = blocchiInvestimenti.value.reduce((s, b) => s + (Number(b.tot_u1) || 0), 0);
    return Math.round((e1 - u1) * 100) / 100;
});
const imposteInv = computed(() => {
    const sezioni = props.rendiconto?.sezioni ?? [];
    let esT = 0;
    let esT1 = 0;
    for (const s of sezioni) {
        if (s.tipo_sezione !== 'misto') continue;
        for (const v of s.voci ?? []) {
            if (v.codice_voce === 'EXP_TAXES') {
                esT += Number(v.importo) || 0;
                esT1 += Number(v.importo_anno_precedente) || 0;
            }
        }
    }
    return { es_t: Math.round(esT * 100) / 100, es_t1: Math.round(esT1 * 100) / 100 };
});

const righeDopoE = computed(() => [
    { etichetta: "Avanzo/disavanzo d'esercizio prima delle imposte", es_t: risultatoAE.value, es_t1: risultatoAE1.value },
    { etichetta: 'Imposte', es_t: null, es_t1: null },
    { etichetta: "Avanzo/disavanzo d'esercizio prima di investimenti e disinvestimenti patrimoniali, e finanziamenti", es_t: risultatoAE.value, es_t1: risultatoAE1.value },
]);
const righeDopoInv = computed(() => [
    { etichetta: 'Imposte', es_t: imposteInv.value.es_t, es_t1: imposteInv.value.es_t1 },
    { etichetta: "Avanzo/disavanzo da entrate e uscite per investimenti e disinvestimenti patrimoniali e finanziamenti", es_t: avanzoINV_t.value, es_t1: avanzoINV_t1.value },
]);
const righeSintesi = computed(() => {
    const ris = Number(props.rendiconto?.risultato_per_cassa ?? 0);
    const ris1 = Math.round((risultatoAE1.value + avanzoINV_t1.value) * 100) / 100;
    return [
        { etichetta: "Avanzo/disavanzo d'esercizio prima di investimenti e disinvestimenti patrimoniali e finanziamenti", es_t: risultatoAE.value, es_t1: risultatoAE1.value },
        { etichetta: "Avanzo/disavanzo da entrate e uscite per investimenti e disinvestimenti patrimoniali e finanziamenti", es_t: avanzoINV_t.value, es_t1: avanzoINV_t1.value },
        { etichetta: 'Avanzo/disavanzo complessivo', es_t: ris, es_t1: ris1 },
    ];
});
const contiSaldi = computed(() => props.rendiconto?.conti_saldi ?? []);

const editBlocchiAE = computed(() => (editBlocchiTabella.value ?? []).filter((b) => AREE_LETTERE.includes(b.area)));
const editBlocchiInvestimenti = computed(() => (editBlocchiTabella.value ?? []).filter((b) => b.area === 'INV'));
const editTotaleEntrateAE = computed(() => editBlocchiAE.value.reduce((s, b) => s + (Number(b.tot_e) || 0), 0));
const editTotaleUsciteAE = computed(() => editBlocchiAE.value.reduce((s, b) => s + (Number(b.tot_u) || 0), 0));
const editRisultatoAE = computed(() => Math.round((editTotaleEntrateAE.value - editTotaleUsciteAE.value) * 100) / 100);
const editTotaleEntrateINV = computed(() => editBlocchiInvestimenti.value.reduce((s, b) => s + (Number(b.tot_e) || 0), 0));
const editTotaleUsciteINV = computed(() => editBlocchiInvestimenti.value.reduce((s, b) => s + (Number(b.tot_u) || 0), 0));
const editAvanzoINV = computed(() => Math.round((editTotaleEntrateINV.value - editTotaleUsciteINV.value) * 100) / 100);
const editImposteInv = computed(() => {
    let esT = 0;
    for (const s of editSezioni.value) {
        if (s.tipo_sezione !== 'misto') continue;
        for (const v of s.voci ?? []) {
            if (v.codice_voce === 'EXP_TAXES') esT += Number(v.importo) || 0;
        }
    }
    return Math.round(esT * 100) / 100;
});
const editRigheDopoE = computed(() => [
    { etichetta: "Avanzo/disavanzo d'esercizio prima delle imposte", es_t: editRisultatoAE.value, es_t1: null },
    { etichetta: 'Imposte', es_t: null, es_t1: null },
    { etichetta: "Avanzo/disavanzo d'esercizio prima di investimenti e disinvestimenti patrimoniali, e finanziamenti", es_t: editRisultatoAE.value, es_t1: null },
]);
const editRigheDopoInv = computed(() => [
    { etichetta: 'Imposte', es_t: editImposteInv.value, es_t1: null },
    { etichetta: "Avanzo/disavanzo da entrate e uscite per investimenti e disinvestimenti patrimoniali e finanziamenti", es_t: editAvanzoINV.value, es_t1: null },
]);
const editRigheBloccoImmagine = computed(() => [
    { etichetta: "Avanzo/disavanzo d'esercizio prima di investimenti e disinvestimenti patrimoniali e finanziamenti", es_t: editRisultatoAE.value, es_t1: null },
    { etichetta: "Avanzo/disavanzo da entrate e uscite per investimenti e disinvestimenti patrimoniali e finanziamenti", es_t: editAvanzoINV.value, es_t1: null },
    { etichetta: 'Avanzo/disavanzo complessivo', es_t: risultatoPerCassa.value, es_t1: null },
]);

const avanzoLabel = (area) => {
    const labels = { A: 'di interesse generale', B: 'diverse', C: 'di raccolta fondi', D: 'finanziarie e patrimoniali' };
    return labels[area] ?? '';
};

function getCsrfToken() {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (token) return token;
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    if (match) return decodeURIComponent(match[1]);
    return page.props?.csrf_token ?? '';
}

const generatingPdf = ref(false);

async function generaPdf() {
    const payload = {
        anno: props.anno,
        data_generazione: dataGenerazione.value || null,
        sezioni: editSezioni.value.map((s) => ({
            sezione: s.sezione,
            voci: (s.voci ?? []).map((v) => ({
                codice_voce: v.codice_voce,
                tipo: v.tipo,
                importo: Number(v.importo) || 0,
                importo_anno_precedente: v.importo_anno_precedente != null && v.importo_anno_precedente !== '' ? Number(v.importo_anno_precedente) : null,
            })),
        })),
    };
    generatingPdf.value = true;
    try {
        const res = await fetch(route('reports.rendiconto-cassa.export-pdf.post'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/pdf',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify(payload),
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error(res.statusText);
        const blob = await res.blob();
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `rendiconto_cassa_${props.anno}.pdf`;
        a.click();
        URL.revokeObjectURL(url);
        closeExportModal();
    } catch (e) {
        console.error(e);
        alert('Errore durante la generazione del PDF.');
    } finally {
        generatingPdf.value = false;
    }
}
</script>

<template>
    <AppLayout title="Rendiconto per cassa">
        <Head title="Rendiconto economico per cassa" />
        <template #header>
            <div class="flex flex-wrap justify-between items-center gap-4">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Rendiconto economico per cassa</h2>
                <div class="flex items-center gap-3">
                    <label class="text-sm text-gray-600 dark:text-gray-400">Anno</label>
                    <select
                        :value="anno"
                        @change="changeAnno"
                        class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                    >
                        <option v-for="y in anni" :key="y" :value="y">{{ y }}</option>
                    </select>
                    <PrimaryButton v-if="anno_precedente_ha_movimenti" @click="scaricaPdfDiretto">Scarica PDF</PrimaryButton>
                    <PrimaryButton v-else @click="openExportModal">Esporta PDF</PrimaryButton>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="grid grid-cols-3 gap-4">
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Totale entrate</div>
                        <div class="text-xl font-semibold text-green-600">{{ formatEuro(rendiconto?.totale_entrate ?? 0) }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Totale uscite</div>
                        <div class="text-xl font-semibold text-red-600">{{ formatEuro(rendiconto?.totale_uscite ?? 0) }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Risultato per cassa</div>
                        <div class="text-xl font-semibold" :class="Number(rendiconto?.risultato_per_cassa ?? 0) >= 0 ? 'text-green-600' : 'text-red-600'">
                            {{ formatEuro(rendiconto?.risultato_per_cassa ?? 0) }}
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-600">
                        <span class="font-medium text-gray-900 dark:text-gray-100">Modello D – Anno {{ anno }}</span>
                    </div>
                    <div class="overflow-x-auto p-4">
                        <table class="min-w-full border-collapse border border-gray-200 dark:border-gray-600 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-left w-[32%]">Uscite</th>
                                    <th class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right w-[9%]">Es.t</th>
                                    <th class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right w-[9%]">Es.t-1</th>
                                    <th class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-left w-[32%]">Entrate</th>
                                    <th class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right w-[9%]">Es.t</th>
                                    <th class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right w-[9%]">Es.t-1</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                                <template v-for="(blocco, bi) in blocchiAE" :key="'ae-' + blocco.area + '-' + bi">
                                    <tr>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 font-medium">
                                            {{ blocco.titolo_uscite ? blocco.area + ') ' + blocco.titolo_uscite : '' }}
                                        </td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right"></td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right"></td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 font-medium">
                                            {{ blocco.titolo_entrate ? blocco.area + ') ' + blocco.titolo_entrate : '' }}
                                        </td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right"></td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right"></td>
                                    </tr>
                                    <tr v-for="(r, ri) in blocco.righe" :key="'aer-' + bi + '-' + ri">
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-gray-700 dark:text-gray-300">{{ r.desc_u || '' }}</td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right text-red-600">{{ r.t_u != null ? fmtNum(r.t_u) : '' }}</td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right text-gray-500">{{ r.t1_u != null ? fmtNum(r.t1_u) : '' }}</td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-gray-700 dark:text-gray-300">{{ r.desc_e || '' }}</td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right text-green-600">{{ r.t_e != null ? fmtNum(r.t_e) : '' }}</td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right text-gray-500">{{ r.t1_e != null ? fmtNum(r.t1_e) : '' }}</td>
                                    </tr>
                                    <tr class="bg-gray-50 dark:bg-gray-700/50 font-medium">
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5">Totale</td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right">{{ fmtNum(blocco.tot_u) }}</td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right">{{ fmtNum(blocco.tot_u1) }}</td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5">Totale</td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right">{{ fmtNum(blocco.tot_e) }}</td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right">{{ fmtNum(blocco.tot_e1) }}</td>
                                    </tr>
                                    <tr v-if="blocco.mostra_avanzo">
                                        <td colspan="3" class="border border-gray-200 dark:border-gray-600 px-3 py-1.5"></td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 font-medium">Avanzo/disavanzo attività {{ avanzoLabel(blocco.area) }}</td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right">{{ blocco.avanzo_t != null ? fmtNum(blocco.avanzo_t) : '' }}</td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right">{{ blocco.avanzo_t1 != null ? fmtNum(blocco.avanzo_t1) : '' }}</td>
                                    </tr>
                                </template>
                                <tr class="bg-gray-50 dark:bg-gray-700/50 font-medium">
                                    <td colspan="3" class="border border-gray-200 dark:border-gray-600 px-3 py-1.5"></td>
                                    <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5">Totale entrate della gestione</td>
                                    <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right">{{ fmtNum(totaleEntrateAE) }}</td>
                                    <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right">{{ fmtNum(totaleEntrateAE1) }}</td>
                                </tr>
                                <tr class="bg-gray-50 dark:bg-gray-700/50 font-medium">
                                    <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5">Totale uscite della gestione</td>
                                    <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right">{{ fmtNum(totaleUsciteAE) }}</td>
                                    <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right">{{ fmtNum(totaleUsciteAE1) }}</td>
                                    <td colspan="3" class="border border-gray-200 dark:border-gray-600 px-3 py-1.5"></td>
                                </tr>
                                <tr v-for="(rs, rsi) in righeDopoE" :key="'dopoe-' + rsi">
                                    <td colspan="3" class="border border-gray-200 dark:border-gray-600 px-3 py-1.5"></td>
                                    <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 font-medium">{{ rs.etichetta }}</td>
                                    <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right">{{ rs.es_t != null ? fmtNum(rs.es_t) : '' }}</td>
                                    <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right">{{ rs.es_t1 != null ? fmtNum(rs.es_t1) : '' }}</td>
                                </tr>
                                <template v-for="(blocco, bi) in blocchiInvestimenti" :key="'inv-' + bi">
                                    <tr>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 font-medium">{{ blocco.titolo_uscite || '' }}</td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right"></td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right"></td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 font-medium">{{ blocco.titolo_entrate || '' }}</td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right"></td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right"></td>
                                    </tr>
                                    <tr v-for="(r, ri) in blocco.righe" :key="'invr-' + bi + '-' + ri">
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-gray-700 dark:text-gray-300">{{ r.desc_u || '' }}</td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right text-red-600">{{ r.t_u != null ? fmtNum(r.t_u) : '' }}</td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right text-gray-500">{{ r.t1_u != null ? fmtNum(r.t1_u) : '' }}</td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-gray-700 dark:text-gray-300">{{ r.desc_e || '' }}</td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right text-green-600">{{ r.t_e != null ? fmtNum(r.t_e) : '' }}</td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right text-gray-500">{{ r.t1_e != null ? fmtNum(r.t1_e) : '' }}</td>
                                    </tr>
                                    <tr class="bg-gray-50 dark:bg-gray-700/50 font-medium">
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5">Totale</td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right">{{ fmtNum(blocco.tot_u) }}</td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right">{{ fmtNum(blocco.tot_u1) }}</td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5">Totale</td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right">{{ fmtNum(blocco.tot_e) }}</td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right">{{ fmtNum(blocco.tot_e1) }}</td>
                                    </tr>
                                </template>
                                <tr v-for="(rs, rsi) in righeDopoInv" :key="'dopoinv-' + rsi">
                                    <td colspan="3" class="border border-gray-200 dark:border-gray-600 px-3 py-1.5"></td>
                                    <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 font-medium">{{ rs.etichetta }}</td>
                                    <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right">{{ rs.es_t != null ? fmtNum(rs.es_t) : '' }}</td>
                                    <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right">{{ rs.es_t1 != null ? fmtNum(rs.es_t1) : '' }}</td>
                                </tr>
                                <tr v-for="(rs, rsi) in righeSintesi" :key="'img-' + rsi">
                                    <td colspan="3" class="border border-gray-200 dark:border-gray-600 px-3 py-1.5"></td>
                                    <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 font-medium">{{ rs.etichetta }}</td>
                                    <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right">{{ rs.es_t != null ? fmtNum(rs.es_t) : '' }}</td>
                                    <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right">{{ rs.es_t1 != null ? fmtNum(rs.es_t1) : '' }}</td>
                                </tr>
                                <template v-if="contiSaldi.length">
                                    <tr class="bg-gray-50 dark:bg-gray-700/50 font-medium">
                                        <td colspan="6" class="border border-gray-200 dark:border-gray-600 px-3 py-1.5">CASSA E BANCA</td>
                                    </tr>
                                    <tr v-for="(conto, ci) in contiSaldi" :key="'conto-' + ci">
                                        <td colspan="3" class="border border-gray-200 dark:border-gray-600 px-3 py-1.5"></td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5">{{ conto.nome }}</td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right">{{ conto.saldo_anno != null ? fmtNum(conto.saldo_anno) : '' }}</td>
                                        <td class="border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-right">{{ conto.saldo_anno_precedente != null ? fmtNum(conto.saldo_anno_precedente) : '' }}</td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modale Modifica prima di stampare -->
        <Teleport to="body">
            <div v-show="showExportModal" class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="fixed inset-0 bg-black/50" aria-hidden="true" @click="closeExportModal" />
                    <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-6xl w-full max-h-[90vh] flex flex-col">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-600">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Modifica prima di stampare PDF</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Modifica gli importi e la data di generazione; i totali si aggiornano automaticamente.</p>
                        </div>
                        <div class="px-6 py-4 overflow-y-auto flex-1">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Data di generazione</label>
                                <input
                                    v-model="dataGenerazione"
                                    type="datetime-local"
                                    class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm w-full max-w-xs"
                                />
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full border-collapse border border-gray-200 dark:border-gray-600 text-sm">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-left">Uscite</th>
                                            <th class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-right">Es.t (€)</th>
                                            <th class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-right">Es.t-1</th>
                                            <th class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-left">Entrate</th>
                                            <th class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-right">Es.t (€)</th>
                                            <th class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-right">Es.t-1</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template v-for="(blocco, bi) in editBlocchiAE" :key="'eba-' + blocco.area + '-' + bi">
                                            <tr>
                                                <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 font-medium">{{ blocco.titolo_uscite ? blocco.area + ') ' + blocco.titolo_uscite : '' }}</td>
                                                <td colspan="2" class="border border-gray-200 dark:border-gray-600 px-2 py-1"></td>
                                                <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 font-medium">{{ blocco.titolo_entrate ? blocco.area + ') ' + blocco.titolo_entrate : '' }}</td>
                                                <td colspan="2" class="border border-gray-200 dark:border-gray-600 px-2 py-1"></td>
                                            </tr>
                                            <tr v-for="(r, ri) in blocco.righe" :key="'eba-r-' + bi + '-' + ri">
                                                <td class="border border-gray-200 dark:border-gray-600 px-2 py-1">{{ r.desc_u || '' }}</td>
                                                <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-right">{{ r.t_u != null ? fmtNum(r.t_u) : '' }}</td>
                                                <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-right">
                                                    <input
                                                        v-if="r.voceU"
                                                        v-model.number="r.voceU.importo_anno_precedente"
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 w-20 text-right text-sm"
                                                    />
                                                    <span v-else>{{ r.t1_u != null ? fmtNum(r.t1_u) : '' }}</span>
                                                </td>
                                                <td class="border border-gray-200 dark:border-gray-600 px-2 py-1">{{ r.desc_e || '' }}</td>
                                                <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-right">{{ r.t_e != null ? fmtNum(r.t_e) : '' }}</td>
                                                <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-right">
                                                    <input
                                                        v-if="r.voceE"
                                                        v-model.number="r.voceE.importo_anno_precedente"
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 w-20 text-right text-sm"
                                                    />
                                                    <span v-else>{{ r.t1_e != null ? fmtNum(r.t1_e) : '' }}</span>
                                                </td>
                                            </tr>
                                            <tr class="bg-gray-50 dark:bg-gray-700/50 font-medium">
                                                <td class="border border-gray-200 dark:border-gray-600 px-2 py-1">Totale</td>
                                                <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-right">{{ fmtNum(blocco.tot_u) }}</td>
                                                <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-right">{{ fmtNum(blocco.tot_u1) }}</td>
                                                <td class="border border-gray-200 dark:border-gray-600 px-2 py-1">Totale</td>
                                                <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-right">{{ fmtNum(blocco.tot_e) }}</td>
                                                <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-right">{{ fmtNum(blocco.tot_e1) }}</td>
                                            </tr>
                                            <tr v-if="blocco.mostra_avanzo">
                                                <td colspan="3" class="border border-gray-200 dark:border-gray-600 px-2 py-1"></td>
                                                <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 font-medium">Avanzo/disavanzo attività {{ avanzoLabel(blocco.area) }}</td>
                                                <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-right">{{ blocco.avanzo_t != null ? fmtNum(blocco.avanzo_t) : '' }}</td>
                                                <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-right">{{ blocco.avanzo_t1 != null ? fmtNum(blocco.avanzo_t1) : '' }}</td>
                                            </tr>
                                        </template>
                                        <tr class="bg-gray-50 dark:bg-gray-700/50 font-medium">
                                            <td colspan="3" class="border border-gray-200 dark:border-gray-600 px-2 py-1"></td>
                                            <td class="border border-gray-200 dark:border-gray-600 px-2 py-1">Totale entrate della gestione</td>
                                            <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-right">{{ fmtNum(editTotaleEntrateAE) }}</td>
                                            <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-right"></td>
                                        </tr>
                                        <tr class="bg-gray-50 dark:bg-gray-700/50 font-medium">
                                            <td class="border border-gray-200 dark:border-gray-600 px-2 py-1">Totale uscite della gestione</td>
                                            <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-right">{{ fmtNum(editTotaleUsciteAE) }}</td>
                                            <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-right"></td>
                                            <td colspan="3" class="border border-gray-200 dark:border-gray-600 px-2 py-1"></td>
                                        </tr>
                                        <tr v-for="(rs, rsi) in editRigheDopoE" :key="'edopoe-' + rsi">
                                            <td colspan="3" class="border border-gray-200 dark:border-gray-600 px-2 py-1"></td>
                                            <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 font-medium">{{ rs.etichetta }}</td>
                                            <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-right">{{ rs.es_t != null ? fmtNum(rs.es_t) : '' }}</td>
                                            <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-right"></td>
                                        </tr>
                                        <template v-for="(blocco, bi) in editBlocchiInvestimenti" :key="'ebi-' + bi">
                                            <tr>
                                                <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 font-medium">{{ blocco.titolo_uscite || '' }}</td>
                                                <td colspan="2" class="border border-gray-200 dark:border-gray-600 px-2 py-1"></td>
                                                <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 font-medium">{{ blocco.titolo_entrate || '' }}</td>
                                                <td colspan="2" class="border border-gray-200 dark:border-gray-600 px-2 py-1"></td>
                                            </tr>
                                            <tr v-for="(r, ri) in blocco.righe" :key="'ebi-r-' + bi + '-' + ri">
                                                <td class="border border-gray-200 dark:border-gray-600 px-2 py-1">{{ r.desc_u || '' }}</td>
                                                <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-right">{{ r.t_u != null ? fmtNum(r.t_u) : '' }}</td>
                                                <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-right">
                                                    <input
                                                        v-if="r.voceU"
                                                        v-model.number="r.voceU.importo_anno_precedente"
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 w-20 text-right text-sm"
                                                    />
                                                    <span v-else>{{ r.t1_u != null ? fmtNum(r.t1_u) : '' }}</span>
                                                </td>
                                                <td class="border border-gray-200 dark:border-gray-600 px-2 py-1">{{ r.desc_e || '' }}</td>
                                                <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-right">{{ r.t_e != null ? fmtNum(r.t_e) : '' }}</td>
                                                <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-right">
                                                    <input
                                                        v-if="r.voceE"
                                                        v-model.number="r.voceE.importo_anno_precedente"
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 w-20 text-right text-sm"
                                                    />
                                                    <span v-else>{{ r.t1_e != null ? fmtNum(r.t1_e) : '' }}</span>
                                                </td>
                                            </tr>
                                            <tr class="bg-gray-50 dark:bg-gray-700/50 font-medium">
                                                <td class="border border-gray-200 dark:border-gray-600 px-2 py-1">Totale</td>
                                                <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-right">{{ fmtNum(blocco.tot_u) }}</td>
                                                <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-right">{{ fmtNum(blocco.tot_u1) }}</td>
                                                <td class="border border-gray-200 dark:border-gray-600 px-2 py-1">Totale</td>
                                                <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-right">{{ fmtNum(blocco.tot_e) }}</td>
                                                <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-right">{{ fmtNum(blocco.tot_e1) }}</td>
                                            </tr>
                                        </template>
                                        <tr v-for="(rs, rsi) in editRigheDopoInv" :key="'edopoinv-' + rsi">
                                            <td colspan="3" class="border border-gray-200 dark:border-gray-600 px-2 py-1"></td>
                                            <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 font-medium">{{ rs.etichetta }}</td>
                                            <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-right">{{ rs.es_t != null ? fmtNum(rs.es_t) : '' }}</td>
                                            <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-right"></td>
                                        </tr>
                                        <tr v-for="(rs, rsi) in editRigheBloccoImmagine" :key="'eimg-' + rsi">
                                            <td colspan="3" class="border border-gray-200 dark:border-gray-600 px-2 py-1"></td>
                                            <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 font-medium">{{ rs.etichetta }}</td>
                                            <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-right">{{ rs.es_t != null ? fmtNum(rs.es_t) : '' }}</td>
                                            <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-right"></td>
                                        </tr>
                                        <template v-if="contiSaldi.length">
                                            <tr class="bg-gray-50 dark:bg-gray-700/50 font-medium">
                                                <td colspan="6" class="border border-gray-200 dark:border-gray-600 px-2 py-1">CASSA E BANCA</td>
                                            </tr>
                                            <tr v-for="(conto, ci) in contiSaldi" :key="'econto-' + ci">
                                                <td colspan="3" class="border border-gray-200 dark:border-gray-600 px-2 py-1"></td>
                                                <td class="border border-gray-200 dark:border-gray-600 px-2 py-1">{{ conto.nome }}</td>
                                                <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-right">{{ conto.saldo_anno != null ? fmtNum(conto.saldo_anno) : '' }}</td>
                                                <td class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-right">{{ conto.saldo_anno_precedente != null ? fmtNum(conto.saldo_anno_precedente) : '' }}</td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                            <div class="border-t border-gray-200 dark:border-gray-600 pt-4 mt-4">
                                <div class="flex justify-between items-center text-sm">
                                    <span class="font-medium text-gray-700 dark:text-gray-300">Totale entrate</span>
                                    <span class="text-green-600 font-medium">{{ formatEuro(totaleEntrate) }}</span>
                                </div>
                                <div class="flex justify-between items-center text-sm mt-1">
                                    <span class="font-medium text-gray-700 dark:text-gray-300">Totale uscite</span>
                                    <span class="text-red-600 font-medium">{{ formatEuro(totaleUscite) }}</span>
                                </div>
                                <div class="flex justify-between items-center text-sm mt-1">
                                    <span class="font-medium text-gray-700 dark:text-gray-300">Risultato per cassa</span>
                                    <span class="font-medium" :class="risultatoPerCassa >= 0 ? 'text-green-600' : 'text-red-600'">{{ formatEuro(risultatoPerCassa) }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-600 flex justify-end gap-2">
                            <SecondaryButton @click="closeExportModal">Annulla</SecondaryButton>
                            <PrimaryButton :disabled="generatingPdf" @click="generaPdf">
                                {{ generatingPdf ? 'Generazione...' : 'Genera PDF' }}
                            </PrimaryButton>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
