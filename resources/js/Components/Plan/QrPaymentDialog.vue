<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
    <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
      <h2 class="mb-4 text-lg font-semibold">Pay with QRIS</h2>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div class="rounded bg-blue-50 p-3 text-sm text-blue-900">
          <p class="font-semibold">Pro Plan: $10/month (IDR ~150,000)</p>
          <p class="mt-2 text-xs">
            ⚠️ This is a one-time payment. You'll need to renew manually after 30 days.
          </p>
        </div>

        <div v-if="error" class="rounded bg-red-50 p-3 text-sm text-red-900">{{ error }}</div>

        <div class="flex gap-3">
          <button
            type="button"
            @click="() => $emit('close')"
            class="flex-1 rounded border border-gray-300 py-2 text-sm font-medium hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            :disabled="loading"
            class="flex-1 rounded bg-green-600 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
          >
            {{ loading ? 'Processing...' : 'Generate QR' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const emit = defineEmits(['close'])

const loading = ref(false)
const error = ref(null)

const handleSubmit = async () => {
  loading.value = true
  error.value = null

  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
    const response = await fetch('/plan/qr', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken,
      },
      body: JSON.stringify({
        plan: 'pro',
      }),
    })

    if (!response.ok) {
      const data = await response.json()
      error.value = data.errors?.plan || 'Failed to generate QR code'
      loading.value = false
      return
    }

    window.location.href = response.url
  } catch (err) {
    error.value = err.message || 'An error occurred'
    loading.value = false
  }
}
</script>
