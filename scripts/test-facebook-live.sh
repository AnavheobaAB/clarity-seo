#!/bin/bash

# Facebook Integration Test Script
# Tests REAL Facebook API calls with credentials from .env

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}  Facebook Live Integration Test${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo

# Load .env file if it exists
if [ -f .env ]; then
    echo -e "${BLUE}Loading from .env file...${NC}"
    export $(cat .env | grep -v '^#' | grep -v '^$' | xargs)
fi

# Check required variables (now from env or .env)
if [ -z "$FACEBOOK_APP_ID" ]; then
    echo -e "${RED}✗ FACEBOOK_APP_ID not set${NC}"
    echo -e "  Set it in .env or pass as environment variable"
    exit 1
fi

if [ -z "$FACEBOOK_APP_SECRET" ]; then
    echo -e "${RED}✗ FACEBOOK_APP_SECRET not set${NC}"
    echo -e "  Set it in .env or pass as environment variable"
    exit 1
fi

echo -e "${GREEN}✓ Using credentials${NC}"
echo -e "  App ID: $FACEBOOK_APP_ID"
echo -e "  Graph Version: ${FACEBOOK_GRAPH_VERSION:-v24.0}"
echo

# Step 1: Get User Access Token
echo -e "${YELLOW}Step 1: Getting User Access Token${NC}"

if [ -z "$FACEBOOK_TEST_ACCESS_TOKEN" ]; then
    echo -e "${RED}✗ FACEBOOK_TEST_ACCESS_TOKEN not set${NC}"
    echo -e "  Please generate a token at: https://developers.facebook.com/tools/explorer/"
    echo -e "  Add these permissions: pages_show_list, pages_read_engagement, pages_manage_engagement"
    exit 1
fi

ACCESS_TOKEN="$FACEBOOK_TEST_ACCESS_TOKEN"
echo -e "${GREEN}✓ Access token loaded from .env${NC}"
echo

# Step 2: Verify token and get user pages
echo -e "${YELLOW}Step 2: Fetching User's Facebook Pages${NC}"
PAGES_RESPONSE=$(curl -s "https://graph.facebook.com/${FACEBOOK_GRAPH_VERSION:-v24.0}/me/accounts?access_token=$ACCESS_TOKEN")

# Check for error
if echo "$PAGES_RESPONSE" | grep -q '"error"'; then
    echo -e "${RED}✗ Failed to fetch pages${NC}"
    echo "$PAGES_RESPONSE" | jq '.'
    exit 1
fi

# Extract pages
PAGES=$(echo "$PAGES_RESPONSE" | jq -r '.data[] | "\(.name) (ID: \(.id))"')
if [ -z "$PAGES" ]; then
    echo -e "${RED}✗ No pages found for this user${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Found pages:${NC}"
echo "$PAGES" | while read line; do echo "  - $line"; done
echo

# Get first page ID and access token
FIRST_PAGE_ID=$(echo "$PAGES_RESPONSE" | jq -r '.data[0].id')
FIRST_PAGE_NAME=$(echo "$PAGES_RESPONSE" | jq -r '.data[0].name')
PAGE_ACCESS_TOKEN=$(echo "$PAGES_RESPONSE" | jq -r '.data[0].access_token')

if [ -z "$FIRST_PAGE_ID" ] || [ "$FIRST_PAGE_ID" == "null" ]; then
    echo -e "${RED}✗ Could not extract page ID${NC}"
    exit 1
fi

echo -e "${BLUE}Using page: ${GREEN}$FIRST_PAGE_NAME${NC} ${BLUE}(ID: $FIRST_PAGE_ID)${NC}"
echo

# Step 3: Fetch reviews (ratings)
echo -e "${YELLOW}Step 3: Fetching Reviews from Facebook Page${NC}"
REVIEWS_URL="https://graph.facebook.com/${FACEBOOK_GRAPH_VERSION:-v24.0}/$FIRST_PAGE_ID/ratings"
REVIEWS_RESPONSE=$(curl -s "$REVIEWS_URL?access_token=$PAGE_ACCESS_TOKEN&fields=rating,review_text,recommendation_type,created_time,open_graph_story,reviewer{name}")

# Check for error
if echo "$REVIEWS_RESPONSE" | grep -q '"error"'; then
    echo -e "${RED}✗ Failed to fetch reviews${NC}"
    echo "$REVIEWS_RESPONSE" | jq '.'
    echo
    echo -e "${YELLOW}Note: If you see 'Unsupported get request', this page may not have reviews enabled.${NC}"
    exit 1
fi

# Count reviews
REVIEW_COUNT=$(echo "$REVIEWS_RESPONSE" | jq '.data | length')
echo -e "${GREEN}✓ Found $REVIEW_COUNT reviews${NC}"
echo

if [ "$REVIEW_COUNT" -eq 0 ]; then
    echo -e "${YELLOW}⚠ No reviews found on this page${NC}"
    echo -e "  You can test response publishing by manually creating a review."
    exit 0
fi

# Display reviews
echo -e "${BLUE}Recent reviews:${NC}"
echo "$REVIEWS_RESPONSE" | jq -r '.data[] | "  ⭐ \(.rating)/5 - \(.reviewer.name // "Anonymous"): \(.review_text // .recommendation_type // "No text")"' | head -5
echo

# Step 4: Test responding to a review
echo -e "${YELLOW}Step 4: Testing Response Publishing${NC}"

# Get first review's rating ID
FIRST_RATING_ID=$(echo "$REVIEWS_RESPONSE" | jq -r '.data[0].open_graph_story.id // empty')

if [ -z "$FIRST_RATING_ID" ] || [ "$FIRST_RATING_ID" == "null" ]; then
    echo -e "${YELLOW}⚠ Cannot find rating ID for first review (may not have open_graph_story)${NC}"
    echo -e "  Skipping response test."
else
    echo -e "  Rating ID: $FIRST_RATING_ID"
    
    # Try to post a test response
    TEST_COMMENT="Thank you for your feedback! (Test response from Clarity SEO)"
    
    echo -e "  Posting test response..."
    RESPONSE_URL="https://graph.facebook.com/${FACEBOOK_GRAPH_VERSION:-v24.0}/$FIRST_PAGE_ID/ratings/$FIRST_RATING_ID"
    RESPONSE_RESULT=$(curl -s -X POST "$RESPONSE_URL" \
        -d "access_token=$PAGE_ACCESS_TOKEN" \
        -d "comment=$TEST_COMMENT")
    
    # Check for success
    if echo "$RESPONSE_RESULT" | grep -q '"success"'; then
        echo -e "${GREEN}✓ Successfully posted response to review!${NC}"
        echo -e "  Response: \"$TEST_COMMENT\""
    elif echo "$RESPONSE_RESULT" | grep -q '"error"'; then
        ERROR_MSG=$(echo "$RESPONSE_RESULT" | jq -r '.error.message')
        ERROR_CODE=$(echo "$RESPONSE_RESULT" | jq -r '.error.code')
        echo -e "${RED}✗ Failed to post response${NC}"
        echo -e "  Error ($ERROR_CODE): $ERROR_MSG"
        echo
        echo -e "${YELLOW}Common issues:${NC}"
        echo -e "  - Missing 'pages_manage_engagement' permission"
        echo -e "  - Page is not eligible for reviews"
        echo -e "  - Token has expired"
    else
        echo -e "${YELLOW}⚠ Unexpected response:${NC}"
        echo "$RESPONSE_RESULT" | jq '.'
    fi
fi

echo
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}✓ Integration test completed${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo
echo -e "${YELLOW}Summary:${NC}"
echo -e "  ✓ Loaded credentials from .env"
echo -e "  ✓ Verified Facebook API connection"
echo -e "  ✓ Fetched user pages"
echo -e "  ✓ Retrieved reviews ($REVIEW_COUNT found)"
if [ ! -z "$FIRST_RATING_ID" ] && [ "$FIRST_RATING_ID" != "null" ]; then
    echo -e "  ✓ Tested response publishing"
fi
echo
