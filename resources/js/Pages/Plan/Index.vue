<template>
  <AuthenticatedLayout>
    <div class="mx-auto max-w-4xl space-y-8 py-12">
      <!-- Current Plan -->
      <div class="rounded-lg bg-white p-6 shadow">
        <h1 class="mb-4 text-2xl font-bold">Current Plan</h1>

        <div class="mb-6 grid grid-cols-3 gap-4">
          <div class="rounded bg-gradient-to-br from-blue-50 to-blue-100 p-4">
            <p class="text-sm font-medium text-gray-600">Plan</p>
            <p class="mt-1 text-2xl font-bold capitalize">{{ subscription.plan }}</p>
            <p v-if="subscription.autoRenews" class="mt-2 text-xs text-gray-600">Auto-renews monthly</p>
            <p v-else class="mt-2 text-xs text-gray-600">Manual renewal after 30 days</p>
          </div>

          <div class="rounded bg-gradient-to-br from-green-50 to-green-100 p-4">
            <p class="text-sm font-medium text-gray-600">Status</p>
            <p class="mt-1 capitalize">
              <span v-if="subscription.status === 'active'" class="text-xl font-bold text-green-600">Active</span>
              <span v-else-if="subscription.status === 'past_due'" class="text-xl font-bold text-amber-600">Past Due</span>
              <span v-else class="text-xl font-bold text-gray-600">{{ subscription.status }}</span>
            </p>
          </div>

          <div v-if="subscription.expiredAt" class="rounded bg-gradient-to-br from-purple-50 to-purple-100 p-4">
            <p class="text-sm font-medium text-gray-600">Expires</p>
            <p class="mt-1 text-xl font-bold">{{ formatDate(subscription.expiredAt) }}</p>
          </div>
        </div>

        <div v-if="currentPlanConfig" class="rounded bg-gray-50 p-4">
          <h3 class="font-semibold">Benefits</h3>
          <ul class="mt-2 space-y-1 text-sm">
            <li>📄 {{ currentPlanConfig.max_docs_per_period }} docs/{{ periodLabel }}</li>
            <li>👥 {{ currentPlanConfig.max_members }} members</li>
            <li>💾 {{ formatBytes(currentPlanConfig.max_storage_bytes) }} storage</li>
          </ul>
        </div>

        <!-- Upgrade/Downgrade Buttons -->
        <div v-if="subscription.plan === 'free'" class="mt-4 flex gap-3">
          <button
            @click="cardDialogOpen = true"
            class="flex-1 rounded bg-blue-600 py-2 font-medium text-white hover:bg-blue-700"
          >
            Upgrade to Pro
          </button>
          <button
            @click="qrDialogOpen = true"
            class="flex-1 rounded bg-green-600 py-2 font-medium text-white hover:bg-green-700"
          >
            Pay with QRIS
          </button>
        </div>
        <div v-else-if="subscription.plan === 'pro'" class="mt-4 flex gap-3">
          <button
            @click="contactSales"
            class="flex-1 rounded bg-purple-600 py-2 font-medium text-white hover:bg-purple-700"
          >
            Upgrade to Enterprise
          </button>
          <button
            @click="downgrade"
            class="flex-1 rounded border border-gray-300 py-2 font-medium hover:bg-gray-50"
          >
            Downgrade to Free
          </button>
        </div>
      </div>

      <!-- Usage -->
      <div v-if="usage" class="rounded-lg bg-white p-6 shadow">
        <h2 class="mb-4 text-xl font-bold">Usage</h2>

        <div class="space-y-4">
          <div>
            <div class="flex justify-between text-sm">
              <span>Documents: {{ usage.documentsUsed }} / {{ usage.documentsLimit || '∞' }}</span>
              <span v-if="usage.documentsLimit" class="text-gray-600">{{ documentUsagePercent }}%</span>
            </div>
            <div v-if="usage.documentsLimit" class="mt-1 h-2 rounded-full bg-gray-200">
              <div
                class="h-full rounded-full bg-blue-500"
                :style="{ width: documentUsagePercent + '%' }"
              ></div>
            </div>
          </div>

          <div>
            <div class="flex justify-between text-sm">
              <span>Members: {{ usage.membersCount }} / {{ usage.membersLimit || '∞' }}</span>
              <span v-if="usage.membersLimit" class="text-gray-600">{{ memberUsagePercent }}%</span>
            </div>
            <div v-if="usage.membersLimit" class="mt-1 h-2 rounded-full bg-gray-200">
              <div
                class="h-full rounded-full bg-green-500"
                :style="{ width: memberUsagePercent + '%' }"
              ></div>
            </div>
          </div>

          <div>
            <div class="flex justify-between text-sm">
              <span>Storage: {{ formatBytes(usage.storageBytesUsed) }} / {{ formatBytes(usage.storageBytesLimit) }}</span>
              <span class="text-gray-600">{{ storageUsagePercent }}%</span>
            </div>
            <div class="mt-1 h-2 rounded-full bg-gray-200">
              <div
                class="h-full rounded-full bg-purple-500"
                :style="{ width: storageUsagePercent + '%' }"
              ></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Payment History -->
      <div v-if="payments.length > 0" class="rounded-lg bg-white p-6 shadow">
        <h2 class="mb-4 text-xl font-bold">Payment History</h2>

        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b">
                <th class="px-4 py-2 text-left font-semibold">Date</th>
                <th class="px-4 py-2 text-left font-semibold">Plan</th>
                <th class="px-4 py-2 text-right font-semibold">Amount</th>
                <th class="px-4 py-2 text-left font-semibold">Status</th>
                <th class="px-4 py-2 text-left font-semibold">Method</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="payment in payments" :key="payment.id" class="border-b hover:bg-gray-50">
                <td class="px-4 py-2">{{ formatDate(payment.created_at) }}</td>
                <td class="px-4 py-2 capitalize">{{ payment.plan }}</td>
                <td class="px-4 py-2 text-right">
                  <span v-if="payment.provider === 'stripe'">{{ formatUSD(payment.amount_usd_cents) }}</span>
                  <span v-else>IDR {{ formatCurrency(payment.amount) }}</span>
                </td>
                <td class="px-4 py-2">
                  <span
                    v-if="payment.status === 'paid'"
                    class="inline-block rounded bg-green-100 px-2 py-1 text-xs font-medium text-green-800"
                  >
                    Paid
                  </span>
                  <span
                    v-else-if="payment.status === 'pending'"
                    class="inline-block rounded bg-yellow-100 px-2 py-1 text-xs font-medium text-yellow-800"
                  >
                    Pending
                  </span>
                  <span
                    v-else
                    class="inline-block rounded bg-red-100 px-2 py-1 text-xs font-medium text-red-800"
                  >
                    {{ payment.status }}
                  </span>
                </td>
                <td class="px-4 py-2 capitalize">{{ payment.provider }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Card on File -->
      <div v-if="card" class="rounded-lg bg-white p-6 shadow">
        <h2 class="mb-4 text-xl font-bold">Payment Method</h2>

        <div class="rounded bg-gray-50 p-4">
          <div class="flex items-center gap-3">
            <div class="text-3xl">💳</div>
            <div>
              <p class="font-semibold capitalize">{{ card.card_brand }} •••• {{ card.card_last_four }}</p>
              <p class="text-sm text-gray-600">Expires {{ card.card_exp_month }}/{{ card.card_exp_year }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Card Payment Dialog -->
    <CardPaymentDialog :open="cardDialogOpen" :stripe-key="stripeKey" @close="cardDialogOpen = false" />

    <!-- QR Payment Dialog -->
    <QrPaymentDialog :open="qrDialogOpen" @close="qrDialogOpen = false" />
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import CardPaymentDialog from '@/Components/plan/CardPaymentDialog.vue'
import QrPaymentDialog from '@/Components/plan/QrPaymentDialog.vue'

const props = defineProps({
  subscription: Object,
  usage: Object,
  plans: Object,
  card: Object,
  payments: Array,
  stripeKey: String,
})

const cardDialogOpen = ref(false)
const qrDialogOpen = ref(false)

const currentPlanConfig = computed(() => props.plans?.[props.subscription?.plan])

const periodLabel = computed(() => {
  if (props.subscription?.plan === 'free') return 'week'
  return 'month'
})

const documentUsagePercent = computed(() => {
  if (!props.usage?.documentsLimit) return 0
  return Math.min(100, Math.round((props.usage.documentsUsed / props.usage.documentsLimit) * 100))
})

const memberUsagePercent = computed(() => {
  if (!props.usage?.membersLimit) return 0
  return Math.min(100, Math.round((props.usage.membersCount / props.usage.membersLimit) * 100))
})

const storageUsagePercent = computed(() => {
  if (!props.usage?.storageBytesLimit) return 0
  return Math.min(100, Math.round((props.usage.storageBytesUsed / props.usage.storageBytesLimit) * 100))
})

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

const formatBytes = (bytes) => {
  if (!bytes) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i]
}

const formatUSD = (cents) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(cents / 100)
}

const formatCurrency = (value) => {
  return new Intl.NumberFormat('id-ID').format(value)
}

const contactSales = () => {
  window.location.href = 'mailto:sales@example.com?subject=Enterprise Plan Inquiry'
}

const downgrade = async () => {
  if (!confirm('Are you sure you want to downgrade to the Free plan? This will cancel your subscription.')) {
    return
  }

  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
    const response = await fetch('/plan/downgrade', {
      method: 'POST',
      headers: {
        'X-CSRF-Token': csrfToken,
      },
    })

    if (response.ok) {
      window.location.reload()
    }
  } catch (err) {
    alert('Failed to downgrade: ' + err.message)
  }
}
</script>
