# Hybrid Campaign API Documentation

## Overview

The Hybrid Campaign API provides endpoints for creating and managing WhatsApp campaigns using two modes:
- **Template-based campaigns**: Using approved WhatsApp templates
- **Direct message campaigns**: Creating custom messages without templates

## Authentication

All API endpoints require authentication and workspace context. Include the following headers:

```
Authorization: Bearer {token}
X-Workspace-ID: {workspace_id}
```

## Base URL

```
https://your-domain.com/api
```

---

## Campaign Creation Endpoints

### Create Hybrid Campaign

Creates a new campaign using either template-based or direct message mode.

**Endpoint:** `POST /campaigns/hybrid`

**Request Headers:**
- `Content-Type: application/json` or `multipart/form-data` (for file uploads)

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `name` | string | Yes | Campaign name (max 128 characters) |
| `campaign_type` | string | Yes | `template` or `direct` |
| `contacts` | string | Yes | Contact group UUID or 'all' |
| `preferred_provider` | string | No | `webjs` or `meta_api` (default: `webjs`) |
| `whatsapp_account_id` | integer | No | Specific WhatsApp account ID |
| `skip_schedule` | boolean | No | Send immediately (default: false) |
| `scheduled_at` | string | No | ISO 8601 datetime for scheduled sending |
| `template` | string | No | Template UUID (required for template campaigns) |

#### Template Campaign Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `header.format` | string | Yes | `TEXT`, `IMAGE`, `DOCUMENT`, `VIDEO` |
| `header.text` | string | No | Header text (required for TEXT format) |
| `header.parameters` | array | No | Media parameters for non-text headers |
| `body.parameters` | array | Yes | Body text parameters |
| `footer.text` | string | No | Footer text (max 60 characters) |
| `buttons` | array | No | Button configuration |

#### Direct Message Campaign Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `header_type` | string | No | `text`, `image`, `document`, `video` (default: text) |
| `header_text` | string | No | Header text (required for text type) |
| `header_media` | file | No | Media file (required for non-text headers) |
| `body_text` | string | Yes | Message body text (max 1024 characters) |
| `footer_text` | string | No | Footer text (max 60 characters) |
| `buttons` | array | No | Button configuration |

#### Button Configuration

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `type` | string | Yes | `reply`, `url`, `phone_number` |
| `text` | string | Yes | Button text (max 20 characters) |
| `url` | string | No | URL (required for url type) |
| `phone_number` | string | No | Phone number (required for phone_number type) |

#### Media File Requirements

| Type | Max Size | Supported Formats |
|------|----------|-------------------|
| Image | 5MB | JPEG, JPG, PNG, GIF |
| Video | 16MB | MP4, MOV, AVI |
| Document | 100MB | PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX |

---

## Response Format

### Success Response (201)

```json
{
  "success": true,
  "message": "Campaign created successfully!",
  "campaign": {
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "name": "My Campaign",
    "type": "direct",
    "status": "pending",
    "provider": "webjs"
  }
}
```

### Error Response (422)

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "name": ["Campaign name is required"],
    "body_text": ["Message body is required"]
  }
}
```

---

## Examples

### Template Campaign Example

```json
{
  "name": "Welcome Template Campaign",
  "campaign_type": "template",
  "template": "550e8400-e29b-41d4-a716-446655440000",
  "contacts": "660e8400-e29b-41d4-a716-446655440001",
  "preferred_provider": "webjs",
  "skip_schedule": true,
  "header": {
    "format": "TEXT",
    "text": "Welcome!",
    "parameters": []
  },
  "body": {
    "parameters": [
      {
        "type": "text",
        "text": "Hello {{first_name}}, welcome to our service!"
      }
    ]
  },
  "footer": {
    "text": "Best regards, Team"
  },
  "buttons": [
    {
      "type": "reply",
      "text": "Get Started"
    }
  ]
}
```

### Direct Message Campaign Example

```json
{
  "name": "Special Offer Campaign",
  "campaign_type": "direct",
  "contacts": "660e8400-e29b-41d4-a716-446655440001",
  "preferred_provider": "meta_api",
  "skip_schedule": true,
  "header_type": "text",
  "header_text": "🎉 Special Offer",
  "body_text": "Hello {{first_name}},\n\nWe have an exclusive offer just for you! Get 20% off on all products this weekend.\n\nDon't miss out!",
  "footer_text": "Reply STOP to unsubscribe",
  "buttons": [
    {
      "type": "url",
      "text": "Shop Now",
      "url": "https://example.com/offers"
    },
    {
      "type": "reply",
      "text": "Not Interested"
    }
  ]
}
```

### Scheduled Campaign Example

```json
{
  "name": "Scheduled Reminder",
  "campaign_type": "direct",
  "contacts": "660e8400-e29b-41d4-a716-446655440001",
  "preferred_provider": "webjs",
  "skip_schedule": false,
  "scheduled_at": "2025-11-15T09:00:00Z",
  "header_type": "text",
  "body_text": "Don't forget about your appointment tomorrow!"
}
```

---

## Supporting Endpoints

### Get Available WhatsApp accounts

**Endpoint:** `GET /campaigns/available-sessions`

Returns list of active WhatsApp accounts for the workspace.

**Response:**
```json
{
  "success": true,
  "sessions": [
    {
      "id": 1,
      "uuid": "770e8400-e29b-41d4-a716-446655440002",
      "phone_number": "+1234567890",
      "provider_type": "webjs",
      "health_score": 95,
      "is_healthy": true
    }
  ],
  "webjs_count": 1,
  "meta_api_count": 0
}
```

### Validate Template Provider Compatibility

**Endpoint:** `POST /campaigns/validate-template-provider`

**Request:**
```json
{
  "template_uuid": "550e8400-e29b-41d4-a716-446655440000",
  "provider": "webjs"
}
```

**Response:**
```json
{
  "success": true,
  "valid": true,
  "message": "Template is compatible with selected provider",
  "template": {
    "header_type": "text",
    "requires_media": false,
    "button_count": 2
  }
}
```

### Preview Campaign Message

**Endpoint:** `POST /campaigns/preview-message`

**Request (Template):**
```json
{
  "campaign_type": "template",
  "template_uuid": "550e8400-e29b-41d4-a716-446655440000"
}
```

**Request (Direct):**
```json
{
  "campaign_type": "direct",
  "header_type": "text",
  "header_text": "Preview Header",
  "body_text": "This is a preview message",
  "footer_text": "Preview footer"
}
```

**Response:**
```json
{
  "success": true,
  "message_content": {
    "header_type": "text",
    "header_text": "Preview Header",
    "body_text": "This is a preview message",
    "footer_text": "Preview footer",
    "buttons_data": []
  }
}
```

---

## Error Codes

| Status Code | Description | Common Causes |
|-------------|-------------|---------------|
| 422 | Validation Error | Missing required fields, invalid data |
| 401 | Unauthorized | Invalid authentication token |
| 403 | Forbidden | Insufficient permissions |
| 404 | Not Found | Template or contact group not found |
| 413 | Payload Too Large | Media file exceeds size limit |
| 500 | Server Error | Internal server error |

---

## Rate Limiting

- Campaign creation: 10 requests per minute
- Preview generation: 30 requests per minute
- Validation requests: 60 requests per minute

---

## Provider-Specific Behavior

### WhatsApp Web JS (webjs)
- Preferred for better compatibility
- Supports text, image, document, and video messages
- Maximum file sizes: 5MB (images), 16MB (videos), 100MB (documents)
- Automatic fallback to Meta API if session unavailable

### Meta Business API (meta_api)
- Official WhatsApp Business API
- Requires approved templates for structured messages
- Maximum 10 buttons per message
- Better reliability and delivery tracking
- Higher message throughput

---

## Variable Replacement

Both campaign types support variable replacement in text content:

| Variable | Description | Example |
|----------|-------------|---------|
| `{{first_name}}` | Contact's first name | "Hello {{first_name}}" |
| `{{last_name}}` | Contact's last name | "Dear {{last_name}}" |
| `{{full_name}}` | Contact's full name | "Hi {{full_name}}" |
| `{{phone}}` | Contact's phone number | "Reply to {{phone}}" |
| `{{email}}` | Contact's email address | "Check {{email}}" |
| `{{company}}` | Contact's company | "From {{company}}" |
| `{{position}}` | Contact's job position | "Dear {{position}}" |

---

## Best Practices

1. **Template Campaigns**: Use for recurring messages and compliance
2. **Direct Campaigns**: Use for one-time notifications and simple messages
3. **Provider Selection**: Prefer WebJS for flexibility, Meta API for reliability
4. **Media Files**: Optimize file sizes for better delivery
5. **Button Limits**: Follow WhatsApp's button restrictions
6. **Variable Usage**: Ensure contact data is complete for proper replacement

---

## Testing

Use the following sample data for testing:

```bash
# Template Campaign Test
curl -X POST "https://your-domain.com/api/campaigns/hybrid" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test Template Campaign",
    "campaign_type": "template",
    "template": "TEMPLATE_UUID_HERE",
    "contacts": "CONTACT_GROUP_UUID_HERE",
    "preferred_provider": "webjs",
    "skip_schedule": true
  }'

# Direct Campaign Test
curl -X POST "https://your-domain.com/api/campaigns/hybrid" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test Direct Campaign",
    "campaign_type": "direct",
    "contacts": "CONTACT_GROUP_UUID_HERE",
    "preferred_provider": "webjs",
    "skip_schedule": true,
    "body_text": "Test message from API"
  }'
```

---

*Last Updated: 2025-11-14*
*Version: 1.0*