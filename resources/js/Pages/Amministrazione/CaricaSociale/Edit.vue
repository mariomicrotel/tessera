<template>
  <AppLayout :title="`Modifica – ${carica.nome}`">
    <div class="max-w-2xl mx-auto px-4 py-6">

      <div class="flex items-center gap-2 text-sm text-gray-500 mb-6">
        <Link :href="route('cariche-sociali.index', tenant)" class="hover:text-gray-700">Cariche Sociali</Link>
        <span>/</span>
        <Link :href="route('cariche-sociali.show', [tenant, carica.id])" class="hover:text-gray-700">{{ carica.nome }}</Link>
        <span>/</span>
        <span class="text-gray-900 font-medium">Modifica</span>
      </div>

      <div class="bg-white rounded-xl shadow border border-gray-100 p-6">
        <h2 class="text-lg font-bold text-gray-900 mb-5">Modifica Carica Sociale</h2>

        <form @submit.prevent="submit" class="space-y-5">

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Organo <span class="text-red-500">*</span></label>
            <select v-model="form.organo_id" class="input-field" required>
              <option v-for="o in organi" :key="o.id" :value="o.id">{{ o.nome }}</option>
            </select>
            <p v-if="form.errors.organo_id" class="text-xs text-red-600 mt-1">{{ form.errors.organo_id }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nome carica <span class="text-red-500">*</span></label>
            <input v-model="form.nome" type="text" maxlength="100" required class="input-field" />
            <p v-if="form.errors.nome" class="text-xs text-red-600 mt-1">{{ form.errors.nome }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Ordine di visualizzazione</label>
            <input v-model.number="form.ordine" type="number" min="0" class="input-field w-32" />
          </div>

          <div class="flex items-center gap-3">
            <input v-model="form.multiplo" type="checkbox" id="multiplo"
                   class="h-4 w-4 rounded border-gray-300 text-blue-600" />
            <label for="multiplo" class="text-sm text-gray-700">Multi-assegnabile</label>
          </div>

          <div class="flex justify-end gap-3 pt-2">
            <Link :href="route('cariche-sociali.show', [tenant, carica.id])" class="btn-secondary">Annulla</Link>
            <button type="submit" :disabled="form.processing" class="btn-primary">
              {{ form.processing ? 'Salvataggio…' : 'Salva modifiche' }}
            </button>
          </div>
        </form>
      </div>

    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({ carica: Object, organi: Array })

const page   = usePage()
const tenant = computed(() => page.props.tenant?.slug ?? page.props.auth?.tenant?.slug)

const form = useForm({
  organo_id: props.carica.organo_id,
  nome:      props.carica.nome,
  ordine:    props.carica.ordine,
  multiplo:  props.carica.multiplo,
})

const submit = () => {
  form.put(route('cariche-sociali.update', [tenant.value, props.carica.id]))
}
</script>
