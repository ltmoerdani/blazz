# WhatsApp Web.js Service

Node.js service for WhatsApp Web.js integration with Laravel Blazz application.

## Features

- **Multi-Session Support**: Handle multiple WhatsApp sessions per workspace
- **QR Code Generation**: Automatic QR code generation for WhatsApp Web authentication
- **Real-time Communication**: Webhook integration with Laravel backend
- **Session Management**: Create, reconnect, and disconnect WhatsApp sessions
- **Health Monitoring**: Built-in health checks and monitoring
- **Security**: HMAC authentication for Laravel communication
- **Production Ready**: PM2 configuration for production deployment

## Prerequisites

- Node.js 18+
- Chrome/Chromium browser
- Linux/macOS/Windows

## Installation

1. **Install Dependencies**
   ```bash
   cd whatsapp-service
   npm install
   ```

2. **Configure Environment**
   ```bash
   cp .env.example .env
   # Edit .env with your Laravel configuration
   ```

3. **Start Development Server**
   ```bash
   npm run dev
   ```

4. **Start Production Server**
   ```bash
   npm run pm2
   ```

## Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `NODE_ENV` | Environment (development/production) | development |
| `PORT` | Server port | 3000 |
| `LARAVEL_URL` | Laravel backend URL | http://127.0.0.1:8000 |
| `API_KEY` | API key for Laravel authentication | - |
| `HMAC_SECRET` | HMAC secret for webhook security | - |
| `LOG_LEVEL` | Logging level | info |
| `MAX_CONCURRENT_SESSIONS` | Maximum concurrent sessions | 50 |

## API Endpoints

### Session Management

- `POST /api/sessions` - Create new WhatsApp session
- `GET /api/sessions/:sessionId/status` - Get session status
- `DELETE /api/sessions/:sessionId` - Disconnect session
- `POST /api/sessions/:sessionId/reconnect` - Reconnect session
- `POST /api/sessions/:sessionId/regenerate-qr` - Regenerate QR code

### Messaging

- `POST /api/messages/send` - Send WhatsApp message

### Monitoring

- `GET /health` - Health check endpoint

## Laravel Integration

The service integrates with Laravel through:

1. **Webhook Callbacks**: Real-time events sent to Laravel
2. **HMAC Authentication**: Secure communication with Laravel API
3. **Broadcasting**: QR codes and status updates broadcast to frontend

## Development

### Project Structure

```
whatsapp-service/
├── server.js              # Main server file
├── package.json           # Dependencies and scripts
├── ecosystem.config.js    # PM2 configuration
├── .env                   # Environment variables
├── sessions/              # WhatsApp session data
├── logs/                  # Application logs
└── README.md             # This file
```

### Key Components

- **WhatsAppSessionManager**: Manages WhatsApp client instances
- **Event Handlers**: QR code, authentication, messages, disconnections
- **Laravel Integration**: Secure communication with Laravel backend
- **Health Monitoring**: Session and system health tracking

## Production Deployment

1. **Install PM2**
   ```bash
   npm install -g pm2
   ```

2. **Start with PM2**
   ```bash
   cd whatsapp-service
   pm2 start ecosystem.config.js
   ```

3. **Monitor Service**
   ```bash
   pm2 logs whatsapp-service
   pm2 monit whatsapp-service
   ```

## Security

- HMAC-SHA256 authentication for Laravel communication
- Session isolation per workspace
- Rate limiting for API endpoints
- Secure session storage with encryption

## Troubleshooting

### Common Issues

1. **QR Code Not Generating**
   - Check Chrome/Chromium installation
   - Verify puppeteer configuration
   - Check session storage permissions

2. **Session Disconnection**
   - Check network connectivity
   - Verify WhatsApp Web.js version compatibility
   - Check session storage corruption

3. **Laravel Communication Issues**
   - Verify API key configuration
   - Check HMAC secret consistency
   - Ensure Laravel service is running

### Logs

Check logs for debugging:
```bash
pm2 logs whatsapp-service
# or
tail -f logs/whatsapp-service.log
```

## License

MIT License - see LICENSE file for details.
