#!/bin/bash

# Blazz Development Server Monitor Script
# Monitors all development services in real-time
# Usage: ./monitor-dev.sh [interval]
# Default interval: 5 seconds

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
WHITE='\033[1;37m'
NC='\033[0m' # No Color
BOLD='\033[1m'
DIM='\033[2m'

# Emoji indicators
UP="✅"
DOWN="❌"
WARN="⚠️"
WAIT="⏳"

# Refresh interval (default 5 seconds, can be overridden by argument)
INTERVAL=${1:-5}

# Service definitions
declare -A SERVICES
SERVICES["Laravel Backend"]="8000|http://127.0.0.1:8000|php artisan serve"
SERVICES["Laravel Reverb"]="8080|http://127.0.0.1:8080|php artisan reverb:start"
SERVICES["WhatsApp Service"]="3001|http://127.0.0.1:3001/health|whatsapp-service"
SERVICES["Queue Worker"]="-|process|php artisan queue:work"
SERVICES["Scheduler"]="-|process|php artisan schedule:work"
SERVICES["Redis"]="-|redis|redis-cli ping"

# Multi-instance WhatsApp ports (optional)
MULTI_INSTANCE_PORTS=(3002 3003 3004)

# Function to check if a URL is responding
check_url() {
    local url=$1
    local timeout=2
    
    if curl -s --connect-timeout $timeout "$url" > /dev/null 2>&1; then
        return 0
    else
        return 1
    fi
}

# Function to check if a process is running
check_process() {
    local pattern=$1
    
    if pgrep -f "$pattern" > /dev/null 2>&1; then
        return 0
    else
        return 1
    fi
}

# Function to check Redis
check_redis() {
    if redis-cli ping > /dev/null 2>&1; then
        return 0
    else
        return 1
    fi
}

# Function to get process count
get_process_count() {
    local pattern=$1
    pgrep -f "$pattern" 2>/dev/null | wc -l | tr -d ' '
}

# Function to get memory usage of a process
get_memory_usage() {
    local pattern=$1
    local pid=$(pgrep -f "$pattern" | head -1)
    
    if [ -n "$pid" ]; then
        ps -o rss= -p "$pid" 2>/dev/null | awk '{printf "%.1fMB", $1/1024}'
    else
        echo "N/A"
    fi
}

# Function to get response time
get_response_time() {
    local url=$1
    local time=$(curl -s -o /dev/null -w "%{time_total}" --connect-timeout 2 "$url" 2>/dev/null)
    
    if [ -n "$time" ] && [ "$time" != "" ]; then
        printf "%.0fms" $(echo "$time * 1000" | bc 2>/dev/null || echo "0")
    else
        echo "N/A"
    fi
}

# Function to check WhatsApp service details
get_whatsapp_details() {
    local response=$(curl -s --connect-timeout 2 "http://127.0.0.1:3001/health" 2>/dev/null)
    
    if [ -n "$response" ]; then
        # Extract sessions count if available
        local sessions=$(echo "$response" | grep -o '"totalSessions":[0-9]*' | cut -d':' -f2)
        local status=$(echo "$response" | grep -o '"status":"[^"]*"' | cut -d'"' -f4)
        
        if [ -n "$sessions" ]; then
            echo "Sessions: $sessions"
        elif [ -n "$status" ]; then
            echo "Status: $status"
        else
            echo "Connected"
        fi
    else
        echo "Unknown"
    fi
}

# Function to get Redis info
get_redis_info() {
    local info=$(redis-cli info 2>/dev/null | grep -E "connected_clients|used_memory_human" | head -2)
    
    if [ -n "$info" ]; then
        local clients=$(echo "$info" | grep "connected_clients" | cut -d':' -f2 | tr -d '\r')
        local memory=$(echo "$info" | grep "used_memory_human" | cut -d':' -f2 | tr -d '\r')
        echo "Clients: ${clients:-0}, Mem: ${memory:-N/A}"
    else
        echo "N/A"
    fi
}

# Function to display header
display_header() {
    clear
    echo -e "${CYAN}╔══════════════════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║${NC}         ${BOLD}${WHITE}🚀 BLAZZ DEVELOPMENT SERVER MONITOR${NC}                                  ${CYAN}║${NC}"
    echo -e "${CYAN}╠══════════════════════════════════════════════════════════════════════════════╣${NC}"
    echo -e "${CYAN}║${NC}  ${DIM}Monitoring interval: ${INTERVAL}s | Press Ctrl+C to exit${NC}                           ${CYAN}║${NC}"
    echo -e "${CYAN}╚══════════════════════════════════════════════════════════════════════════════╝${NC}"
    echo ""
}

# Function to display service status
display_service() {
    local name=$1
    local port=$2
    local status=$3
    local details=$4
    local response_time=$5
    local memory=$6
    
    local status_icon=""
    local status_color=""
    
    if [ "$status" = "UP" ]; then
        status_icon="${UP}"
        status_color="${GREEN}"
    elif [ "$status" = "DOWN" ]; then
        status_icon="${DOWN}"
        status_color="${RED}"
    else
        status_icon="${WARN}"
        status_color="${YELLOW}"
    fi
    
    # Format port display
    local port_display=""
    if [ "$port" != "-" ]; then
        port_display="${DIM}:${port}${NC}"
    fi
    
    printf "${status_color}${status_icon} %-20s${NC}${port_display}" "$name"
    
    # Add padding based on port length
    local padding=$((25 - ${#name} - ${#port} - 1))
    if [ "$port" = "-" ]; then
        padding=$((25 - ${#name}))
    fi
    printf "%${padding}s" ""
    
    printf "${DIM}│${NC} "
    
    if [ "$status" = "UP" ]; then
        if [ -n "$response_time" ] && [ "$response_time" != "N/A" ]; then
            printf "${GREEN}%-10s${NC}" "$response_time"
        else
            printf "%-10s" "-"
        fi
        printf "${DIM}│${NC} "
        if [ -n "$memory" ] && [ "$memory" != "N/A" ]; then
            printf "${BLUE}%-12s${NC}" "$memory"
        else
            printf "%-12s" "-"
        fi
        printf "${DIM}│${NC} "
        printf "${CYAN}%s${NC}" "$details"
    else
        printf "${RED}%-10s${NC}" "OFFLINE"
        printf "${DIM}│${NC} "
        printf "%-12s" "-"
        printf "${DIM}│${NC} "
        printf "${RED}Service not responding${NC}"
    fi
    
    echo ""
}

# Function to display table header
display_table_header() {
    echo -e "${WHITE}${BOLD}┌──────────────────────────────┬────────────┬──────────────┬─────────────────────────┐${NC}"
    echo -e "${WHITE}${BOLD}│ Service                      │ Response   │ Memory       │ Details                 │${NC}"
    echo -e "${WHITE}${BOLD}├──────────────────────────────┼────────────┼──────────────┼─────────────────────────┤${NC}"
}

# Function to display table footer
display_table_footer() {
    echo -e "${WHITE}${BOLD}└──────────────────────────────┴────────────┴──────────────┴─────────────────────────┘${NC}"
}

# Function to display summary
display_summary() {
    local up=$1
    local down=$2
    local total=$3
    local timestamp=$(date "+%Y-%m-%d %H:%M:%S")
    
    echo ""
    echo -e "${DIM}──────────────────────────────────────────────────────────────────────────────────${NC}"
    echo ""
    
    if [ "$down" -eq 0 ]; then
        echo -e "  ${GREEN}${BOLD}🎉 All services are running!${NC} (${up}/${total} online)"
    elif [ "$up" -eq 0 ]; then
        echo -e "  ${RED}${BOLD}⛔ All services are down!${NC} (${up}/${total} online)"
    else
        echo -e "  ${YELLOW}${BOLD}⚠️  Some services have issues${NC} (${up}/${total} online, ${down} down)"
    fi
    
    echo ""
    echo -e "  ${DIM}Last updated: ${timestamp}${NC}"
    echo -e "  ${DIM}Next refresh in ${INTERVAL} seconds...${NC}"
}

# Function to display quick actions
display_actions() {
    echo ""
    echo -e "${MAGENTA}${BOLD}Quick Actions:${NC}"
    echo -e "  ${DIM}• Start all services:${NC} ./start-dev.sh"
    echo -e "  ${DIM}• Stop all services:${NC}  ./stop-dev.sh"
    echo -e "  ${DIM}• View logs:${NC}          tail -f logs/*.log"
    echo -e "  ${DIM}• Redis CLI:${NC}          redis-cli"
}

# Main monitoring loop
monitor() {
    while true; do
        display_header
        display_table_header
        
        local services_up=0
        local services_down=0
        local total_services=0
        
        # Check Laravel Backend
        total_services=$((total_services + 1))
        if check_url "http://127.0.0.1:8000"; then
            services_up=$((services_up + 1))
            local resp_time=$(get_response_time "http://127.0.0.1:8000")
            local mem=$(get_memory_usage "php artisan serve")
            display_service "Laravel Backend" "8000" "UP" "Ready" "$resp_time" "$mem"
        else
            services_down=$((services_down + 1))
            display_service "Laravel Backend" "8000" "DOWN" "" "" ""
        fi
        
        # Check Laravel Reverb
        total_services=$((total_services + 1))
        if check_url "http://127.0.0.1:8080"; then
            services_up=$((services_up + 1))
            local resp_time=$(get_response_time "http://127.0.0.1:8080")
            local mem=$(get_memory_usage "php artisan reverb:start")
            display_service "Laravel Reverb" "8080" "UP" "Broadcasting" "$resp_time" "$mem"
        else
            services_down=$((services_down + 1))
            display_service "Laravel Reverb" "8080" "DOWN" "" "" ""
        fi
        
        # Check WhatsApp Service (Single Instance)
        total_services=$((total_services + 1))
        if check_url "http://127.0.0.1:3001/health"; then
            services_up=$((services_up + 1))
            local resp_time=$(get_response_time "http://127.0.0.1:3001/health")
            local mem=$(get_memory_usage "whatsapp-service\|nodemon.*server.js")
            local details=$(get_whatsapp_details)
            display_service "WhatsApp Service" "3001" "UP" "$details" "$resp_time" "$mem"
        else
            services_down=$((services_down + 1))
            display_service "WhatsApp Service" "3001" "DOWN" "" "" ""
        fi
        
        # Check Multi-Instance WhatsApp (if running)
        for port in "${MULTI_INSTANCE_PORTS[@]}"; do
            if check_url "http://127.0.0.1:$port/health"; then
                total_services=$((total_services + 1))
                services_up=$((services_up + 1))
                local resp_time=$(get_response_time "http://127.0.0.1:$port/health")
                display_service "WhatsApp Instance" "$port" "UP" "Multi-instance" "$resp_time" ""
            fi
        done
        
        # Check Queue Worker
        total_services=$((total_services + 1))
        if check_process "php artisan queue:work"; then
            services_up=$((services_up + 1))
            local count=$(get_process_count "php artisan queue:work")
            local mem=$(get_memory_usage "php artisan queue:work")
            display_service "Queue Worker" "-" "UP" "Workers: $count" "" "$mem"
        else
            services_down=$((services_down + 1))
            display_service "Queue Worker" "-" "DOWN" "" "" ""
        fi
        
        # Check Scheduler
        total_services=$((total_services + 1))
        if check_process "php artisan schedule:work"; then
            services_up=$((services_up + 1))
            local mem=$(get_memory_usage "php artisan schedule:work")
            display_service "Scheduler" "-" "UP" "Running" "" "$mem"
        else
            services_down=$((services_down + 1))
            display_service "Scheduler" "-" "DOWN" "" "" ""
        fi
        
        # Check Redis
        total_services=$((total_services + 1))
        if check_redis; then
            services_up=$((services_up + 1))
            local redis_info=$(get_redis_info)
            display_service "Redis" "6379" "UP" "$redis_info" "" ""
        else
            services_down=$((services_down + 1))
            display_service "Redis" "6379" "DOWN" "" "" ""
        fi
        
        display_table_footer
        display_summary $services_up $services_down $total_services
        display_actions
        
        # Wait for next refresh
        sleep $INTERVAL
    done
}

# Trap Ctrl+C for graceful exit
trap 'echo -e "\n${YELLOW}👋 Monitor stopped.${NC}"; exit 0' SIGINT SIGTERM

# Check for help flag
if [ "$1" = "-h" ] || [ "$1" = "--help" ]; then
    echo "Blazz Development Server Monitor"
    echo ""
    echo "Usage: ./monitor-dev.sh [interval]"
    echo ""
    echo "Options:"
    echo "  interval    Refresh interval in seconds (default: 5)"
    echo "  -h, --help  Show this help message"
    echo ""
    echo "Examples:"
    echo "  ./monitor-dev.sh        # Monitor with 5 second interval"
    echo "  ./monitor-dev.sh 2      # Monitor with 2 second interval"
    echo "  ./monitor-dev.sh 10     # Monitor with 10 second interval"
    exit 0
fi

# Start monitoring
echo -e "${GREEN}Starting Blazz Server Monitor...${NC}"
echo -e "${DIM}Refresh interval: ${INTERVAL} seconds${NC}"
echo -e "${DIM}Press Ctrl+C to stop${NC}"
sleep 1

monitor
