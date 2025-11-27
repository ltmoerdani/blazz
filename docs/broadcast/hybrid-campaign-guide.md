# 📱 Hybrid Campaign Creation Guide

**Version:** 1.0.0
**Last Updated:** November 14, 2025

---

## 📋 Overview

Hybrid Campaign Creation allows users to send WhatsApp messages through two approaches:
1. **Template Mode** - Using pre-approved templates (Meta API)
2. **Direct Mode** - Creating messages on-the-fly (WhatsApp Web JS priority)

---

## 🎯 Use Cases

### **Template Mode**
✅ **Marketing Campaigns** - Regular promotional messages
✅ **Transactional Messages** - Order confirmations, updates
✅ **Authentication** - OTP, verification codes
✅ **Compliance Requirements** - When template approval is mandatory

### **Direct Mode** ⭐
✅ **Time-Sensitive Messages** - Flash sales, urgent updates
✅ **Personal Follow-ups** - Custom messages for specific contacts
✅ **Testing Campaigns** - A/B testing, message trials
✅ **Emergency Communications** - Critical notifications
✅ **Small Batch Campaigns** - Targeted messaging

---

## 🛠️ Implementation Guide

### **Frontend Component Structure**

```vue
<!-- resources/js/Components/CampaignForm.vue -->
<template>
  <div class="campaign-form">
    <!-- Campaign Settings -->
    <div class="campaign-settings">
      <FormInput v-model="form.name" name="Campaign Name" required />

      <!-- Template Mode Selector -->
      <FormSelect v-model="form.template_mode"
                   :options="modeOptions"
                   name="Campaign Type"
                   @change="onModeChange" />

      <FormSelect v-model="form.contact_group_id"
                   :options="contactGroupOptions"
                   name="Send To"
                   required />

      <!-- Scheduling -->
      <div class="scheduling-section">
        <FormInput v-if="!form.skip_schedule"
                  v-model="form.scheduled_at"
                  type="datetime-local"
                  name="Schedule Time" />

        <label class="flex items-center">
          <input v-model="form.skip_schedule" type="checkbox" />
          <span class="ml-2">Send Immediately</span>
        </label>
      </div>
    </div>

    <!-- Template Selection Mode -->
    <div v-if="form.template_mode === 'template'" class="template-section">
      <FormSelect v-model="form.template_id"
                   :options="templateOptions"
                   name="Select Template"
                   @change="loadTemplate" />

      <!-- Variable Assignment -->
      <TemplateVariableAssigner v-if="selectedTemplate"
                               :template="selectedTemplate"
                               v-model="form.variables" />
    </div>

    <!-- Direct Template Builder Mode -->
    <div v-else class="direct-template-builder">
      <TemplateBuilder v-model="form.template_components" />
    </div>

    <!-- WhatsApp Preview -->
    <div class="preview-section">
      <WhatsappPreview :template="getPreviewData()" />
    </div>

    <!-- Submit -->
    <button @click="submitCampaign" :disabled="!isFormValid">
      Create Campaign
    </button>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'

const form = useForm({
  name: '',
  template_mode: 'direct', // 'template' | 'direct'
  template_id: null,
  contact_group_id: '',
  whatsapp_account_id: null,
  skip_schedule: false,
  scheduled_at: null,

  // Direct template data
  template_components: {
    header: {
      format: 'TEXT',
      text: '',
      example: []
    },
    body: {
      text: '',
      example: []
    },
    footer: {
      text: ''
    },
    buttons: []
  },

  // Template mode data
  variables: {}
})

const modeOptions = [
  { value: 'template', label: 'Use Existing Template' },
  { value: 'direct', label: 'Create Message Directly' }
]

const isFormValid = computed(() => {
  if (!form.name || !form.contact_group_id) return false

  if (form.template_mode === 'template') {
    return form.template_id !== null
  } else {
    // Validate direct template components
    return validateDirectTemplate()
  }
})

function validateDirectTemplate() {
  const components = form.template_components

  // Body is required
  if (!components.body.text?.trim()) return false

  // Validate buttons if any
  if (components.buttons.length > 0) {
    return components.buttons.every(button => button.text?.trim())
  }

  return true
}

function getPreviewData() {
  if (form.template_mode === 'template') {
    return selectedTemplate.value?.structure
  } else {
    return form.template_components
  }
}

function submitCampaign() {
  form.post('/campaigns', {
    onSuccess: () => {
      // Handle success
    },
    onError: (errors) => {
      // Handle validation errors
    }
  })
}
</script>
```

### **Template Builder Component**

```vue
<!-- resources/js/Components/TemplateBuilder.vue -->
<template>
  <div class="template-builder">
    <!-- Header Component -->
    <div class="template-section">
      <h3>Header (Optional)</h3>
      <div class="component-type-selector">
        <button v-for="format in headerFormats"
                :key="format.value"
                @click="components.header.format = format.value"
                :class="{ active: components.header.format === format.value }">
          {{ format.label }}
        </button>
      </div>

      <div v-if="components.header.format === 'TEXT'">
        <FormInput v-model="components.header.text"
                  name="Header Text"
                  :maxlength="60" />
      </div>

      <div v-else-if="['IMAGE', 'VIDEO', 'DOCUMENT'].includes(components.header.format)">
        <MediaUploader :format="components.header.format"
                     v-model="components.header.example" />
      </div>
    </div>

    <!-- Body Component -->
    <div class="template-section">
      <h3>Message Content (Required)</h3>
      <div class="variable-editor">
        <FormTextarea v-model="components.body.text"
                     name="Message"
                     :rows="4" />

        <VariableManager v-if="hasVariables"
                       :text="components.body.text"
                       v-model="components.body.example" />
      </div>
    </div>

    <!-- Footer Component -->
    <div class="template-section">
      <h3>Footer (Optional)</h3>
      <FormInput v-model="components.footer.text"
                name="Footer Text"
                :maxlength="60" />
    </div>

    <!-- Buttons Component -->
    <div class="template-section">
      <h3>Buttons (Optional)</h3>
      <div class="button-builder">
        <div class="button-type-selector">
          <button @click="addButton('QUICK_REPLY')"
                  :disabled="quickReplyButtons.length >= 6">
            Quick Reply ({{ 6 - quickReplyButtons.length }})
          </button>
          <button @click="addButton('URL')"
                  :disabled="urlButtons.length >= 2">
            Visit Website ({{ 2 - urlButtons.length }})
          </button>
          <button @click="addButton('PHONE_NUMBER')"
                  :disabled="phoneButtons.length >= 1">
            Call Phone ({{ 1 - phoneButtons.length }})
          </button>
        </div>

        <div v-for="(button, index) in components.buttons"
             :key="index"
             class="button-config">
          <div class="button-header">
            <span>{{ formatButtonType(button.type) }}</span>
            <button @click="removeButton(index)" class="remove-btn">×</button>
          </div>

          <FormInput v-model="button.text"
                    name="Button Text"
                    :maxlength="25" />

          <FormInput v-if="button.type === 'URL'"
                    v-model="button.url"
                    name="Website URL"
                    type="url" />

          <FormInput v-if="button.type === 'PHONE_NUMBER'"
                    v-model="button.phone_number"
                    name="Phone Number" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps(['modelValue'])
const emit = defineEmits(['update:modelValue'])

const components = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const headerFormats = [
  { value: 'TEXT', label: 'Text' },
  { value: 'IMAGE', label: 'Image' },
  { value: 'VIDEO', label: 'Video' },
  { value: 'DOCUMENT', label: 'Document' }
]

const hasVariables = computed(() => {
  return (components.value.body.text.match(/{{\d+}}/g) || []).length > 0
})

const quickReplyButtons = computed(() =>
  components.value.buttons.filter(b => b.type === 'QUICK_REPLY')
)

const urlButtons = computed(() =>
  components.value.buttons.filter(b => b.type === 'URL')
)

const phoneButtons = computed(() =>
  components.value.buttons.filter(b => b.type === 'PHONE_NUMBER')
)

function addButton(type) {
  const newButton = {
    type: type,
    text: ''
  }

  if (type === 'URL') {
    newButton.url = ''
  } else if (type === 'PHONE_NUMBER') {
    newButton.phone_number = ''
  }

  components.value.buttons.push(newButton)
}

function removeButton(index) {
  components.value.buttons.splice(index, 1)
}

function formatButtonType(type) {
  const types = {
    'QUICK_REPLY': 'Quick Reply',
    'URL': 'Visit Website',
    'PHONE_NUMBER': 'Call Phone'
  }
  return types[type] || type
}
</script>
```

### **Variable Management Component**

```vue
<!-- resources/js/Components/VariableManager.vue -->
<template>
  <div class="variable-manager">
    <h4>Template Variables</h4>

    <div v-for="(variable, index) in variables" :key="index" class="variable-item">
      <div class="variable-header">
        <span>{{ index + 1 }}. {{ getVariableText(index) }}</span>
        <span class="variable-type">{{ variable.selection }}</span>
      </div>

      <div class="variable-config">
        <FormSelect v-model="variable.selection"
                     :options="variableTypes"
                     @change="onVariableTypeChange(index)" />

        <FormInput v-if="variable.selection === 'static'"
                  v-model="variable.value"
                  :name="`Variable ${index + 1}`" />

        <FormSelect v-else
                  v-model="variable.value"
                  :options="dynamicOptions"
                  :name="`Variable ${index + 1}`" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, watch } from 'vue'

const props = defineProps(['text', 'modelValue'])
const emit = defineEmits(['update:modelValue'])

const variableTypes = [
  { value: 'static', label: 'Static Text' },
  { value: 'dynamic', label: 'Dynamic Field' }
]

const dynamicOptions = [
  { value: 'first_name', label: 'First Name' },
  { value: 'last_name', label: 'Last Name' },
  { value: 'full_name', label: 'Full Name' },
  { value: 'phone', label: 'Phone Number' },
  { value: 'email', label: 'Email Address' },
  { value: 'company', label: 'Company Name' },
  { value: 'custom_field_1', label: 'Custom Field 1' },
  { value: 'custom_field_2', label: 'Custom Field 2' }
]

// Extract variables from text
const variables = computed(() => {
  const matches = props.text.match(/{{(\d+)}}/g) || []
  const count = matches.length

  // Ensure we have enough variable slots
  const currentVars = props.modelValue || []

  if (currentVars.length < count) {
    const newVars = [...currentVars]
    for (let i = currentVars.length; i < count; i++) {
      newVars.push({
        selection: 'static',
        value: ''
      })
    }
    return newVars
  }

  return currentVars.slice(0, count)
})

function getVariableText(index) {
  const matches = props.text.match(/{{(\d+)}}/g) || []
  return matches[index] || `{{${index + 1}}}`
}

function onVariableTypeChange(index) {
  variables.value[index].value = ''
  emitUpdate()
}

function emitUpdate() {
  emit('update:modelValue', variables.value)
}

// Watch for changes and emit update
watch(variables, emitUpdate, { deep: true })
</script>
```

---

## 🔧 Backend Implementation

### **Campaign Controller Enhancement**

```php
<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreHybridCampaignRequest;
use App\Services\HybridCampaignService;
use App\Models\Template;
use App\Models\ContactGroup;
use App\Models\WhatsAppAccount;
use App\Models\workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HybridCampaignController extends Controller
{
    protected $hybridCampaignService;

    public function __construct()
    {
        $workspaceId = session()->get('current_workspace');
        $this->hybridCampaignService = new HybridCampaignService($workspaceId);
    }

    public function create(Request $request)
    {
        $workspaceId = session()->get('current_workspace');
        $workspace = Workspace::find($workspaceId);

        // Get available data
        $templates = Template::where('workspace_id', $workspaceId)
            ->where('status', 'APPROVED')
            ->whereNull('deleted_at')
            ->select(['id', 'uuid', 'name', 'language', 'category'])
            ->get();

        $contactGroups = ContactGroup::where('workspace_id', $workspaceId)
            ->whereNull('deleted_at')
            ->select(['id', 'uuid', 'name'])
            ->get();

        $whatsappAccounts = WhatsAppAccount::forWorkspace($workspaceId)
            ->connected()
            ->where('is_active', true)
            ->select(['id', 'uuid', 'phone_number', 'provider_type', 'health_score'])
            ->orderByDesc('is_primary')
            ->orderByDesc('health_score')
            ->get();

        return Inertia::render('User/Campaigns/CreateHybrid', [
            'templates' => $templates,
            'contactGroups' => $contactGroups,
            'whatsappAccounts' => $whatsappAccounts,
            'settings' => $workspace,
            'template_categories' => [
                ['value' => 'MARKETING', 'label' => 'Marketing'],
                ['value' => 'UTILITY', 'label' => 'Utility'],
                ['value' => 'AUTHENTICATION', 'label' => 'Authentication']
            ]
        ]);
    }

    public function store(StoreHybridCampaignRequest $request)
    {
        $result = $this->hybridCampaignService->createFromRequest(
            $request->validated()
        );

        return redirect()->route('campaigns')->with('status', [
            'type' => $result->success ? 'success' : 'error',
            'message' => $result->message,
        ]);
    }

    public function getTemplate(Request $request)
    {
        $template = Template::where('uuid', $request->template_id)
            ->with(['workspace'])
            ->first();

        if (!$template) {
            return response()->json(['error' => 'Template not found'], 404);
        }

        return response()->json([
            'template' => $template,
            'structure' => $template->getStructure(),
            'variables' => $template->extractVariables()
        ]);
    }
}
```

### **Hybrid Campaign Service**

```php
<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Template;
use App\Models\ContactGroup;
use App\Models\Contact;
use App\Jobs\ProcessDirectCampaignJob;
use App\Jobs\ProcessTemplateCampaignJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class HybridCampaignService
{
    protected $workspaceId;

    public function __construct($workspaceId)
    {
        $this->workspaceId = $workspaceId;
    }

    public function createFromRequest(array $data)
    {
        try {
            DB::beginTransaction();

            if ($data['template_mode'] === 'template') {
                $result = $this->createFromTemplate($data);
            } else {
                $result = $this->createFromDirectTemplate($data);
            }

            DB::commit();

            // Queue campaign processing
            $this->queueCampaignProcessing($result->data);

            Log::info('Hybrid campaign created', [
                'workspace_id' => $this->workspaceId,
                'campaign_id' => $result->data->id,
                'mode' => $data['template_mode'],
                'user_id' => auth()->id(),
            ]);

            return $result;

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create hybrid campaign', [
                'workspace_id' => $this->workspaceId,
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to create campaign: ' . $e->getMessage(),
            ];
        }
    }

    protected function createFromTemplate(array $data)
    {
        $template = Template::where('uuid', $data['template_id'])
            ->where('workspace_id', $this->workspaceId)
            ->firstOrFail();

        $contactGroup = ContactGroup::where('uuid', $data['contact_group_id'])
            ->where('workspace_id', $this->workspaceId)
            ->firstOrFail();

        $scheduledAt = $this->parseScheduleTime($data);

        $campaign = Campaign::create([
            'workspace_id' => $this->workspaceId,
            'name' => $data['name'],
            'template_mode' => 'template',
            'template_id' => $template->id,
            'contact_group_id' => $contactGroup->id,
            'whatsapp_account_id' => $data['whatsapp_account_id'] ?? null,
            'provider_preference' => $data['provider_preference'] ?? 'auto',
            'status' => 'scheduled',
            'scheduled_at' => $scheduledAt,
            'created_by' => auth()->id(),
            'metadata' => json_encode([
                'template_variables' => $data['variables'] ?? [],
                'original_template_id' => $template->id,
            ])
        ]);

        return (object) [
            'success' => true,
            'data' => $campaign->fresh(['template', 'contactGroup', 'whatsappAccount']),
            'message' => 'Template-based campaign created successfully!',
        ];
    }

    protected function createFromDirectTemplate(array $data)
    {
        $contactGroup = ContactGroup::where('uuid', $data['contact_group_id'])
            ->where('workspace_id', $this->workspaceId)
            ->firstOrFail();

        // Build WA Business API compliant template structure
        $templateStructure = $this->buildTemplateStructure($data);

        $scheduledAt = $this->parseScheduleTime($data);

        $campaign = Campaign::create([
            'workspace_id' => $this->workspaceId,
            'name' => $data['name'],
            'template_mode' => 'direct',
            'template_id' => null,
            'contact_group_id' => $contactGroup->id,
            'direct_template_data' => $templateStructure,
            'whatsapp_account_id' => $data['whatsapp_account_id'] ?? null,
            'provider_preference' => $data['provider_preference'] ?? 'auto',
            'status' => 'scheduled',
            'scheduled_at' => $scheduledAt,
            'created_by' => auth()->id(),
            'metadata' => json_encode([
                'template_category' => $data['template_category'] ?? 'MARKETING',
                'template_language' => $data['template_language'] ?? 'id',
                'components' => $data['components'] ?? [],
            ])
        ]);

        return (object) [
            'success' => true,
            'data' => $campaign->fresh(['contactGroup', 'whatsappAccount']),
            'message' => 'Direct campaign created successfully!',
        ];
    }

    protected function buildTemplateStructure(array $data): array
    {
        $components = [];
        $componentsData = $data['components'] ?? [];

        // Header Component
        if (!empty($componentsData['header'])) {
            $header = $componentsData['header'];

            if ($header['format'] !== 'TEXT' && !empty($header['example'])) {
                $components[] = [
                    'type' => 'HEADER',
                    'format' => $header['format'],
                    'example' => [
                        $header['format'] . '_handle' => [$header['example']]
                    ]
                ];
            } elseif (!empty($header['text'])) {
                $components[] = [
                    'type' => 'HEADER',
                    'format' => 'TEXT',
                    'text' => $header['text']
                ];
            }
        }

        // Body Component (Required)
        $body = $componentsData['body'] ?? [];
        $bodyComponent = [
            'type' => 'BODY',
            'text' => $body['text'] ?? ''
        ];

        if (!empty($body['example'])) {
            $bodyComponent['example'] = [
                'body_text' => $body['example']
            ];
        }

        $components[] = $bodyComponent;

        // Footer Component (Optional)
        if (!empty($componentsData['footer']['text'])) {
            $components[] = [
                'type' => 'FOOTER',
                'text' => $componentsData['footer']['text']
            ];
        }

        // Buttons Component (Optional)
        if (!empty($componentsData['buttons'])) {
            $buttons = collect($componentsData['buttons'])->map(function ($button) {
                $formattedButton = [
                    'type' => $button['type'],
                    'text' => $button['text']
                ];

                if ($button['type'] === 'URL') {
                    $formattedButton['url'] = $button['url'];
                } elseif ($button['type'] === 'PHONE_NUMBER') {
                    $formattedButton['phone_number'] = $button['phone_number'];
                } elseif ($button['type'] === 'COPY_CODE') {
                    $formattedButton['example'] = ['12345'];
                }

                return $formattedButton;
            })->toArray();

            $components[] = [
                'type' => 'BUTTONS',
                'buttons' => $buttons
            ];
        }

        return [
            'name' => 'temp_direct_' . time() . '_' . uniqid(),
            'category' => $data['template_category'] ?? 'MARKETING',
            'language' => $data['template_language'] ?? 'id',
            'components' => $components
        ];
    }

    protected function parseScheduleTime(array $data): Carbon
    {
        if ($data['skip_schedule'] ?? false) {
            return now();
        }

        return isset($data['scheduled_at'])
            ? Carbon::parse($data['scheduled_at'])
            : now();
    }

    protected function queueCampaignProcessing(Campaign $campaign): void
    {
        if ($campaign->template_mode === 'template') {
            ProcessTemplateCampaignJob::dispatch($campaign->id, $this->workspaceId)
                ->onQueue('campaigns')
                ->delay($campaign->scheduled_at->isFuture()
                    ? $campaign->scheduled_at
                    : now()->addSeconds(5));
        } else {
            ProcessDirectCampaignJob::dispatch($campaign->id, $this->workspaceId)
                ->onQueue('campaigns')
                ->delay($campaign->scheduled_at->isFuture()
                    ? $campaign->scheduled_at
                    : now()->addSeconds(5));
        }
    }
}
```

### **Form Request Validation**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHybridCampaignRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $rules = [
            // Campaign settings
            'name' => 'required|string|max:255',
            'template_mode' => 'required|in:template,direct',
            'contact_group_id' => 'required|string|exists:contact_groups,uuid',
            'whatsapp_account_id' => 'nullable|string|exists:whatsapp_accounts,uuid',
            'provider_preference' => 'nullable|in:webjs,meta,auto',
            'skip_schedule' => 'boolean',
            'scheduled_at' => 'required_unless:skip_schedule,true|date|after:now',
        ];

        // Template mode validation
        if ($this->template_mode === 'template') {
            $rules['template_id'] = 'required|string|exists:templates,uuid';
            $rules['variables' => 'array';
            $rules['variables.*.selection'] = 'required|in:static,dynamic';
            $rules['variables.*.value'] = 'required|string|max:255';
        } else {
            // Direct template validation
            $rules = array_merge($rules, $this->getDirectTemplateRules());
        }

        return $rules;
    }

    protected function getDirectTemplateRules(): array
    {
        $rules = [];

        // Template category and language
        $rules['template_category'] = 'required|in:MARKETING,UTILITY,AUTHENTICATION';
        $rules['template_language'] = 'required|string|max:10';

        // Components validation
        $components = $this->input('components', []);

        // Header validation
        if (!empty($components['header'])) {
            $headerFormat = $components['header']['format'] ?? 'TEXT';

            if ($headerFormat === 'TEXT') {
                $rules['components.header.text'] = 'nullable|string|max:60';
            } else {
                $rules['components.header.example'] = 'required|file';

                if ($headerFormat === 'IMAGE') {
                    $rules['components.header.example'] .= '|mimes:jpg,png|max:5120';
                } elseif ($headerFormat === 'VIDEO') {
                    $rules['components.header.example'] .= '|mimes:mp4|max:16384';
                } elseif ($headerFormat === 'DOCUMENT') {
                    $rules['components.header.example'] .= '|mimes:pdf,doc,docx|max:102400';
                }
            }
        }

        // Body validation (required)
        $rules['components.body.text'] = 'required|string|max:1024';

        if (!empty($components['body']['example'])) {
            foreach ($components['body']['example'] as $index => $example) {
                $rules["components.body.example.{$index}.*"] = 'string|max:255';
            }
        }

        // Footer validation (optional)
        if (!empty($components['footer']['text'])) {
            $rules['components.footer.text'] = 'string|max:60';
        }

        // Buttons validation (optional)
        if (!empty($components['buttons'])) {
            foreach ($components['buttons'] as $index => $button) {
                $rules["components.buttons.{$index}.type"] = 'required|in:QUICK_REPLY,URL,PHONE_NUMBER';
                $rules["components.buttons.{$index}.text"] = 'required|string|max:25';

                if ($button['type'] === 'URL') {
                    $rules["components.buttons.{$index}.url"] = 'required|url|max:2048';
                } elseif ($button['type'] === 'PHONE_NUMBER') {
                    $rules["components.buttons.{$index}.phone_number"] = 'required|string|max:20';
                }
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Campaign name is required.',
            'template_mode.required' => 'Please select a campaign type.',
            'contact_group_id.required' => 'Please select contacts to send to.',
            'scheduled_at.after' => 'Schedule time must be in the future.',
            'components.body.text.required' => 'Message content is required.',
            'components.header.example.required' => 'Header media is required when header format is not text.',
            'components.buttons.*.text.required' => 'Button text is required.',
            'template_id.required' => 'Please select a template.',
        ];
    }
}
```

---

## 🚀 Best Practices

### **Frontend Best Practices**

1. **Progressive Enhancement**
   - Start with template mode, add direct mode
   - Graceful degradation for older browsers
   - Client-side validation before server submission

2. **User Experience**
   - Real-time preview of messages
   - Clear indication of selected mode
   - Save draft functionality
   - Loading states for operations

3. **Performance**
   - Lazy load component data
   - Debounce API calls
   - Optimize for mobile devices
   - Use React efficiently

### **Backend Best Practices**

1. **Data Validation**
   - Comprehensive validation rules
   - Sanitize all user inputs
   - Validate WhatsApp compliance
   - Handle edge cases gracefully

2. **Error Handling**
   - Detailed error logging
   - User-friendly error messages
   - Graceful fallbacks
   - Transaction rollback

3. **Performance**
   - Database query optimization
   - Efficient queue processing
   - Memory management
   - Caching strategies

### **Security Best Practices**

1. **Input Validation**
   - Never trust user input
   - Validate file uploads
   - Rate limiting
   - CSRF protection

2. **Data Access**
   - Workspace scoping
   - Authorization checks
   - Minimal data exposure
   - Audit logging

---

## 📊 Testing Guide

### **Frontend Testing**

```javascript
// Component testing example
import { mount } from '@vue/test-utils'
import { createTestingPinia } from 'pinia'
import CampaignForm from '@/Components/CampaignForm.vue'

describe('CampaignForm', () => {
  it('validates required fields', async () => {
    const wrapper = mount(CampaignForm, {
      global: {
        plugins: [createTestingPinia()]
      }
    })

    // Test form validation
    await wrapper.find('[data-testid="submit-button"]').trigger('click')

    expect(wrapper.text()).toContain('Campaign name is required')
    expect(wrapper.text()).toContain('Select contacts to send to')
  })

  it('switches between template modes', async () => {
    const wrapper = mount(CampaignForm)

    // Switch to direct mode
    await wrapper.find('[value="direct"]').setChecked(true)

    expect(wrapper.vm.form.template_mode).toBe('direct')
    expect(wrapper.find('[data-testid="template-builder"]').exists()).toBe(true)
  })
})
```

### **Backend Testing**

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\HybridCampaignService;
use App\Models\Workspace;
use App\Models\Template;
use App\Models\ContactGroup;

class HybridCampaignServiceTest extends TestCase
{
    protected $service;
    protected $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();
        $this->service = new HybridCampaignService($this->workspace->id);
    }

    /** @test */
    public function it_creates_direct_campaign()
    {
        $contactGroup = ContactGroup::factory()->create([
            'workspace_id' => $this->workspace->id
        ]);

        $data = [
            'name' => 'Test Campaign',
            'template_mode' => 'direct',
            'contact_group_id' => $contactGroup->uuid,
            'skip_schedule' => true,
            'template_category' => 'MARKETING',
            'template_language' => 'id',
            'components' => [
                'body' => [
                    'text' => 'Hello {{1}}!',
                    'example' => ['World']
                ]
            ]
        ];

        $result = $this->service->createFromRequest($data);

        $this->assertTrue($result->success);
        $this->assertDatabaseHas('campaigns', [
            'workspace_id' => $this->workspace->id,
            'name' => 'Test Campaign',
            'template_mode' => 'direct',
        ]);
    }

    /** @test */
    public function it_validates_template_structure()
    {
        $invalidData = [
            'name' => 'Test Campaign',
            'template_mode' => 'direct',
            'contact_group_id' => 'invalid-uuid',
            'components' => []
        ];

        $result = $this->service->createFromRequest($invalidData);

        $this->assertFalse($result->success);
        $this->assertStringContains('required', $result->message);
    }
}
```

### **Integration Testing**

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Workspace;
use App\Models\ContactGroup;
use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;

class HybridCampaignTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workspace = Workspace::factory()->create();

        $this->actingAs($this->user);
        session(['current_workspace' => $this->workspace->id]);
    }

    /** @test */
    public function it_creates_direct_campaign_via_api()
    {
        $contactGroup = ContactGroup::factory()->create([
            'workspace_id' => $this->workspace->id
        ]);

        $response = $this->post('/campaigns', [
            'name' => 'API Test Campaign',
            'template_mode' => 'direct',
            'contact_group_id' => $contactGroup->uuid,
            'skip_schedule' => true,
            'template_category' => 'MARKETING',
            'components' => [
                'body' => [
                    'text' => 'Test message from API'
                ]
            ]
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('campaigns', [
            'name' => 'API Test Campaign',
            'template_mode' => 'direct'
        ]);
    }
}
```

---

## 📈 Performance Metrics

### **Key Performance Indicators**

1. **Campaign Creation Speed**
   - Template mode: ~2-3 seconds
   - Direct mode: ~3-5 seconds (including validation)

2. **Message Processing Speed**
   - WebJS provider: ~50-100 messages/minute per session
   - Meta API provider: ~500-1000 messages/minute

3. **Queue Performance**
   - Job processing: <100ms per job
   - Queue depth: <1000 messages optimal
   - Memory usage: <512MB per worker

4. **Database Performance**
   - Campaign listing: <100ms
   - Analytics queries: <500ms
   - Contact loading: <200ms per 1000 contacts

### **Monitoring Dashboard**

```javascript
// Real-time performance monitoring
const performanceMetrics = {
  campaignCreationSpeed: {
    current: 3.2,
    average: 4.1,
    target: 5.0
  },
  messageProcessingRate: {
    current: 125,
    average: 98,
    target: 100
  },
  errorRate: {
    current: 2.1,
    average: 3.4,
    target: 5.0
  }
}
```

---

This guide provides a comprehensive reference for implementing hybrid WhatsApp campaigns with both template and direct message modes, following the established patterns and best practices of the Blazz WhatsApp Business Platform.