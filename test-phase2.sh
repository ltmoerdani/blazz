#!/bin/bash

# Phase 2 Implementation Test Script
# Tests: Queue Worker Auto-Restart & Load Balancing

echo "========================================="
echo "PHASE 2: Implementation Test"
echo "Date: $(date)"
echo "========================================="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test counters
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

# Helper functions
pass() {
    echo -e "${GREEN}✓ PASSED${NC}: $1"
    ((PASSED_TESTS++))
    ((TOTAL_TESTS++))
}

fail() {
    echo -e "${RED}✗ FAILED${NC}: $1"
    ((FAILED_TESTS++))
    ((TOTAL_TESTS++))
}

info() {
    echo -e "${YELLOW}ℹ INFO${NC}: $1"
}

section() {
    echo ""
    echo "========================================="
    echo "$1"
    echo "========================================="
    echo ""
}

# =====================================
# TEST 1: SimpleLoadBalancer Class
# =====================================
section "TEST 1: SimpleLoadBalancer Class"

info "Checking if SimpleLoadBalancer.php exists..."
if [ -f "app/Services/WhatsApp/SimpleLoadBalancer.php" ]; then
    pass "SimpleLoadBalancer.php file exists"
else
    fail "SimpleLoadBalancer.php file NOT found"
fi

info "Checking syntax..."
php -l app/Services/WhatsApp/SimpleLoadBalancer.php > /dev/null 2>&1
if [ $? -eq 0 ]; then
    pass "SimpleLoadBalancer.php syntax is valid"
else
    fail "SimpleLoadBalancer.php has syntax errors"
fi

# =====================================
# TEST 2: Load Balancer Functionality
# =====================================
section "TEST 2: Load Balancer Functionality"

info "Testing getNextInstance() method..."
php artisan tinker --execute="
\$lb = new \App\Services\WhatsApp\SimpleLoadBalancer();
\$instance = \$lb->getNextInstance();
echo 'Selected instance: ' . \$instance . PHP_EOL;
if (in_array(\$instance, ['http://localhost:3001', 'http://localhost:3002', 'http://localhost:3003', 'http://localhost:3004'])) {
    echo 'PASS' . PHP_EOL;
} else {
    echo 'FAIL' . PHP_EOL;
}
" > /tmp/lb_test_output.txt 2>&1

if grep -q "PASS" /tmp/lb_test_output.txt; then
    pass "getNextInstance() returns valid instance URL"
    cat /tmp/lb_test_output.txt | grep "Selected instance"
else
    fail "getNextInstance() did not return valid instance"
    cat /tmp/lb_test_output.txt
fi

info "Testing getDistribution() method..."
php artisan tinker --execute="
\$lb = new \App\Services\WhatsApp\SimpleLoadBalancer();
\$dist = \$lb->getDistribution();
echo 'Distribution: ' . json_encode(\$dist) . PHP_EOL;
echo (count(\$dist) == 4 ? 'PASS' : 'FAIL') . PHP_EOL;
" > /tmp/lb_dist_output.txt 2>&1

if grep -q "PASS" /tmp/lb_dist_output.txt; then
    pass "getDistribution() returns all 4 instances"
    cat /tmp/lb_dist_output.txt | grep "Distribution"
else
    fail "getDistribution() did not return 4 instances"
    cat /tmp/lb_dist_output.txt
fi

info "Testing isBalanced() method..."
php artisan tinker --execute="
\$lb = new \App\Services\WhatsApp\SimpleLoadBalancer();
\$balanced = \$lb->isBalanced();
echo 'Is balanced: ' . (\$balanced ? 'true' : 'false') . PHP_EOL;
echo 'PASS' . PHP_EOL;
" > /tmp/lb_balanced_output.txt 2>&1

if grep -q "PASS" /tmp/lb_balanced_output.txt; then
    pass "isBalanced() executes without error"
    cat /tmp/lb_balanced_output.txt | grep "Is balanced"
else
    fail "isBalanced() threw an error"
    cat /tmp/lb_balanced_output.txt
fi

# =====================================
# TEST 3: Integration with WhatsAppAccountService
# =====================================
section "TEST 3: Integration with WhatsAppAccountService"

info "Checking if WhatsAppAccountService uses load balancer..."
if grep -q "SimpleLoadBalancer" app/Services/WhatsApp/WhatsAppAccountService.php; then
    pass "WhatsAppAccountService integrated with SimpleLoadBalancer"
else
    fail "WhatsAppAccountService NOT using SimpleLoadBalancer"
fi

info "Checking if assigned_instance_url is set on account creation..."
if grep -q "assigned_instance_url.*assignedInstance" app/Services/WhatsApp/WhatsAppAccountService.php; then
    pass "assigned_instance_url is set from load balancer"
else
    fail "assigned_instance_url NOT being set"
fi

# =====================================
# TEST 4: Queue Worker Status
# =====================================
section "TEST 4: Queue Worker Status"

info "Checking current queue worker process..."
QUEUE_PID=$(ps aux | grep "queue:work" | grep -v grep | awk '{print $2}' | head -1)

if [ -n "$QUEUE_PID" ]; then
    pass "Queue worker is running (PID: $QUEUE_PID)"
    info "Command: $(ps -p $QUEUE_PID -o command= 2>/dev/null)"
else
    fail "Queue worker is NOT running"
    info "⚠️  Need to implement aaPanel Supervisor configuration"
fi

# Check if Supervisor is available
info "Checking if Supervisor is installed..."
if command -v supervisorctl > /dev/null 2>&1; then
    pass "Supervisor is installed"
    info "Version: $(supervisorctl version 2>/dev/null || echo 'Unknown')"
else
    fail "Supervisor is NOT installed"
    info "⚠️  Install via: sudo apt install supervisor (Ubuntu) or via aaPanel"
fi

# =====================================
# TEST 5: Performance Check
# =====================================
section "TEST 5: Current Load Distribution"

info "Getting current session distribution across instances..."
php artisan tinker --execute="
use App\Models\WhatsAppAccount;
\$dist = WhatsAppAccount::select('assigned_instance_url', \Illuminate\Support\Facades\DB::raw('COUNT(*) as count'))
    ->whereIn('status', ['connected', 'qr_scanning'])
    ->groupBy('assigned_instance_url')
    ->get();

echo 'Current Distribution:' . PHP_EOL;
foreach (\$dist as \$d) {
    echo '  ' . (\$d->assigned_instance_url ?: 'NULL') . ': ' . \$d->count . ' sessions' . PHP_EOL;
}
echo 'PASS' . PHP_EOL;
" > /tmp/current_dist.txt 2>&1

if grep -q "PASS" /tmp/current_dist.txt; then
    pass "Successfully retrieved current distribution"
    cat /tmp/current_dist.txt | grep -A 10 "Current Distribution"
else
    fail "Failed to get distribution"
    cat /tmp/current_dist.txt
fi

# =====================================
# SUMMARY
# =====================================
section "TEST SUMMARY"

echo "Total Tests:  $TOTAL_TESTS"
echo -e "Passed:       ${GREEN}$PASSED_TESTS${NC}"
echo -e "Failed:       ${RED}$FAILED_TESTS${NC}"
echo ""

if [ $FAILED_TESTS -eq 0 ]; then
    echo -e "${GREEN}=========================================${NC}"
    echo -e "${GREEN}  ALL TESTS PASSED ✓${NC}"
    echo -e "${GREEN}=========================================${NC}"
    exit 0
else
    echo -e "${RED}=========================================${NC}"
    echo -e "${RED}  SOME TESTS FAILED ✗${NC}"
    echo -e "${RED}=========================================${NC}"
    exit 1
fi
