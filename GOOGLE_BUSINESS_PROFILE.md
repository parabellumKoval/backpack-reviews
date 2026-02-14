# Google Business Profile Reviews Integration

## Overview

This guide describes how to connect Google Business Profile, sync reviews into the local database, and use the API endpoints.

## Prerequisites

1. Create a Google Cloud project.
2. Enable **Business Profile API** for the project.
3. Configure the OAuth consent screen.
4. Create **OAuth Client ID** (type: Web application).
5. Add an authorized redirect URI (see below).

Recommended redirect URI:

```
https://YOUR_DOMAIN/admin/reviews/google/callback
```

## Admin Settings

Open the settings page:

```
/admin/settings/reviews
```

On the **Google Business Profile** tab, fill:

- `Google OAuth Client ID`
- `Google OAuth Client Secret`
- `OAuth Redirect URI`
- Enable **Google отзывы: включить**

Save the settings, then click **Подключить Google Business Profile** to start OAuth.

## OAuth Confirmation Flow

1. Click **Подключить Google Business Profile** in the settings.
2. Authorize the Google account with access to the business profile.
3. After approval, Google redirects back to the callback URL.
4. Tokens are stored in `ak_google_review_connections`, and an initial sync runs.

If the refresh token is missing, force consent again (the OAuth link uses `prompt=consent`).

## Sync Reviews

Manual sync:

```
php artisan reviews:google:sync
```

Sync a specific connection:

```
php artisan reviews:google:sync --connection=1
```

Scheduled sync is configured in `src/api/routes/console.php` to run hourly. Ensure the Laravel scheduler is running:

```
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
```

## API Usage

### List Google Reviews

```
GET /api/google-reviews
```

Query params:

- `per_page` (int)
- `page` (int)
- `location_id` (int)
- `location_name` (string)
- `account_id` (string)

Response includes pagination plus summary:

```json
{
  "data": [
    {
      "id": 1,
      "review_id": "123",
      "review_name": "accounts/123/locations/456/reviews/123",
      "rating": 5,
      "comment": "Great service!",
      "reviewer": {
        "name": "John",
        "photo_url": "https://...",
        "is_anonymous": false
      },
      "reply": {
        "comment": "Thanks!",
        "updated_at": "2025-02-18T12:00:00Z"
      },
      "location": {
        "id": 10,
        "title": "Main Store",
        "account_id": "123",
        "location_name": "accounts/123/locations/456"
      },
      "review_created_at": "2025-02-18T10:00:00Z",
      "review_updated_at": "2025-02-18T11:00:00Z"
    }
  ],
  "meta": {
    "total": 120,
    "avg_rating": 4.58
  }
}
```

### Get a single review

```
GET /api/google-reviews/{googleReview}
```

## Data Storage

Tables used:

- `ak_google_review_connections` (OAuth tokens and metadata)
- `ak_google_review_locations` (Business locations)
- `ak_google_reviews` (Downloaded reviews)
