#!/bin/bash

# Test script to send WhatsApp message via Node.js API

SESSION_ID="webjs_1_1761187563_TQ2jtzUZ"
WORKSPACE_ID=1
RECIPIENT_PHONE="6282146291472"  # Change to your test number
MESSAGE="Test message from API at $(date)"
API_KEY="your-api-key-here"  # Should match .env

curl -X POST http://localhost:3001/api/messages/send \
  -H "Content-Type: application/json" \
  -d "{
    \"session_id\": \"$SESSION_ID\",
    \"workspace_id\": $WORKSPACE_ID,
    \"recipient_phone\": \"$RECIPIENT_PHONE\",
    \"message\": \"$MESSAGE\",
    \"type\": \"text\",
    \"api_key\": \"$API_KEY\"
  }"

echo ""
echo "Message sent! Check logs:"
echo "tail -f whatsapp-service/logs/whatsapp-service.log | grep 'Message'"
