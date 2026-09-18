<template>
  <AppLayout>
    <div class="mx-auto max-w-md py-12">
      <div class="rounded-lg bg-white p-6 shadow">
        <h1 class="mb-4 text-2xl font-bold">Pay with QRIS</h1>

        <div class="mb-6 rounded bg-blue-50 p-4 text-sm text-blue-900">
          <p class="font-semibold">Pro Plan: IDR {{ formatCurrency(amountIdr) }}</p>
          <p class="mt-2">Scan the QR code below with your phone to complete payment.</p>
          <p class="mt-2 text-xs">This payment is valid for 30 minutes. After payment, you'll need to renew manually after 30 days.</p>
        </div>

        <div v-if="qrString" class="mb-6 flex justify-center">
          <canvas ref="qrCanvas" class="border-2 border-gray-300"></canvas>
        </div>

        <div v-if="error" class="mb-4 rounded bg-red-50 p-3 text-sm text-red-900">{{ error }}</div>

        <div v-if="polling" class="mb-4 rounded bg-amber-50 p-3 text-sm text-amber-900">
          Waiting for payment confirmation... ({{ timeLeft }}s)
        </div>

        <div class="flex gap-3">
          <Link href="/plan" class="flex-1 rounded border border-gray-300 py-2 text-center text-sm font-medium hover:bg-gray-50">
            Back to Plan
          </Link>
          <button
            v-if="!polling"
            @click="startPolling"
            class="flex-1 rounded bg-blue-600 py-2 text-sm font-medium text-white hover:bg-blue-700"
          >
            I've Paid
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import QRCode from 'qrcode'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  orderId: String,
  amountIdr: Number,
  qrString: String,
  expiredAt: String,
})

const qrCanvas = ref(null)
const error = ref(null)
const polling = ref(false)
const timeLeft = ref(0)
let pollInterval = null

const formatCurrency = (value) => {
  return new Intl.NumberFormat('id-ID').format(value)
}

onMounted(async () => {
  if (qrString && qrCanvas.value) {
    try {
      await QRCode.toCanvas(qrCanvas.value, qrString, { width: 250 })
    } catch (err) {
      error.value = 'Failed to generate QR code'
    }
  }
})

const startPolling = () => {
  polling.value = true
  timeLeft.value = 300

  pollInterval = setInterval(async () => {
    timeLeft.value--

    if (timeLeft.value <= 0) {
      clearInterval(pollInterval)
      error.value = 'Payment confirmation timeout'
      polling.value = false
      return
    }

    try {
      const response = await fetch(`/api/payments/${props.orderId}/status`)
      if (response.ok) {
        const data = await response.json()
        if (data.status === 'paid') {
          clearInterval(pollInterval)
          window.location.href = '/plan'
        }
      }
    } catch (err) {
      // Continue polling
    }
  }, 2000)
}

watch(
  () => props.orderId,
  () => {
    if (polling.value) {
      clearInterval(pollInterval)
      polling.value = false
    }
  }
)

onBeforeUnmount(() => {
  if (pollInterval) clearInterval(pollInterval)
})
</script>
