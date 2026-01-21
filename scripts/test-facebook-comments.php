#!/usr/bin/env php
<?php

/**
 * Test Facebook Comments API
 * Verifies we can read posts and comments with current permissions
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  Facebook Comments API Test\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";

// Get credential
$credential = App\Models\PlatformCredential::where('platform', 'facebook')->first();

if (!$credential) {
    echo "❌ No Facebook credentials found\n";
    exit(1);
}

$pageId = $credential->getPageId();
$pageToken = $credential->metadata['page_access_token'] ?? $credential->access_token;

echo "✓ Using page: $pageId\n";
echo "\n";

// Step 1: Fetch recent posts
echo "📥 Fetching recent posts...\n";

$graphVersion = config('facebook.graph_version', 'v24.0');
$postsUrl = "https://graph.facebook.com/$graphVersion/$pageId/feed";

$context = stream_context_create([
    'http' => [
        'ignore_errors' => true
    ]
]);

$response = file_get_contents($postsUrl . '?' . http_build_query([
    'access_token' => $pageToken,
    'fields' => 'id,message,created_time,from',
    'limit' => 10
]), false, $context);

$postsData = json_decode($response, true);

if (isset($postsData['error'])) {
    echo "❌ Error fetching posts:\n";
    echo "  " . $postsData['error']['message'] . "\n";
    echo "  Code: " . $postsData['error']['code'] . "\n";
    exit(1);
}

$posts = $postsData['data'] ?? [];
echo "✓ Found " . count($posts) . " posts\n";
echo "\n";

if (empty($posts)) {
    echo "⚠️  No posts found. Create a post on your page first!\n";
    exit(0);
}

// Display posts
echo "Recent posts:\n";
foreach (array_slice($posts, 0, 5) as $index => $post) {
    $message = isset($post['message']) ? substr($post['message'], 0, 50) . '...' : '[No text]';
    echo "  [" . ($index + 1) . "] " . $post['created_time'] . " - $message\n";
    echo "      Post ID: " . $post['id'] . "\n";
}
echo "\n";

// Step 2: Fetch comments for first post
echo "📥 Fetching comments from first post...\n";
$firstPost = $posts[0];
$postId = $firstPost['id'];

$commentsUrl = "https://graph.facebook.com/$graphVersion/$postId/comments";

$response = file_get_contents($commentsUrl . '?' . http_build_query([
    'access_token' => $pageToken,
    'fields' => 'id,from,message,created_time,attachment',
    'limit' => 25
]));

$commentsData = json_decode($response, true);

if (isset($commentsData['error'])) {
    echo "❌ Error fetching comments:\n";
    echo "  " . $commentsData['error']['message'] . "\n";
    echo "  Code: " . $commentsData['error']['code'] . "\n";
    exit(1);
}

$comments = $commentsData['data'] ?? [];
echo "✓ Found " . count($comments) . " comments\n";
echo "\n";

if (empty($comments)) {
    echo "⚠️  No comments on this post\n";
    echo "   Try another post or add a comment manually\n";
    exit(0);
}

// Display comments
echo "Comments:\n";
foreach ($comments as $index => $comment) {
    $author = $comment['from']['name'] ?? 'Unknown';
    $message = substr($comment['message'], 0, 60);
    echo "  [" . ($index + 1) . "] $author: $message...\n";
    echo "      Comment ID: " . $comment['id'] . "\n";
}
echo "\n";

// Step 3: Test replying to first comment
echo "📤 Testing reply to comment...\n";
$firstComment = $comments[0];
$commentId = $firstComment['id'];

echo "Do you want to post a test reply? (yes/no): ";
$confirm = trim(fgets(STDIN));

if (strtolower($confirm) === 'yes') {
    $replyText = "Thanks for your comment! (Test reply from Clarity SEO)";

    $replyUrl = "https://graph.facebook.com/$graphVersion/$commentId/comments";

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query([
                'access_token' => $pageToken,
                'message' => $replyText
            ])
        ]
    ]);

    $response = @file_get_contents($replyUrl, false, $context);

    if ($response === false) {
        echo "❌ Failed to post reply\n";
    } else {
        $result = json_decode($response, true);

        if (isset($result['id'])) {
            echo "✅ SUCCESS! Reply posted!\n";
            echo "   Reply ID: " . $result['id'] . "\n";
            echo "\n";
            echo "🎉 Go check your Facebook page to see the reply!\n";
        } elseif (isset($result['error'])) {
            echo "❌ Error: " . $result['error']['message'] . "\n";
        }
    }
} else {
    echo "⏭️  Skipped test reply\n";
}

echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✅ API test complete!\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";
