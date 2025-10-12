# WhatsApp Web.js Service

Node.js service for WhatsApp Web integration using whatsapp-web.js library.

## Prerequisites

- Node.js 18.x or higher
- npm or yarn package manager

## Installation

1. Install dependencies:
```bash
cd whatsapp-service
npm install
```

2. Configure environment variables:
```bash
cp .env.example .env
# Edit .env and ensure API_TOKEN and HMAC_SECRET match your Laravel .env:
# WHATSAPP_NODE_API_TOKEN and WHATSAPP_NODE_HMAC_SECRET
```

3. Important: The API_TOKEN and HMAC_SECRET in this .env MUST match the values in your Laravel .env file:
   - `API_TOKEN` here = `WHATSAPP_NODE_API_TOKEN` in Laravel
   - `HMAC_SECRET` here = `WHATSAPP_NODE_HMAC_SECRET` in Laravel

## Running the Service

### Development
```bash
npm run dev
```

### Production
```bash
npm start
```

### Using PM2 (Production)
```bash
npm run pm2
```

## Service Architecture

This service acts as a bridge between WhatsApp Web and your Laravel application:

1. **Session Management**: Creates and manages WhatsApp Web sessions
2. **QR Code Generation**: Generates QR codes for authentication
3. **Message Handling**: Sends and receives WhatsApp messages
4. **Webhook Integration**: Sends events to Laravel via webhooks

## API Endpoints

### Session Management

- `POST /api/sessions/create` - Create new WhatsApp session
- `POST /api/sessions/disconnect` - Disconnect session
- `POST /api/sessions/refresh-qr` - Refresh QR code
- `GET /api/sessions/:sessionId/status` - Get session status

### Message Operations

- `POST /api/messages/send` - Send text message
- `POST /api/messages/send-media` - Send media message

### Health Check

- `GET /health` - Service health status

## Authentication

All API requests require:
- `X-API-Token` header with the API token
- `X-HMAC-Signature` header with HMAC signature
- `X-Timestamp` header with current timestamp
- `X-Workspace-ID` header with workspace ID

## Webhook Events

The service sends the following events to Laravel:

- `session.qr` - QR code generated
- `session.ready` - Session connected
- `session.disconnected` - Session disconnected
- `session.destroyed` - Session destroyed
- `message.received` - Message received from WhatsApp
- `message.sent` - Message sent confirmation
- `message.failed` - Message failed to send

## Troubleshooting

### Service won't start
- Check if port 3000 is available
- Verify Node.js version (requires 18.x+)
- Check .env configuration

### QR code not generating
- Verify API_TOKEN and HMAC_SECRET match Laravel .env
- Check Laravel webhook endpoint is accessible
- Review service logs for errors

### Authentication errors
- Ensure HMAC_SECRET matches between Laravel and Node service
- Verify timestamps are within 5-minute window
- Check that X-Workspace-ID header is present

## Logs

Logs are written to console with Winston logger. In production with PM2:
```bash
pm2 logs whatsapp-service
```
