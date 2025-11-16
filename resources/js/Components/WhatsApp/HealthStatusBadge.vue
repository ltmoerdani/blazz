<template>
  <div class="health-status-badge" :class="badgeClasses">
    <div class="badge-content">
      <span class="status-dot"></span>
      <span class="status-text">{{ statusLabel }}</span>
      <span class="health-score">{{ healthScore }}%</span>
    </div>
    
    <div v-if="showTooltip" class="badge-tooltip">
      <div class="tooltip-header">
        <span>Health Score: {{ healthScore }}%</span>
      </div>
      <div v-if="issues && issues.length > 0" class="tooltip-issues">
        <p class="tooltip-title">Issues:</p>
        <ul>
          <li v-for="(issue, index) in issues" :key="index">{{ issue }}</li>
        </ul>
      </div>
      <div v-if="lastCheckAt" class="tooltip-footer">
        Last checked: {{ formatDate(lastCheckAt) }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  healthScore: {
    type: Number,
    required: true,
    default: 0,
  },
  healthStatus: {
    type: String,
    default: 'unknown',
  },
  issues: {
    type: Array,
    default: () => [],
  },
  lastCheckAt: {
    type: String,
    default: null,
  },
  showTooltip: {
    type: Boolean,
    default: true,
  },
  size: {
    type: String,
    default: 'md', // sm, md, lg
  },
})

const badgeClasses = computed(() => {
  return {
    [`badge-${props.healthStatus}`]: true,
    [`badge-${props.size}`]: true,
    'has-issues': props.issues && props.issues.length > 0,
  }
})

const statusLabel = computed(() => {
  const labels = {
    'excellent': 'Excellent',
    'good': 'Good',
    'warning': 'Warning',
    'critical': 'Critical',
    'failed': 'Failed',
    'unknown': 'Unknown',
  }
  return labels[props.healthStatus] || 'Unknown'
})

const formatDate = (dateString) => {
  if (!dateString) return 'Never'
  try {
    const date = new Date(dateString)
    const now = new Date()
    const diffMs = now - date
    const diffMins = Math.floor(diffMs / 60000)
    
    if (diffMins < 1) return 'just now'
    if (diffMins < 60) return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`
    
    const diffHours = Math.floor(diffMins / 60)
    if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`
    
    const diffDays = Math.floor(diffHours / 24)
    if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`
    
    return date.toLocaleDateString()
  } catch (e) {
    return dateString
  }
}
</script>

<style scoped>
.health-status-badge {
  position: relative;
  display: inline-flex;
  align-items: center;
  padding: 0.375rem 0.75rem;
  border-radius: 0.5rem;
  font-size: 0.875rem;
  font-weight: 500;
  transition: all 0.2s ease;
  cursor: help;
}

.health-status-badge:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.badge-content {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.status-dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: 50%;
  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}

.status-text {
  font-weight: 600;
}

.health-score {
  font-size: 0.75rem;
  opacity: 0.9;
  font-weight: 600;
}

/* Size variants */
.badge-sm {
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
}

.badge-sm .status-dot {
  width: 0.375rem;
  height: 0.375rem;
}

.badge-lg {
  padding: 0.5rem 1rem;
  font-size: 1rem;
}

.badge-lg .status-dot {
  width: 0.625rem;
  height: 0.625rem;
}

/* Status color variants */
.badge-excellent {
  background-color: rgb(220 252 231);
  color: rgb(22 101 52);
}

.badge-excellent .status-dot {
  background-color: rgb(34 197 94);
}

.badge-good {
  background-color: rgb(224 242 254);
  color: rgb(7 89 133);
}

.badge-good .status-dot {
  background-color: rgb(59 130 246);
}

.badge-warning {
  background-color: rgb(254 249 195);
  color: rgb(113 63 18);
}

.badge-warning .status-dot {
  background-color: rgb(234 179 8);
}

.badge-critical {
  background-color: rgb(255 237 213);
  color: rgb(154 52 18);
}

.badge-critical .status-dot {
  background-color: rgb(249 115 22);
}

.badge-failed {
  background-color: rgb(254 226 226);
  color: rgb(153 27 27);
}

.badge-failed .status-dot {
  background-color: rgb(239 68 68);
}

.badge-unknown {
  background-color: rgb(243 244 246);
  color: rgb(75 85 99);
}

.badge-unknown .status-dot {
  background-color: rgb(156 163 175);
}

/* Tooltip */
.badge-tooltip {
  position: absolute;
  bottom: calc(100% + 0.5rem);
  left: 50%;
  transform: translateX(-50%);
  width: max-content;
  max-width: 20rem;
  padding: 0.75rem;
  background: white;
  border: 1px solid rgb(229 231 235);
  border-radius: 0.5rem;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.2s ease;
  z-index: 50;
}

.health-status-badge:hover .badge-tooltip {
  opacity: 1;
  pointer-events: auto;
}

.badge-tooltip::after {
  content: '';
  position: absolute;
  top: 100%;
  left: 50%;
  transform: translateX(-50%);
  width: 0;
  height: 0;
  border-left: 0.5rem solid transparent;
  border-right: 0.5rem solid transparent;
  border-top: 0.5rem solid white;
}

.tooltip-header {
  font-weight: 600;
  font-size: 0.875rem;
  margin-bottom: 0.5rem;
  color: rgb(17 24 39);
}

.tooltip-issues {
  margin-top: 0.5rem;
  padding-top: 0.5rem;
  border-top: 1px solid rgb(229 231 235);
}

.tooltip-title {
  font-size: 0.75rem;
  font-weight: 600;
  color: rgb(107 114 128);
  margin-bottom: 0.25rem;
}

.tooltip-issues ul {
  list-style: none;
  padding: 0;
  margin: 0;
}

.tooltip-issues li {
  font-size: 0.75rem;
  color: rgb(75 85 99);
  padding: 0.25rem 0;
  display: flex;
  align-items: center;
  gap: 0.375rem;
}

.tooltip-issues li::before {
  content: '•';
  color: rgb(239 68 68);
  font-weight: bold;
}

.tooltip-footer {
  margin-top: 0.5rem;
  padding-top: 0.5rem;
  border-top: 1px solid rgb(229 231 235);
  font-size: 0.75rem;
  color: rgb(107 114 128);
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
  .badge-tooltip {
    background: rgb(31 41 55);
    border-color: rgb(55 65 81);
    color: rgb(229 231 235);
  }
  
  .badge-tooltip::after {
    border-top-color: rgb(31 41 55);
  }
  
  .tooltip-header {
    color: rgb(229 231 235);
  }
  
  .tooltip-issues {
    border-top-color: rgb(55 65 81);
  }
  
  .tooltip-issues li {
    color: rgb(209 213 219);
  }
  
  .tooltip-footer {
    border-top-color: rgb(55 65 81);
    color: rgb(156 163 175);
  }
}
</style>
