<template>
  <div class="space-y-3">
    <!-- Label -->
    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
      Kecepatan Pengiriman
      <span class="text-gray-400 font-normal ml-1">(Speed Tier)</span>
    </label>
    
    <!-- Tier Options -->
    <div class="space-y-2">
      <div 
        v-for="tier in tiers" 
        :key="tier.value"
        @click="selectTier(tier.value)"
        :class="[
          'p-4 rounded-lg border-2 cursor-pointer transition-all duration-200',
          selectedTier === tier.value 
            ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20 ring-2 ring-primary-200 dark:ring-primary-800' 
            : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600',
        ]"
      >
        <!-- Tier Header -->
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <!-- Emoji -->
            <span class="text-2xl">{{ tier.emoji }}</span>
            
            <!-- Label & Description -->
            <div>
              <div class="font-medium text-gray-900 dark:text-white flex items-center gap-2">
                {{ tier.label }}
                
                <!-- Recommended Badge -->
                <span 
                  v-if="tier.is_default" 
                  class="text-xs px-2 py-0.5 bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 rounded-full"
                >
                  ⭐ Recommended
                </span>
                
                <!-- Selected Check -->
                <svg 
                  v-if="selectedTier === tier.value"
                  class="w-5 h-5 text-primary-500" 
                  fill="currentColor" 
                  viewBox="0 0 20 20"
                >
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
              </div>
              
              <div class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                {{ tier.description }}
              </div>
            </div>
          </div>
          
          <!-- Interval Display -->
          <div class="text-right">
            <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
              {{ tier.interval }}
            </div>
            <div class="text-xs text-gray-400">
              per message
            </div>
          </div>
        </div>
        
        <!-- Risk & Stats Row -->
        <div class="mt-3 flex items-center justify-between">
          <!-- Risk Badge -->
          <span 
            :class="[
              'inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full',
              getRiskBadgeClass(tier.risk_level)
            ]"
          >
            <span class="mr-1">{{ getRiskIcon(tier.risk_level) }}</span>
            Risk: {{ formatRiskLevel(tier.risk_level) }}
          </span>
          
          <!-- Estimated Speed -->
          <span class="text-xs text-gray-400">
            ~{{ estimateMessagesPerHour(tier) }} msg/hour
          </span>
        </div>
        
        <!-- Warning for Aggressive Tier -->
        <div 
          v-if="tier.show_warning && selectedTier === tier.value" 
          class="mt-3 p-3 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800"
        >
          <div class="flex items-start gap-2">
            <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            <div>
              <p class="text-sm font-medium text-red-800 dark:text-red-300">
                Warning: High Ban Risk
              </p>
              <p class="text-xs text-red-600 dark:text-red-400 mt-1">
                Kecepatan ini dapat menyebabkan akun WhatsApp ter-ban. 
                Gunakan hanya jika Anda memahami risikonya.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Help Text -->
    <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">
      💡 Tier dengan interval lebih lama lebih aman tetapi lebih lambat.
      Pilih sesuai kebutuhan dan toleransi risiko Anda.
    </p>
  </div>
</template>

<script setup>
/**
 * Speed Tier Selector Component
 * 
 * User-selectable speed tier for campaign message sending.
 * Each tier balances speed vs safety (ban risk).
 * 
 * @see docs/broadcast/relay/02-anti-ban-system-design.md
 * @see docs/broadcast/relay/03-implementation-guide.md
 */
import { computed } from 'vue'

// Props
const props = defineProps({
  modelValue: { 
    type: Number, 
    default: 2 
  },
  tiers: { 
    type: Array, 
    required: true 
  },
})

// Emits
const emit = defineEmits(['update:modelValue'])

// Computed
const selectedTier = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
})

// Methods
const selectTier = (tierValue) => {
  selectedTier.value = tierValue
}

const getRiskBadgeClass = (riskLevel) => {
  const classes = {
    'very_low': 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    'low': 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    'medium': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    'high': 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
    'very_high': 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
  }
  return classes[riskLevel] || classes['medium']
}

const getRiskIcon = (riskLevel) => {
  const icons = {
    'very_low': '🟢',
    'low': '🟢',
    'medium': '🟡',
    'high': '🟠',
    'very_high': '🔴',
  }
  return icons[riskLevel] || '🟡'
}

const formatRiskLevel = (riskLevel) => {
  return riskLevel.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())
}

const estimateMessagesPerHour = (tier) => {
  // Calculate based on average interval
  const avgInterval = (tier.interval_min + tier.interval_max) / 2
  const messagesPerHour = Math.floor(3600 / avgInterval)
  return messagesPerHour
}
</script>
