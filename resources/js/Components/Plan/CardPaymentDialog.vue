<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
    <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
      <h2 class="mb-4 text-lg font-semibold">Subscribe to Pro</h2>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div id="card-element" class="rounded border border-gray-300 p-3"></div>
        <div id="card-errors" class="text-sm text-red-600"></div>

        <div class="mt-4 rounded bg-blue-50 p-3 text-sm text-blue-900">
          <p class="font-semibold">Pro Plan: $10/month</p>
          <p class="mt-1">100 docs/month, 10 members, 10GB storage</p>
          <p class="mt-2 text-xs text-gray-600">Auto-renewal: Your card will be charged monthly.</p>
        </div>

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
            class="flex-1 rounded bg-blue-600 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
          >
            {{ loading ? 'Processing...' : 'Subscribe Now' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { loadStripe } from '@stripe/stripe-js'

const props = defineProps({
  open: Boolean,
  stripeKey: String,
})

const emit = defineEmits(['close', 'subscribed'])

const loading = ref(false)
const stripe = ref(null)
const elements = ref(null)
const cardElement = ref(null)

onMounted(async () => {
  stripe.value = await loadStripe(props.stripeKey)
  elements.value = stripe.value.elements()
  cardElement.value = elements.value.create('card')

  watch(
    () => props.open,
    (newOpen) => {
      if (newOpen && cardElement.value && !cardElement.value._isMounted) {
        setTimeout(() => {
          if (document.getElementById('card-element')) {
            cardElement.value.mount('#card-element')
          }
        }, 50)
      }
    }
  )
})

const handleSubmit = async () => {
  if (!stripe.value || !cardElement.value) return

  loading.value = true
  const cardErrors = document.getElementById('card-errors')

  try {
    // Create setup intent for saving card
    const setupResponse = await fetch('/plan/checkout', { method: 'POST' })
    const { clientSecret } = await setupResponse.json()

    // Confirm setup intent to create payment method
    const { setupIntent, error } = await stripe.value.confirmCardSetup(clientSecret, {
      payment_method: {
        card: cardElement.value,
      },
    })

    if (error) {
      cardErrors.textContent = error.message
      loading.value = false
      return
    }

    // Subscribe with the confirmed payment method
    const response = await fetch('/plan/subscribe', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content,
      },
      body: JSON.stringify({
        plan: 'pro',
        payment_method: setupIntent.payment_method,
      }),
    })

    if (!response.ok) {
      const data = await response.json()
      cardErrors.textContent = data.message || 'Subscription failed'
      loading.value = false
      return
    }

    emit('subscribed')
    window.location.href = '/plan'
  } catch (err) {
    cardErrors.textContent = err.message || 'An error occurred'
    loading.value = false
  }
}
</script>
