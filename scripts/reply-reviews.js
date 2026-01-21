import gplay from 'google-play-scraper';
import { google } from 'googleapis';
import fs from 'fs';
import path from 'path';

// Configuration
const PACKAGE_NAME = process.argv[2] || 'acada.app.acada';
const REPLY_TEXT = process.argv[3] || 'Thank you for your feedback! We are constantly improving the app.';
const COUNTRY = process.argv[4] || 'us';

const KEY_FILE_PATH = path.join(process.cwd(), 'storage/app/private/edcada-credentials.json');

async function main() {
    console.log(`Target App: ${PACKAGE_NAME}`);
    console.log(`Country: ${COUNTRY}`);
    
    // Scrape with different sort orders
    const sortOptions = {
        'NEWEST': gplay.sort.NEWEST,
        'HELPFUL': gplay.sort.HELPFUL,
        'RATING': gplay.sort.RATING
    };

    let allReviews = [];

    for (const [name, sortValue] of Object.entries(sortOptions)) {
        console.log(`Fetching reviews (Sort: ${name})...`);
        try {
            const reviews = await gplay.reviews({
                appId: PACKAGE_NAME,
                sort: sortValue,
                num: 100,
                country: COUNTRY
            });
            console.log(`  Found ${reviews.data.length} reviews.`);
            allReviews = allReviews.concat(reviews.data);
        } catch (e) {
            console.log(`  Error: ${e.message}`);
        }
    }

    if (allReviews.length === 0) {
        console.log("No reviews found across any sort option.");
        return;
    }

    // Authenticate
    if (!fs.existsSync(KEY_FILE_PATH)) {
        console.error(`Error: Credentials file not found at ${KEY_FILE_PATH}`);
        process.exit(1);
    }

    const auth = new google.auth.GoogleAuth({
        keyFile: KEY_FILE_PATH,
        scopes: ['https://www.googleapis.com/auth/androidpublisher'],
    });

    const androidPublisher = google.androidpublisher({
        version: 'v3',
        auth,
    });

    // Reply
    for (const review of allReviews) {
        console.log('------------------------------------------------');
        console.log(`Author: ${review.userName}`);
        console.log(`Rating: ${review.score}`);
        console.log(`Content: ${review.text}`);
        console.log(`ID: ${review.id}`);

        if (review.replyText) {
            console.log(`[SKIP] Already replied.`);
            continue;
        }

        console.log(`Attempting to reply...`);
        try {
            await androidPublisher.reviews.reply({
                packageName: PACKAGE_NAME,
                reviewId: review.id,
                requestBody: {
                    replyText: REPLY_TEXT
                }
            });
            console.log(`[SUCCESS] Reply sent!`);
        } catch (apiError) {
            console.error(`[FAILED] API Error: ${apiError.message}`);
        }
    }
}

main();
