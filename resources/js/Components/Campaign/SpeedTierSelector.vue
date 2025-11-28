<template>
  <div class="space-y-2">
    <!-- Label -->
    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
      Kecepatan Pengiriman
      <span class="text-gray-400 font-normal ml-1">(Speed Tier)</span>
    </label>
    
    <!-- Custom Dropdown -->
    <div class="relative" ref="dropdownRef">
      <!-- Dropdown Trigger (Selected Item Display) -->
      <button
        type="button"
        @click="toggleDropdown"
        :class="[
          'w-full p-4 rounded-lg border-2 cursor-pointer transition-all duration-200 text-left',
          isOpen 
            ? 'border-primary-500 ring-2 ring-primary-200 dark:ring-primary-800' 
            : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600',
          'bg-white dark:bg-gray-800'
        ]"
      >
        <div v-if="currentTier" class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="text-2xl">{{ currentTier.emoji }}</span>
            <div>
              <div class="font-medium text-gray-900 dark:text-white flex items-center gap-2">
                {{ currentTier.label }}
                <span 
                  v-if="currentTier.is_default" 
                  class="text-xs px-2 py-0.5 bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 rounded-full"
                >
                  ⭐ Recommended
                </span>
              </div>
              <div class="text-sm text-gray-500 dark:text-gray-400">
                {{ currentTier.interval }} per message • ~{{ estimateMessagesPerHour(currentTier) }} msg/hour
              </div>
            </div>
          </div>
          
          <div class="flex items-center gap-3">
            <!-- Risk Badge -->
            <span 
              :class="[
                'inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full',
                getRiskBadgeClass(currentTier.risk_level)
              ]"
            >
              {{ getRiskIcon(currentTier.risk_level) }} {{ formatRiskLevel(currentTier.risk_level) }}
            </span>
            
            <!-- Chevron -->
            <svg 
              :class="['w-5 h-5 text-gray-400 transition-transform duration-200', isOpen ? 'rotate-180' : '']" 
              fill="none" 
              stroke="currentColor" 
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </div>
        </div>
        
        <!-- Placeholder when no tier selected -->
        <div v-else class="flex items-center justify-between text-gray-400">
          <span>Pilih kecepatan pengiriman...</span>
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </div>
      </button>
      
      <!-- Dropdown Options -->
      <Transition
        enter-active-class="transition ease-out duration-100"
        enter-from-class="transform opacity-0 scale-95"
        enter-to-class="transform opacity-100 scale-100"
        leave-active-class="transition ease-in duration-75"
        leave-from-class="transform opacity-100 scale-100"
        leave-to-class="transform opacity-0 scale-95"
      >
        <div 
          v-if="isOpen" 
          class="absolute z-50 mt-2 w-full bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-lg max-h-[400px] overflow-y-auto"
        >
          <div class="p-2 space-y-1">
            <div 
              v-for="tier in tiers" 
              :key="tier.value"
              @click="selectTier(tier.value)"
              :class="[
                'p-3 rounded-lg cursor-pointer transition-all duration-150',
                selectedTier === tier.value 
                  ? 'bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-700' 
                  : 'hover:bg-gray-50 dark:hover:bg-gray-700/50 border border-transparent',
              ]"
            >
              <!-- Tier Content -->
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <!-- Emoji -->
                  <span class="text-xl">{{ tier.emoji }}</span>
                  
                  <!-- Label & Description -->
                  <div>
                    <div class="font-medium text-gray-900 dark:text-white flex items-center gap-2">
                      {{ tier.label }}
                      <span 
                        v-if="tier.is_default" 
                        class="text-xs px-1.5 py-0.5 bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 rounded-full"
                      >
                        ⭐ Recommended
                      </span>
                      <svg 
                        v-if="selectedTier === tier.value"
                        class="w-4 h-4 text-primary-500" 
                        fill="currentColor" 
                        viewBox="0 0 20 20"
                      >
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                      </svg>
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                      {{ tier.description }}
                    </div>
                  </div>
                </div>
                
                <!-- Right Side Info -->
                <div class="text-right flex-shrink-0 ml-4">
                  <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ tier.interval }}
                  </div>
                  <div class="text-xs text-gray-400">
                    ~{{ estimateMessagesPerHour(tier) }} msg/hour
                  </div>
                </div>
              </div>
              
              <!-- Risk Badge Row -->
              <div class="mt-2 flex items-center justify-between">
                <span 
                  :class="[
                    'inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full',
                    getRiskBadgeClass(tier.risk_level)
                  ]"
                >
                  {{ getRiskIcon(tier.risk_level) }} Risk: {{ formatRiskLevel(tier.risk_level) }}
                </span>
              </div>
              
              <!-- Warning for Aggressive Tier -->
              <div 
                v-if="tier.show_warning" 
                class="mt-2 p-2 bg-red-50 dark:bg-red-900/20 rounded border border-red-200 dark:border-red-800"
              >
                <div class="flex items-start gap-2">
                  <span class="text-red-500">⚠️</span>
                  <p class="text-xs text-red-600 dark:text-red-400">
                    Kecepatan ini dapat menyebabkan akun WhatsApp ter-ban.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </div>
    
    <!-- Selected Tier Warning (shown below dropdown when aggressive is selected) -->
    <div 
      v-if="currentTier?.show_warning" 
      class="p-3 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800"
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
    
    <!-- Help Text -->
    <p class="text-xs text-gray-400 dark:text-gray-500">
      💡 Tier dengan interval lebih lama lebih aman tetapi lebih lambat.
    </p>
  </div>
</template>

<script setup>
/**
 * Speed Tier Selector Component (Dropdown Version)
 * 
 * User-selectable speed tier for campaign message sending.
 * Dropdown with rich card-like options for better UX.
 * 
 * @see docs/broadcast/relay/02-anti-ban-system-design.md
 * @see docs/broadcast/relay/03-implementation-guide.md
 */
import { ref, computed, onMounted, onUnmounted } from 'vue'

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

// Refs
const dropdownRef = ref(null)
const isOpen = ref(false)

// Computed
const selectedTier = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
})

const currentTier = computed(() => {
  return props.tiers.find(t => t.value === selectedTier.value) || null
})

// Methods
const toggleDropdown = () => {
  isOpen.value = !isOpen.value
}

const selectTier = (tierValue) => {
  selectedTier.value = tierValue
  isOpen.value = false
}

const closeDropdown = (event) => {
  if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
    isOpen.value = false
  }
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
  const avgInterval = (tier.interval_min + tier.interval_max) / 2
  return Math.floor(3600 / avgInterval)
}

// Lifecycle
onMounted(() => {
  document.addEventListener('click', closeDropdown)
})

onUnmounted(() => {
  document.removeEventListener('click', closeDropdown)
})
</script>
