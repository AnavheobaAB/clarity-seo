import gplay from 'google-play-scraper';

const packageName = process.argv[2] || 'acada.app.acada';

console.log(`Checking app details for: ${packageName}`);

gplay.app({appId: packageName})
  .then(app => {
    console.log('App found!');
    console.log(`Title: ${app.title}`);
    console.log(`Score: ${app.score}`);
    console.log(`Ratings: ${app.ratings}`);
    console.log(`Reviews: ${app.reviews}`);
    console.log(`URL: ${app.url}`);
  })
  .catch(e => {
    console.error('Error fetching app details:', e.message);
  });
