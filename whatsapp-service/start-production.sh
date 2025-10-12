#!/bin/bash

# WhatsApp Service Production Startup Script
# Usage: ./start-production.sh

echo "🚀 Starting WhatsApp Service in Production Mode..."

# Check if Node.js is installed
if ! command -v node &> /dev/null; then
    echo "❌ Node.js is not installed. Please install Node.js 18+ first."
    exit 1
fi

# Check if PM2 is installed
if ! command -v pm2 &> /dev/null; then
    echo "📦 Installing PM2 globally..."
    npm install -g pm2
fi

# Install dependencies
echo "📦 Installing Node.js dependencies..."
cd whatsapp-service
npm install --production

# Create logs directory
mkdir -p logs

# Set proper permissions
chmod +x server.js

# Start the service with PM2
echo "🔄 Starting WhatsApp service with PM2..."
pm2 start ecosystem.config.js

# Save PM2 configuration for auto-restart on server reboot
echo "💾 Saving PM2 configuration..."
pm2 save

# Enable PM2 startup script
echo "🔧 Setting up PM2 startup script..."
pm2 startup

# Display status
echo "📊 Service Status:"
pm2 status

echo ""
echo "✅ WhatsApp Service started successfully!"
echo ""
echo "🔍 Monitoring Commands:"
echo "  pm2 logs whatsapp-service     # View logs"
echo "  pm2 monit                    # Real-time monitoring"
echo "  pm2 restart whatsapp-service  # Restart service"
echo "  pm2 stop whatsapp-service     # Stop service"
echo ""
echo "🌐 Service URLs:"
echo "  Health Check: http://localhost:3000/health"
echo "  API Base: http://localhost:3000/api"
echo ""
echo "📝 Logs are available at: ./logs/"
echo ""
echo "🎯 Next Steps:"
echo "  1. Configure environment variables in .env"
echo "  2. Test QR code generation"
echo "  3. Verify Laravel integration"
echo "  4. Set up monitoring alerts"
echo ""
echo "Happy messaging! 🚀"
