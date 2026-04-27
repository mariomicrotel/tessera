<template>
  <AppLayout :title="`Builder Email – ${typeLabel}`">
    <div class="max-w-7xl mx-auto px-4 py-6 space-y-6">

      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
            <Link :href="route('email-templates.index', tenant)" class="hover:text-gray-700">Email Templates</Link>
            <span>/</span>
            <span class="text-gray-900 font-medium">{{ typeLabel }}</span>
          </div>
          <h1 class="text-2xl font-bold text-gray-900">Builder – {{ typeLabel }}</h1>
        </div>
        <div class="flex items-center gap-2">
          <button @click="sendTest" :disabled="sending"
                  class="btn-secondary flex items-center gap-2 text-sm">
            <PaperAirplaneIcon class="h-4 w-4" />
            {{ sending ? 'Invio…' : 'Invia test' }}
          </button>
          <button @click="save" :disabled="form.processing"
                  class="btn-primary flex items-center gap-2 text-sm">
            <CheckIcon class="h-4 w-4" />
            {{ form.processing ? 'Salvataggio…' : 'Salva' }}
          </button>
        </div>
      </div>

      <!-- Toast -->
      <div v-if="toast" :class="['fixed top-4 right-4 z-50 px-4 py-3 rounded-xl shadow-lg text-white text-sm font-medium transition-all',
                                  toast.type === 'success' ? 'bg-green-600' : 'bg-red-600']">
        {{ toast.message }}
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Colonna sinistra: editor -->
        <div class="space-y-4">

          <!-- Oggetto email -->
          <div class="bg-white rounded-xl shadow border border-gray-100 p-4">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Oggetto email</label>
            <input v-model="form.subject" type="text" @input="schedulePreview"
                   class="input-field" placeholder="Oggetto del messaggio" />
          </div>

          <!-- Toolbar placeholder -->
          <div class="bg-white rounded-xl shadow border border-gray-100 p-4">
            <p class="text-xs font-semibold text-gray-600 uppercase mb-2">Inserisci placeholder</p>
            <div class="flex flex-wrap gap-2">
              <button v-for="ph in placeholders" :key="ph.key"
                      @click="insertPlaceholder(ph.key)"
                      :title="ph.description"
                      class="px-2 py-1 rounded bg-blue-50 text-blue-700 text-xs font-mono hover:bg-blue-100 transition-colors border border-blue-200">
                {{ '{' + '{' + ph.key + '}' + '}' }}
              </button>
            </div>
            <p class="text-xs text-gray-400 mt-2">Click per inserire nel corpo del testo alla posizione del cursore.</p>
          </div>

          <!-- Editor corpo HTML -->
          <div class="bg-white rounded-xl shadow border border-gray-100 p-4">
            <div class="flex items-center justify-between mb-2">
              <label class="text-sm font-semibold text-gray-700">Corpo email (HTML)</label>
              <div class="flex gap-1">
                <button @click="viewMode = 'html'"
                        :class="['px-2 py-1 text-xs rounded', viewMode === 'html' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600']">
                  HTML
                </button>
                <button @click="viewMode = 'visual'"
                        :class="['px-2 py-1 text-xs rounded', viewMode === 'visual' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600']">
                  Visuale
                </button>
              </div>
            </div>

            <!-- Raw HTML editor -->
            <textarea v-if="viewMode === 'html'"
                      ref="bodyTextarea"
                      v-model="form.body_html"
                      @input="schedulePreview"
                      rows="20"
                      class="w-full font-mono text-xs border border-gray-200 rounded-lg p-3 resize-y focus:ring-2 focus:ring-blue-300 focus:outline-none"
                      placeholder="<p>Gentile {{member_name}},</p>&#10;<p>...</p>"></textarea>

            <!-- Quick visual editor (basic) -->
            <div v-else class="space-y-2">
              <div class="flex gap-1 border-b border-gray-100 pb-2">
                <button @click="wrapSelection('b')" class="visual-btn font-bold">B</button>
                <button @click="wrapSelection('i')" class="visual-btn italic">I</button>
                <button @click="wrapSelection('u')" class="visual-btn underline">U</button>
                <button @click="insertTag('h2')" class="visual-btn text-xs">H2</button>
                <button @click="insertTag('h3')" class="visual-btn text-xs">H3</button>
                <button @click="insertTag('p')" class="visual-btn text-xs">P</button>
                <button @click="insertTag('ul')" class="visual-btn text-xs">UL</button>
              </div>
              <textarea
                ref="bodyTextarea"
                v-model="form.body_html"
                @input="schedulePreview"
                rows="18"
                class="w-full font-mono text-xs border border-gray-200 rounded-lg p-3 resize-y focus:ring-2 focus:ring-blue-300 focus:outline-none"></textarea>
            </div>
          </div>
        </div>

        <!-- Colonna destra: anteprima -->
        <div class="space-y-4">
          <div class="bg-white rounded-xl shadow border border-gray-100 overflow-hidden sticky top-4">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
              <h2 class="text-sm font-semibold text-gray-700">Anteprima</h2>
              <span class="text-xs text-gray-400">Con valori di esempio</span>
            </div>

            <!-- Oggetto preview -->
            <div class="px-5 py-2 border-b border-gray-100 bg-gray-50">
              <p class="text-xs text-gray-500">Oggetto:</p>
              <p class="text-sm font-medium text-gray-900">{{ previewSubject || '(oggetto vuoto)' }}</p>
            </div>

            <!-- Corpo preview -->
            <div class="px-5 py-4 overflow-auto max-h-[60vh]">
              <div v-if="previewHtml"
                   class="prose prose-sm max-w-none"
                   v-html="previewHtml"></div>
              <p v-else class="text-sm text-gray-400 italic">
                L'anteprima apparirà qui mentre scrivi…
              </p>
            </div>
          </div>
        </div>

      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Link, useForm, usePage, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { CheckIcon, PaperAirplaneIcon } from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({
  template:        Object,
  typeLabel:       String,
  placeholders:    Array,
  preview_samples: Object,
})

const page   = usePage()
const tenant = computed(() => page.props.tenant?.slug ?? page.props.auth?.tenant?.slug)

const form = useForm({
  subject:   props.template?.subject   ?? '',
  body_html: props.template?.body_html ?? '',
})

const viewMode       = ref('html')
const bodyTextarea   = ref(null)
const previewHtml    = ref('')
const previewSubject = ref('')
const sending        = ref(false)
const toast          = ref(null)

let previewTimeout = null

// Anteprima live con debounce 400ms
const schedulePreview = () => {
  clearTimeout(previewTimeout)
  previewTimeout = setTimeout(fetchPreview, 400)
}

const fetchPreview = async () => {
  try {
    const { data } = await axios.post(
      route('email-templates.preview', [tenant.value, props.template.tipo]),
      { subject: form.subject, body_html: form.body_html }
    )
    previewHtml.value    = data.body_html
    previewSubject.value = data.subject
  } catch {
    // silently fail preview
  }
}

// Carica anteprima iniziale
fetchPreview()

// Inserisci placeholder alla posizione del cursore nel textarea
const insertPlaceholder = (key) => {
  const el  = bodyTextarea.value
  if (!el) {
    form.body_html += '{{' + key + '}}'
    schedulePreview()
    return
  }
  const tag   = '{{' + key + '}}'
  const start = el.selectionStart ?? form.body_html.length
  const end   = el.selectionEnd ?? start
  form.body_html =
    form.body_html.substring(0, start) +
    tag +
    form.body_html.substring(end)

  schedulePreview()
  setTimeout(() => {
    el.selectionStart = el.selectionEnd = start + tag.length
    el.focus()
  }, 0)
}

// Wrap selezione in tag HTML
const wrapSelection = (tag) => {
  const el = bodyTextarea.value
  if (!el) return
  const start = el.selectionStart
  const end   = el.selectionEnd
  const sel   = form.body_html.substring(start, end)
  form.body_html =
    form.body_html.substring(0, start) +
    `<${tag}>${sel}</${tag}>` +
    form.body_html.substring(end)
  schedulePreview()
}

const insertTag = (tag) => {
  const el = bodyTextarea.value
  if (!el) return
  const pos = el.selectionStart ?? form.body_html.length
  const insert = `<${tag}></${tag}>`
  form.body_html = form.body_html.substring(0, pos) + insert + form.body_html.substring(pos)
  schedulePreview()
}

// Salva
const save = () => {
  form.put(route('email-templates.update', [tenant.value, props.template.tipo]), {
    onSuccess: () => showToast('Template salvato.', 'success'),
    onError:   () => showToast('Errore nel salvataggio.', 'error'),
  })
}

// Invia test
const sendTest = async () => {
  sending.value = true
  try {
    const { data } = await axios.post(
      route('email-templates.send-test', [tenant.value, props.template.tipo]),
      { subject: form.subject, body_html: form.body_html }
    )
    showToast(`Email di test inviata a ${data.to}`, 'success')
  } catch {
    showToast('Errore nell\'invio della email di test.', 'error')
  } finally {
    sending.value = false
  }
}

const showToast = (message, type = 'success') => {
  toast.value = { message, type }
  setTimeout(() => { toast.value = null }, 4000)
}
</script>

<style scoped>
.visual-btn {
  @apply px-2 py-1 rounded text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 font-mono transition-colors;
}
</style>
