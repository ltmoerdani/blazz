# Campaign "Skip Scheduling & Send Immediately" - Implementation Fix

## 📋 Overview
Fix untuk memastikan campaign dengan opsi "Skip scheduling & send immediately" berjalan dengan benar menggunakan Laravel Queue Worker.

## ✅ Issues Fixed

### 1. Job Dispatch ke Queue yang Salah
**Problem**: Job `SendCampaignJob` di-dispatch ke queue `default`, tapi queue worker hanya listening ke `whatsapp-campaign`

**Solution**: 
- Tambah explicit `onQueue('whatsapp-campaign')` saat dispatch job
- Update `CampaignService::createHybridCampaign()`

```php
SendCampaignJob::dispatch($campaign->id)
    ->onQueue('whatsapp-campaign');
```

### 2. buttons_data Type Error
**Problem**: `buttons_data` disimpan sebagai JSON string di database tapi tidak di-decode, menyebabkan error:
```
foreach() argument must be of type array|object, string given
```

**Solution**: 
- Update `Campaign::getResolvedMessageContent()` untuk handle JSON string
- Decode `buttons_data` menjadi array sebelum digunakan

```php
$buttonsData = $this->buttons_data;
if (is_string($buttonsData)) {
    $buttonsData = json_decode($buttonsData, true) ?? [];
} elseif (!is_array($buttonsData)) {
    $buttonsData = [];
}
```

### 3. Logging untuk Debugging
**Problem**: Sulit debug campaign processing tanpa log yang detail

**Solution**:
- Tambah comprehensive logging di `SendCampaignJob`
- Log di setiap step: job start, campaign resolved, session selected, processing status
- Tambah logging di `CampaignService` untuk track dispatch

## 🔄 Flow Campaign dengan "Skip Schedule"

```
User creates campaign dengan skip_schedule=true
    ↓
CampaignService::createHybridCampaign()
    ↓
Campaign::create() → Status: 'scheduled'
    ↓
SendCampaignJob::dispatch() → Queue: 'whatsapp-campaign'
    ↓
Queue Worker picks up job
    ↓
SendCampaignJob::handle()
    ↓
processSingleCampaign()
    ↓
processCampaign()
    ├─ Select WhatsApp Account
    ├─ Create Campaign Logs (status: scheduled → ongoing)
    └─ sendOngoingCampaignMessages()
        ↓
        sendTemplateMessage() / sendDirectMessage()
            ↓
            Message sent via WhatsApp Web.js
            ↓
            Campaign Log updated (status: success/failed)
            ↓
            Campaign completed
```

## 🚀 How to Use

### 1. Start Queue Worker
```bash
cd /Applications/MAMP/htdocs/blazz

# Start queue worker
nohup php artisan queue:work \
  --queue=whatsapp-urgent,whatsapp-high,whatsapp-normal,whatsapp-campaign \
  --tries=3 \
  --timeout=300 \
  > storage/logs/queue-worker.log 2>&1 &
```

### 2. Create Campaign via UI
1. Navigate to Campaigns → Create Campaign
2. Fill in campaign details:
   - Campaign Type: Direct Message atau Template-based
   - Message content
   - Select contacts
3. **Check** "Skip scheduling & send immediately"
4. Click "Create Campaign"

### 3. Monitor Campaign

**Via Database:**
```sql
-- Check campaign status
SELECT id, name, status, messages_sent, messages_failed 
FROM campaigns 
WHERE id = <campaign_id>;

-- Check campaign logs
SELECT id, status, chat_id, created_at 
FROM campaign_logs 
WHERE campaign_id = <campaign_id>;
```

**Via Logs:**
```bash
# Monitor Laravel log
tail -f storage/logs/laravel.log | grep "campaign_id"

# Monitor queue worker
tail -f storage/logs/queue-worker.log
```

## 📊 Test Results

### Test Campaign
- Campaign ID: 7
- UUID: de4e8f14-6b15-4461-a6a0-50758434bb36
- Status: ✅ completed
- Message Status: ✅ success (sent)
- Contact: Laksmana (+62816108641)
- Response Time: < 1 second

### Message Metadata
```json
{
  "success": true,
  "message_id": "true_62816108641@c.us_3EB0A4A4CE098020929E92",
  "timestamp": 1763564101,
  "message_status": "sent",
  "contact_name": "Laksmana",
  "body": "This is a test message from campaign testing script. Timestamp: 2025-11-19 14:55:01"
}
```

## 🔍 Troubleshooting

### Campaign Not Processing
1. Check queue worker is running:
   ```bash
   ps aux | grep "queue:work"
   ```

2. Check jobs in queue:
   ```sql
   SELECT * FROM jobs WHERE queue = 'whatsapp-campaign';
   ```

3. Restart queue worker:
   ```bash
   php artisan queue:restart
   ```

### Messages Not Sending
1. Check WhatsApp account is connected:
   ```sql
   SELECT id, phone_number, status, provider_type 
   FROM whatsapp_accounts 
   WHERE is_active = 1 AND status = 'connected';
   ```

2. Check campaign logs for errors:
   ```sql
   SELECT id, status, metadata 
   FROM campaign_logs 
   WHERE campaign_id = <id> AND status = 'failed';
   ```

3. Check Laravel logs:
   ```bash
   tail -100 storage/logs/laravel.log | grep "error\|ERROR"
   ```

## ✨ Key Features

### Real-time Campaign Processing
- ✅ Immediate job dispatch when "skip_schedule" is enabled
- ✅ Queue worker processes jobs asynchronously
- ✅ No cronjob needed for immediate campaigns

### Provider Selection
- ✅ Auto-select best WhatsApp account based on health score
- ✅ Support WebJS and Meta API providers
- ✅ Fallback mechanism if primary account fails

### Message Types
- ✅ Direct messages (custom text)
- ✅ Template-based messages
- ✅ Support header, body, footer, buttons
- ✅ Variable replacement ({{first_name}}, {{phone}}, etc.)

### Error Handling
- ✅ Comprehensive error logging
- ✅ Campaign status tracking (scheduled → ongoing → completed/failed)
- ✅ Individual message status tracking per contact

## 📝 Files Modified

1. `/app/Services/CampaignService.php`
   - Added explicit queue specification
   - Added comprehensive logging

2. `/app/Jobs/SendCampaignJob.php`
   - Enhanced logging at each processing step
   - Better error tracking with file/line information

3. `/app/Models/Campaign.php`
   - Fixed `getResolvedMessageContent()` to handle JSON strings
   - Proper type checking for `buttons_data`

## 🎯 Next Steps

### Recommended Improvements
1. ✅ Setup cronjob for scheduled campaigns (via Laravel Scheduler)
2. ⚠️ Add retry mechanism for failed messages
3. ⚠️ Implement rate limiting per WhatsApp account
4. ⚠️ Add webhook for message delivery status updates
5. ⚠️ Dashboard for real-time campaign monitoring

### Production Checklist
- [ ] Setup supervisor for queue worker auto-restart
- [ ] Configure proper logging rotation
- [ ] Setup monitoring alerts for failed jobs
- [ ] Test with large contact lists (1000+)
- [ ] Load testing for concurrent campaigns
- [ ] Backup strategy for campaign logs

## 📚 References

- [WhatsApp Web.js Documentation](https://docs.wwebjs.dev/)
- [Laravel Queue Documentation](https://laravel.com/docs/queues)
- [Laravel Supervisor Configuration](https://laravel.com/docs/queues#supervisor-configuration)

---

**Status**: ✅ **WORKING** - Campaign with "Skip scheduling & send immediately" successfully sends messages via queue worker

**Last Updated**: 2025-11-19 14:55:01
**Tested By**: System Test Script
**Environment**: Development (MAMP + MySQL)
