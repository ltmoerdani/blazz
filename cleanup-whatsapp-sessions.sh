#!/bin/bash

# WhatsApp Sessions Cleanup Script
# Date: 2025-10-13
# Purpose: Clean up stuck WhatsApp sessions before testing

echo "=========================================="
echo "WhatsApp Sessions Cleanup"
echo "=========================================="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

cd /Applications/MAMP/htdocs/blazz

# Step 1: Show current sessions
echo -e "${YELLOW}[1/5] Current WhatsApp sessions in database:${NC}"
php artisan tinker --execute="
\$sessions = DB::table('whatsapp_sessions')->get();
foreach(\$sessions as \$s) {
    echo '  ID: ' . \$s->id . ' | ' . \$s->session_id . ' | Status: ' . \$s->status . PHP_EOL;
}
echo '  Total: ' . count(\$sessions) . ' sessions' . PHP_EOL;
" 2>/dev/null

# Step 2: Stop Node.js service
echo ""
echo -e "${YELLOW}[2/5] Stopping Node.js service...${NC}"
pkill -9 node 2>/dev/null
sleep 2
echo -e "${GREEN}✅ Node.js stopped${NC}"

# Step 3: Clean Node.js session files
echo ""
echo -e "${YELLOW}[3/5] Cleaning Node.js session files...${NC}"
SESSION_DIR="whatsapp-service/.wwebjs_auth"
if [ -d "$SESSION_DIR" ]; then
    SESSION_COUNT=$(find "$SESSION_DIR" -mindepth 1 -maxdepth 1 -type d | wc -l)
    if [ "$SESSION_COUNT" -gt 0 ]; then
        rm -rf "$SESSION_DIR"/*
        echo -e "${GREEN}✅ Cleaned $SESSION_COUNT session directories${NC}"
    else
        echo -e "${GREEN}✅ No session files to clean${NC}"
    fi
else
    echo -e "${GREEN}✅ Session directory doesn't exist${NC}"
fi

# Step 4: Clean database
echo ""
echo -e "${YELLOW}[4/5] Cleaning database sessions...${NC}"
DB_RESULT=$(php artisan tinker --execute="
\$count = DB::table('whatsapp_sessions')->count();
DB::table('whatsapp_sessions')->delete();
echo \$count;
" 2>/dev/null)
echo -e "${GREEN}✅ Deleted $DB_RESULT database records${NC}"

# Step 5: Verify cleanup
echo ""
echo -e "${YELLOW}[5/5] Verifying cleanup...${NC}"

# Check database
DB_COUNT=$(php artisan tinker --execute="echo DB::table('whatsapp_sessions')->count();" 2>/dev/null)
if [ "$DB_COUNT" = "0" ]; then
    echo -e "${GREEN}✅ Database: 0 sessions${NC}"
else
    echo -e "${RED}❌ Database still has $DB_COUNT sessions!${NC}"
fi

# Check session files
if [ -d "$SESSION_DIR" ]; then
    FILE_COUNT=$(find "$SESSION_DIR" -mindepth 1 -maxdepth 1 | wc -l)
    if [ "$FILE_COUNT" -eq 0 ]; then
        echo -e "${GREEN}✅ Session files: cleaned${NC}"
    else
        echo -e "${RED}❌ Session files still exist: $FILE_COUNT items${NC}"
    fi
else
    echo -e "${GREEN}✅ Session directory: clean${NC}"
fi

# Check Node.js
if lsof -i :3001 > /dev/null 2>&1; then
    echo -e "${RED}❌ Node.js still running${NC}"
else
    echo -e "${GREEN}✅ Node.js: stopped${NC}"
fi

echo ""
echo "=========================================="
echo "✅ Cleanup completed!"
echo "=========================================="
echo ""
echo "Next steps:"
echo "1. Run: ./restart-whatsapp-services.sh"
echo "2. Run: ./test-whatsapp-qr-fix.sh"
echo "3. Test via browser"
echo ""
