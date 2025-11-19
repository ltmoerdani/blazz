# Fix: Campaign Direct Message - Null Template Error

**Date:** 2025-11-20  
**Status:** ✅ Fixed  
**Priority:** High  
**Type:** Bug Fix

## 🐛 Problem

Error saat membuka halaman detail campaign dengan tipe **Direct Message** yang sudah dijadwalkan:

```
TypeError: Cannot read properties of null (reading 'name')
    at Index-9c246ffe.js:1:5668
```

### Root Cause

1. Campaign dengan tipe `direct` tidak memiliki `template` (null)
2. Frontend mencoba akses `props.campaign?.template?.name` tanpa cek apakah campaign menggunakan template
3. Komponen `WhatsappTemplate.vue` tidak bisa handle format direct message

## ✅ Solution

### 1. Update Campaign View Component

**File:** `resources/js/Pages/User/Campaign/View.vue`

#### Changes:

**A. Campaign Details Section**

Sebelum:
```vue
<div class="text-sm bg-slate-100 p-3 rounded-lg">
    <h3>{{ $t('Template') }}</h3>
    <p>{{ props.campaign?.template?.name }}</p>
</div>
```

Sesudah:
```vue
<div class="text-sm bg-slate-100 p-3 rounded-lg">
    <h3>{{ $t('Campaign Type') }}</h3>
    <p>{{ props.campaign?.campaign_type === 'direct' ? $t('Direct Message') : $t('Template-based') }}</p>
</div>
<div v-if="props.campaign?.template" class="text-sm bg-slate-100 p-3 rounded-lg">
    <h3>{{ $t('Template') }}</h3>
    <p>{{ props.campaign?.template?.name }}</p>
</div>
```

**B. Message Preview Section**

Sebelum:
```vue
<div class="w-full rounded-lg p-5 mt-5 border chat-bg">
    <WhatsappTemplate :parameters="JSON.parse(props.campaign.metadata)" :placeholder="false" :visible="true"/>
</div>
```

Sesudah:
```vue
<div class="w-full rounded-lg p-5 mt-5 border chat-bg">
    <!-- Direct Message Preview -->
    <div v-if="props.campaign?.campaign_type === 'direct'" class="mr-auto rounded-lg rounded-tl-none my-1 p-1 text-sm bg-white flex flex-col relative speech-bubble-left w-[25em]">
        <div v-if="props.campaign.header_type && props.campaign.header_type !== 'text'" class="mb-4 bg-[#ccd0d5] flex justify-center py-8 rounded">
            <img v-if="props.campaign.header_type === 'image'" :src="'/images/image-placeholder.png'">
            <img v-if="props.campaign.header_type === 'video'" :src="'/images/video-placeholder.png'">
            <img v-if="props.campaign.header_type === 'document'" :src="'/images/document-placeholder.png'">
        </div>
        <h2 v-else-if="props.campaign.header_text" class="text-gray-700 text-sm mb-1 px-2 normal-case whitespace-pre-wrap">{{ props.campaign.header_text }}</h2>
        <p class="px-2 normal-case whitespace-pre-wrap">{{ props.campaign.body_text }}</p>
        <div class="text-[#8c8c8c] mt-1 px-2">
            <span class="text-[13px]">{{ props.campaign.footer_text }}</span>
            <span class="text-right text-xs leading-none float-right" :class="props.campaign.footer_text ? 'mt-2' : ''">9:15</span>
        </div>
    </div>
    
    <!-- Template-based Preview -->
    <WhatsappTemplate v-else :parameters="JSON.parse(props.campaign.metadata)" :placeholder="false" :visible="true"/>
</div>
```

## 📊 Impact Analysis

### Files Modified
1. `resources/js/Pages/User/Campaign/View.vue` - Handle null template & add direct message preview

### Breaking Changes
- **None** - Backward compatible dengan campaign template yang sudah ada

### Benefits
✅ Campaign direct message bisa dibuka tanpa error  
✅ Preview message sesuai dengan tipe campaign (direct vs template)  
✅ UI lebih informatif dengan menampilkan tipe campaign  
✅ Template name hanya muncul untuk campaign berbasis template

## 🧪 Testing

### Test Case 1: View Direct Message Campaign
1. Buat campaign dengan type "Direct Message"
2. Isi header, body, footer
3. Schedule campaign
4. Klik campaign di list untuk view detail

**Expected:**
- ✅ Halaman terbuka tanpa error
- ✅ Campaign Type menampilkan "Direct Message"
- ✅ Field "Template" tidak muncul
- ✅ Preview menampilkan direct message format

### Test Case 2: View Template-based Campaign  
1. Buat campaign dengan type "Use Template"
2. Pilih template yang approved
3. Schedule campaign
4. Klik campaign di list untuk view detail

**Expected:**
- ✅ Halaman terbuka tanpa error
- ✅ Campaign Type menampilkan "Template-based"
- ✅ Field "Template" muncul dengan nama template
- ✅ Preview menggunakan WhatsappTemplate component

### Test Case 3: Schedule Direct Message Campaign
1. Buat campaign direct message
2. Set scheduled time
3. Submit campaign
4. Tunggu sampai scheduled time
5. Cek campaign detail

**Expected:**
- ✅ Campaign terkirim sesuai schedule
- ✅ Detail campaign bisa dibuka tanpa error
- ✅ Stats (sent, delivered, read, failed) update

## 📝 Notes

### Why Template Can Be Null?
- Campaign dengan `campaign_type = 'direct'` tidak menggunakan approved template
- Message dibuat custom di form campaign
- Field `template_id` di database adalah NULL

### Direct Message Data Structure
Campaign direct message menyimpan data di column terpisah:
- `header_type` - text/image/video/document
- `header_text` - text header (optional)
- `header_media` - file path untuk media (optional)
- `body_text` - message body (required)
- `footer_text` - footer text (optional)
- `buttons` - JSON array untuk action buttons (optional)

### Template-based Data Structure
Campaign template menyimpan reference ke template:
- `template_id` - FK to templates table
- `metadata` - JSON dengan parameter values

## 🔗 Related

- Hybrid Campaign System Documentation
- Direct Message Campaign Feature
- WhatsApp Template Component

---

**Fixed by:** AI Assistant  
**Tested:** Manual testing required  
**Deploy:** Ready for staging
