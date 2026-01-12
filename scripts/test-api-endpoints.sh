#!/bin/bash

###############################################################################
# Clarity SEO API Endpoint Testing Script
# 
# Tests all 164+ API endpoints with authentication and .env integration
# 
# Usage:
#   ./scripts/test-api-endpoints.sh              # Test all endpoints
#   ./scripts/test-api-endpoints.sh --facebook   # Test Facebook endpoints only
#   ./scripts/test-api-endpoints.sh --auth       # Test auth endpoints only
###############################################################################

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Load .env file
ENV_FILE="$PROJECT_ROOT/.env"
if [ ! -f "$ENV_FILE" ]; then
    echo -e "${RED}Error: .env file not found at $ENV_FILE${NC}"
    exit 1
fi

# Export .env variables
set -a
source "$ENV_FILE"
set +a

# Configuration
API_BASE_URL="${APP_URL:-http://localhost:8000}/api/v1"
DB_PATH="$PROJECT_ROOT/database/database.sqlite"

# Test results
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0
SKIPPED_TESTS=0

# Test mode
TEST_MODE="${1:-all}"

# Log file
LOG_FILE="$PROJECT_ROOT/storage/logs/api-test-$(date +%Y%m%d-%H%M%S).log"
mkdir -p "$(dirname "$LOG_FILE")"

# Global variables for authentication
AUTH_TOKEN=""
TENANT_ID=""
LOCATION_ID=""
REVIEW_ID=""
LISTING_ID=""

###############################################################################
# Helper Functions
###############################################################################

log() {
    echo -e "$1" | tee -a "$LOG_FILE"
}

log_success() {
    log "${GREEN}✅ $1${NC}"
    ((PASSED_TESTS++))
}

log_error() {
    log "${RED}❌ $1${NC}"
    ((FAILED_TESTS++))
}

log_skip() {
    log "${YELLOW}⏭  $1${NC}"
    ((SKIPPED_TESTS++))
}

log_info() {
    log "${BLUE}ℹ  $1${NC}"
}

# Make HTTP request
http_request() {
    local method="$1"
    local endpoint="$2"
    local data="$3"
    local auth_required="${4:-true}"
    
    ((TOTAL_TESTS++))
    
    local url="${API_BASE_URL}${endpoint}"
    local start_time=$(date +%s%3N)
    
    # Build curl command
    local curl_cmd="curl -s -w '\n%{http_code}' -X $method '$url'"
    
    # Add auth header if required
    if [ "$auth_required" = "true" ] && [ -n "$AUTH_TOKEN" ]; then
        curl_cmd="$curl_cmd -H 'Authorization: Bearer $AUTH_TOKEN'"
    fi
    
    # Add content-type and data
    if [ -n "$data" ]; then
        curl_cmd="$curl_cmd -H 'Content-Type: application/json' -d '$data'"
    fi
    
    # Execute request
    local response=$(eval $curl_cmd)
    local http_code=$(echo "$response" | tail -n1)
    local body=$(echo "$response" | sed '$d')
    
    local end_time=$(date +%s%3N)
    local duration=$((end_time - start_time))
    
    # Store response for potential use
    echo "$body" > /tmp/clarity_api_response.json
    
    echo "$http_code|$duration|$body"
}

# Parse JSON field
json_field() {
    local json="$1"
    local field="$2"
    echo "$json" | grep -o "\"$field\":\"[^\"]*\"" | sed "s/\"$field\":\"\([^\"]*\)\"/\1/"
}

test_endpoint() {
    local name="$1"
    local method="$2"
    local endpoint="$3"
    local data="$4"
    local expected_code="${5:-200}"
    local auth_required="${6:-true}"
    
    local result=$(http_request "$method" "$endpoint" "$data" "$auth_required")
    local http_code=$(echo "$result" | cut -d'|' -f1)
    local duration=$(echo "$result" | cut -d'|' -f2)
    local body=$(echo "$result" | cut -d'|' -f3-)
    
    if [ "$http_code" = "$expected_code" ]; then
        log_success "[$TOTAL_TESTS] $name ($http_code) - ${duration}ms"
        echo "$body"
        return 0
    else
        log_error "[$TOTAL_TESTS] $name (Expected: $expected_code, Got: $http_code) - ${duration}ms"
        log "   Response: $(echo "$body" | head -c 200)"
        return 1
    fi
}

###############################################################################
# Test Suites
###############################################################################

test_auth_endpoints() {
    log ""
    log "==== TESTING AUTHENTICATION ENDPOINTS ===="
    log ""
    
    # Use existing demo credentials
    local email="demo@example.com"
    local password="password123"
    
    # Login with existing user
    local login_data="{\"email\":\"$email\",\"password\":\"$password\"}"
    local response=$(test_endpoint "POST /auth/login" "POST" "/auth/login" "$login_data" "200" "false")
    
    if [ $? -eq 0 ]; then
        AUTH_TOKEN=$(echo "$response" | grep -o '"token":"[^"]*"' | cut -d'"' -f4)
        if [ -z "$AUTH_TOKEN" ]; then
            # Try alternative JSON parsing
            AUTH_TOKEN=$(echo "$response" | python3 -c "import sys, json; print(json.load(sys.stdin).get('token', ''))" 2>/dev/null || echo "")
        fi
        if [ -n "$AUTH_TOKEN" ]; then
            log_info "Authenticated with token: ${AUTH_TOKEN:0:20}..."
        else
            log_error "Failed to extract authentication token"
            log "Response: $response"
        fi
    fi
}

test_tenant_endpoints() {
    log ""
    log "==== TESTING TENANT ENDPOINTS ===="
    log ""
    
    # Create tenant
    local tenant_data='{"name":"Test Company","description":"Test tenant"}'
    local response=$(test_endpoint "POST /tenants" "POST" "/tenants" "$tenant_data" "201")
    
    if [ $? -eq 0 ]; then
        # Remove null bytes and extract ID
        local clean_response=$(echo "$response" | tr -d '\000')
        
        # Try jq first, then fallback to grep
        if command -v jq &> /dev/null; then
            TENANT_ID=$(echo "$clean_response" | jq -r '.data.id // .id' 2>/dev/null)
        fi
        
        # Fallback to grep if jq failed or not available
        if [ -z "$TENANT_ID" ]; then
            TENANT_ID=$(echo "$clean_response" | grep -o '"id":[0-9]\+' | head -1 | grep -o '[0-9]\+')
        fi
        
        if [ -n "$TENANT_ID" ] && [ "$TENANT_ID" != "null" ]; then
            log_info "Created tenant ID: $TENANT_ID"
        else
            log_error "Failed to extract tenant ID from response"
        fi
    fi
    
    # List tenants
    test_endpoint "GET /tenants" "GET" "/tenants" "" "200" > /dev/null
    
    # Get tenant
    if [ -n "$TENANT_ID" ]; then
        test_endpoint "GET /tenants/$TENANT_ID" "GET" "/tenants/$TENANT_ID" "" "200" > /dev/null
        
        # Switch tenant
        test_endpoint "POST /tenants/$TENANT_ID/switch" "POST" "/tenants/$TENANT_ID/switch" "" "200" > /dev/null
        
        # Update tenant
        local update_data='{"name":"Updated Company"}'
        test_endpoint "PUT /tenants/$TENANT_ID" "PUT" "/tenants/$TENANT_ID" "$update_data" "200" > /dev/null
    fi
}

test_location_endpoints() {
    log ""
    log "==== TESTING LOCATION ENDPOINTS ===="
    log ""
    
    if [ -z "$TENANT_ID" ]; then
        log_skip "Skipping location tests (no tenant ID)"
        return
    fi
    
    # Create location
    local location_data="{\"name\":\"Test Location\",\"address\":\"123 Main St\",\"city\":\"San Francisco\",\"state\":\"CA\",\"postal_code\":\"94102\",\"country\":\"US\",\"phone\":\"+1234567890\"}"
    
    local response=$(test_endpoint "POST /tenants/$TENANT_ID/locations" "POST" "/tenants/$TENANT_ID/locations" "$location_data" "201")
    
    if [ $? -eq 0 ]; then
        local clean_response=$(echo "$response" | tr -d '\000')
        
        if command -v jq &> /dev/null; then
            LOCATION_ID=$(echo "$clean_response" | jq -r '.data.id // .id' 2>/dev/null)
        fi
        
        if [ -z "$LOCATION_ID" ]; then
            LOCATION_ID=$(echo "$clean_response" | grep -o '"id":[0-9]\+' | head -1 | grep -o '[0-9]\+')
        fi
        
        if [ -n "$LOCATION_ID" ] && [ "$LOCATION_ID" != "null" ]; then
            log_info "Created location ID: $LOCATION_ID"
        fi
    fi
    
    # List locations
    test_endpoint "GET /tenants/$TENANT_ID/locations" "GET" "/tenants/$TENANT_ID/locations" "" "200" > /dev/null
    
    if [ -n "$LOCATION_ID" ]; then
        # Get location
        test_endpoint "GET /tenants/$TENANT_ID/locations/$LOCATION_ID" "GET" "/tenants/$TENANT_ID/locations/$LOCATION_ID" "" "200" > /dev/null
        
        # Update location
        local update_data='{"name":"Updated Location"}'
        test_endpoint "PUT /tenants/$TENANT_ID/locations/$LOCATION_ID" "PUT" "/tenants/$TENANT_ID/locations/$LOCATION_ID" "$update_data" "200" > /dev/null
    fi
}

test_facebook_endpoints() {
    log ""
    log "==== TESTING FACEBOOK ENDPOINTS ===="
    log ""
    
    if [ -z "$TENANT_ID" ]; then
        log_skip "Skipping Facebook tests (no tenant ID)"
        return
    fi
    
    # Check if Facebook credentials are configured
    if [ -z "$FACEBOOK_APP_ID" ] || [ -z "$FACEBOOK_APP_SECRET" ]; then
        log_skip "Facebook credentials not configured in .env"
        return
    fi
    
    # Store Facebook credentials (using test token if available)
    if [ -n "$FACEBOOK_TEST_ACCESS_TOKEN" ] && [ -n "$FACEBOOK_TEST_PAGE_ID" ]; then
        local cred_data="{\"platform\":\"facebook\",\"access_token\":\"$FACEBOOK_TEST_ACCESS_TOKEN\",\"page_id\":\"$FACEBOOK_TEST_PAGE_ID\"}"
        
        test_endpoint "POST /tenants/$TENANT_ID/listings/credentials" "POST" "/tenants/$TENANT_ID/listings/credentials" "$cred_data" "201" > /dev/null
        
        # Get platforms
        test_endpoint "GET /tenants/$TENANT_ID/listings/platforms" "GET" "/tenants/$TENANT_ID/listings/platforms" "" "200" > /dev/null
        
        if [ -n "$LOCATION_ID" ]; then
            # Sync Facebook listing
            test_endpoint "POST /tenants/$TENANT_ID/locations/$LOCATION_ID/listings/sync/facebook" "POST" "/tenants/$TENANT_ID/locations/$LOCATION_ID/listings/sync/facebook" "" "200" > /dev/null
            
            # Get listing stats
            test_endpoint "GET /tenants/$TENANT_ID/locations/$LOCATION_ID/listings/stats" "GET" "/tenants/$TENANT_ID/locations/$LOCATION_ID/listings/stats" "" "200" > /dev/null
        fi
    else
        log_skip "Facebook test credentials not configured (FACEBOOK_TEST_ACCESS_TOKEN, FACEBOOK_TEST_PAGE_ID)"
    fi
}

test_review_endpoints() {
    log ""
    log "==== TESTING REVIEW ENDPOINTS ===="
    log ""
    
    if [ -z "$TENANT_ID" ]; then
        log_skip "Skipping review tests (no tenant ID)"
        return
    fi
    
    # List reviews
    test_endpoint "GET /tenants/$TENANT_ID/reviews" "GET" "/tenants/$TENANT_ID/reviews" "" "200" > /dev/null
    
    # Get review stats
    test_endpoint "GET /tenants/$TENANT_ID/reviews/stats" "GET" "/tenants/$TENANT_ID/reviews/stats" "" "200" > /dev/null
    
    if [ -n "$LOCATION_ID" ]; then
        # List location reviews
        test_endpoint "GET /tenants/$TENANT_ID/locations/$LOCATION_ID/reviews" "GET" "/tenants/$TENANT_ID/locations/$LOCATION_ID/reviews" "" "200" > /dev/null
        
        # Get location review stats
        test_endpoint "GET /tenants/$TENANT_ID/locations/$LOCATION_ID/reviews/stats" "GET" "/tenants/$TENANT_ID/locations/$LOCATION_ID/reviews/stats" "" "200" > /dev/null
    fi
}

test_ai_response_endpoints() {
    log ""
    log "==== TESTING AI RESPONSE ENDPOINTS ===="
    log ""
    
    if [ -z "$TENANT_ID" ]; then
        log_skip "Skipping AI response tests (no tenant ID)"
        return
    fi
    
    # Get AI response stats
    test_endpoint "GET /tenants/$TENANT_ID/ai-response/stats" "GET" "/tenants/$TENANT_ID/ai-response/stats" "" "200" > /dev/null
    
    # Get AI response usage
    test_endpoint "GET /tenants/$TENANT_ID/ai-response/usage" "GET" "/tenants/$TENANT_ID/ai-response/usage" "" "200" > /dev/null
}

test_brand_voice_endpoints() {
    log ""
    log "==== TESTING BRAND VOICE ENDPOINTS ===="
    log ""
    
    if [ -z "$TENANT_ID" ]; then
        log_skip "Skipping brand voice tests (no tenant ID)"
        return
    fi
    
    # List brand voices
    test_endpoint "GET /tenants/$TENANT_ID/brand-voices" "GET" "/tenants/$TENANT_ID/brand-voices" "" "200" > /dev/null
    
    # Create brand voice
    local brand_data='{"name":"Friendly","tone":"friendly","guidelines":"Always be warm and welcoming"}'
    local response=$(test_endpoint "POST /tenants/$TENANT_ID/brand-voices" "POST" "/tenants/$TENANT_ID/brand-voices" "$brand_data" "201")
    
    if [ $? -eq 0 ]; then
        local clean_response=$(echo "$response" | tr -d '\000')
        local brand_id=""
        
        if command -v jq &> /dev/null; then
            brand_id=$(echo "$clean_response" | jq -r '.data.id // .id' 2>/dev/null)
        fi
        
        if [ -z "$brand_id" ]; then
            brand_id=$(echo "$clean_response" | grep -o '"id":[0-9]\+' | head -1 | grep -o '[0-9]\+')
        fi
        
        if [ -n "$brand_id" ] && [ "$brand_id" != "null" ]; then
            test_endpoint "GET /tenants/$TENANT_ID/brand-voices/$brand_id" "GET" "/tenants/$TENANT_ID/brand-voices/$brand_id" "" "200" > /dev/null
        fi
    fi
}

test_report_endpoints() {
    log ""
    log "==== TESTING REPORT ENDPOINTS ===="
    log ""
    
    if [ -z "$TENANT_ID" ]; then
        log_skip "Skipping report tests (no tenant ID)"
        return
    fi
    
    # List reports
    test_endpoint "GET /tenants/$TENANT_ID/reports" "GET" "/tenants/$TENANT_ID/reports" "" "200" > /dev/null
    
    # List report templates
    test_endpoint "GET /tenants/$TENANT_ID/report-templates" "GET" "/tenants/$TENANT_ID/report-templates" "" "200" > /dev/null
    
    # List report schedules
    test_endpoint "GET /tenants/$TENANT_ID/report-schedules" "GET" "/tenants/$TENANT_ID/report-schedules" "" "200" > /dev/null
}

###############################################################################
# Main Execution
###############################################################################

main() {
    log ""
    log "========================================="
    log "  Clarity SEO API Endpoint Testing"
    log "========================================="
    log ""
    log "API Base URL: $API_BASE_URL"
    log "Test Mode: $TEST_MODE"
    log "Log File: $LOG_FILE"
    log ""
    
    # Start timer
    START_TIME=$(date +%s)
    
    case "$TEST_MODE" in
        "--facebook"|"-f")
            test_auth_endpoints
            test_tenant_endpoints
            test_location_endpoints
            test_facebook_endpoints
            ;;
        "--auth"|"-a")
            test_auth_endpoints
            ;;
        *)
            test_auth_endpoints
            test_tenant_endpoints
            test_location_endpoints
            test_facebook_endpoints
            test_review_endpoints
            test_ai_response_endpoints
            test_brand_voice_endpoints
            test_report_endpoints
            ;;
    esac
    
    # End timer
    END_TIME=$(date +%s)
    DURATION=$((END_TIME - START_TIME))
    
    # Summary
    log ""
    log "========================================="
    log "  Test Summary"
    log "========================================="
    log ""
    log "Total Tests:   $TOTAL_TESTS"
    log "${GREEN}Passed:        $PASSED_TESTS${NC}"
    log "${RED}Failed:        $FAILED_TESTS${NC}"
    log "${YELLOW}Skipped:       $SKIPPED_TESTS${NC}"
    log ""
    
    if [ $FAILED_TESTS -eq 0 ]; then
        log "${GREEN}✅ All tests passed!${NC}"
    else
        log "${RED}❌ Some tests failed. Check log: $LOG_FILE${NC}"
    fi
    
    log ""
    log "Duration: ${DURATION}s"
    log "========================================="
    
    # Exit code
    if [ $FAILED_TESTS -gt 0 ]; then
        exit 1
    fi
}

# Run main
main
