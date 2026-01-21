#!/bin/bash

# Quick test with hardcoded credentials
# Run this if you don't want to modify .env

export FACEBOOK_APP_ID=540488388733766
export FACEBOOK_APP_SECRET=662728ec0ede964155a57a9d5bf89105  
export FACEBOOK_GRAPH_VERSION=v24.0
export FACEBOOK_BASE_URL=https://graph.facebook.com

# You still need to set a test access token
# Get it from: https://developers.facebook.com/tools/explorer/
if [ -z "$FACEBOOK_TEST_ACCESS_TOKEN" ]; then
    echo "Please set FACEBOOK_TEST_ACCESS_TOKEN environment variable"
    echo ""
    echo "Get a token from: https://developers.facebook.com/tools/explorer/"
    echo "Then run: FACEBOOK_TEST_ACCESS_TOKEN=your_token ./scripts/run-facebook-test.sh"
    exit 1
fi

echo "Using hardcoded credentials with App ID: $FACEBOOK_APP_ID"
echo ""

# Run the main test
./scripts/test-facebook-live.sh
