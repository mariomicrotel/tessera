<template>
  <AppLayout title="Nuovo Centro di Costo">
    <div class="max-w-2xl mx-auto px-4 py-6">
      <div class="flex items-center gap-2 text-sm text-gray-500 mb-6">
        <Link :href="route('centri-di-costo.index', tenant)" class="hover:text-gray-700">Centri di Costo</Link>
        <span>/</span>
        <span class="text-gray-900 font-medium">Nuovo</span>
      </div>

      <div class="bg-white rounded-xl shadow border border-gray-100 p-6">
        <h2 class="text-lg font-bold text-gray-900 mb-5">Nuovo Centro di Costo</h2>

        <form @submit.prevent="submit" class="space-y-5">
          <div class="grid grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Codice <span class="text-red-500">*</span></label>
              <input v-model="form.codice" type="text" maxlength="20" required
                     placeholder="CDC-01" class="input-field font-mono" />
              <p v-if="form.errors.codice" class="text-xs text-red-600 mt-1">{{ form.errors.codice }}</p>
            </div>
            <div class="col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Descrizione <span class="text-red-500">*</span></label>
              <input v-model="form.descrizione" type="text" maxlength="200" required
                     placeholder="es. Attività sportiva, Sede Milano" class="input-field" />
              <p v-if="form.errors.descrizione" class="text-xs text-red-600 mt-1">{{ form.errors.descrizione }}</p>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Note</label>
            <textarea v-model="form.note" rows="3" maxlength="1000" class="input-field resize-none"
                      placeholder="Descrizione estesa del centro di costo…"></textarea>
          </div>

          <div class="flex items-center gap-3">
            <input v-model="form.attivo" type="checkbox" id="attivo"
                   class="h-4 w-4 rounded border-gray-300 text-blue-600" />
            <label for="attivo" class="text-sm text-gray-700">Centro attivo</label>
          </div>

          <div class="flex justify-end gap-3 pt-2">
            <Link :href="route('centri-di-costo.index', tenant)" class="btn-secondary">Annulla</Link>
            <button type="submit" :disabled="form.processing" class="btn-primary">
              {{ form.processing ? 'Salvataggio…' : 'Crea centro' }}
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

const page   = usePage()
const tenant = computed(() => page.props.tenant?.slug ?? page.props.auth?.tenant?.slug)

const form = useForm({ codice: '', descrizione: '', note: '', attivo: true })

const submit = () => form.post(route('centri-di-costo.store', tenant.value))
</script>
