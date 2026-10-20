# Frontend Setup Guide

The Sail Friday backend now serves both the REST API and a frontend application.

📖 **See also:**
- [Setup Guide](SETUP.md) - Complete setup instructions for the backend
- [API Reference](API.md) - Complete documentation of all API endpoints
- [Developer Guide](DEVELOPER_GUIDE.md) - Understanding the backend architecture

## Directory Structure

```
public/
├── index.php           # Entry point (handles routing)
├── .htaccess          # Apache configuration
└── app/               # Frontend application
    ├── index.html     # Main HTML file
    ├── js/            # JavaScript files
    │   └── main.js
    ├── css/           # Stylesheets
    │   └── styles.css
    └── assets/        # Images, fonts, etc.
```

## How It Works

### Routing Logic

1. **API Routes** (`/api/*`) → Handled by backend controllers
2. **Static Files** (`/app/js/*`, `/app/css/*`, `/app/assets/*`) → Served directly
3. **All Other Routes** (`/`, `/events`, `/profile`, etc.) → Serve `index.html`

This allows your frontend SPA to handle client-side routing.

### Example URLs

- `http://localhost:8000/` → Serves frontend
- `http://localhost:8000/events` → Serves frontend (your JS handles routing)
- `http://localhost:8000/api/events` → API endpoint (JSON response)
- `http://localhost:8000/app/js/main.js` → Static JS file

## Using Your Own Frontend

To replace the sample frontend with your own:

1. **Copy your files** into `public/app/`:
   ```bash
   cp -r /path/to/your/frontend/* public/app/
   ```

2. **Ensure your main HTML file is named** `index.html`

3. **Update paths** in your HTML to use absolute paths:
   ```html
   <script src="/app/js/your-script.js"></script>
   <link rel="stylesheet" href="/app/css/your-styles.css">
   ```

## API Integration Example

Sign in happens at the sailConnect auth service (see [SSO.md](SSO.md)); the browser then sends its session
cookie with every same-site request, so no token handling is needed:

```javascript
const profileResponse = await fetch('/api/users/me', { credentials: 'same-origin' });

if (profileResponse.status === 401) {
    // Not signed in: go to the auth service and come back here afterwards
    window.location.href = 'https://auth.sailConnect.org/signin?return=' + encodeURIComponent(window.location.href);
}
```

## Testing the Setup

1. **Start the development server:**
   ```bash
   php -S localhost:8000 -t public
   ```

2. **Visit the frontend:**
   - Open http://localhost:8000 in your browser
   - You should see the sample frontend with a list of events

3. **Test the API:**
   - Visit http://localhost:8000/api/events
   - You should see JSON response with event data

## Sample Frontend Features

The included sample frontend demonstrates:

- ✅ Fetching data from `/api/events`
- ✅ Displaying events in a grid layout
- ✅ Error handling
- ✅ Responsive design
- ✅ Basic styling

Replace it with your own implementation as needed.

## Important Notes

### API Base URL

All API endpoints are prefixed with `/api`. Make sure your frontend uses:

```javascript
const API_BASE = '/api';
```

### Authentication

Protected endpoints require the sailConnect session cookie, which the browser sends automatically:

```javascript
fetch('/api/users/me', { credentials: 'same-origin' });
```

### CORS

CORS is configured in the backend. If you need to adjust allowed origins, update `config/config.php`:

```php
'cors' => [
    'allowed_origins' => explode(',', getenv('CORS_ALLOWED_ORIGINS') ?: '*'),
    // ...
]
```

### Client-Side Routing

For client-side routing (e.g., React Router, Vue Router):

1. The server serves `index.html` for all non-API, non-file routes
2. Your JavaScript router handles URL changes
3. Use HTML5 History API (not hash routing)

Example (vanilla JS):

```javascript
// Handle navigation
document.addEventListener('click', (e) => {
    if (e.target.matches('[data-link]')) {
        e.preventDefault();
        navigateTo(e.target.href);
    }
});

function navigateTo(url) {
    history.pushState(null, null, url);
    router();
}

window.addEventListener('popstate', router);

function router() {
    const path = window.location.pathname;

    // Your routing logic here
    if (path === '/') {
        showHomePage();
    } else if (path === '/events') {
        showEventsPage();
    }
    // ...
}
```

## Deployment

### Apache/Lightsail

The `.htaccess` file is configured to:
- Serve static files directly (better performance)
- Cache assets appropriately
- Gzip compress text content
- Route other requests through PHP

No additional configuration needed.

### Nginx

If using Nginx, add this to your configuration:

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/html/sailConnect/sailFriday/public;
    index index.php index.html;

    # API routes
    location /api {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Static files
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Frontend (SPA)
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP processing
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## Troubleshooting

### Frontend Not Loading

1. Check that `public/app/index.html` exists
2. Verify file permissions (readable by web server)
3. Check browser console for errors

### API Not Working

1. Ensure paths start with `/api`
2. Check CORS configuration
3. Verify you are signed in at the auth service (the `sc_session` cookie is present) for protected endpoints

### Static Files Not Loading

1. Use absolute paths: `/app/js/file.js` (not `./js/file.js`)
2. Check `.htaccess` is being read (Apache)
3. Verify file permissions

### 404 Errors

1. Ensure `.htaccess` rewrite rules are active
2. Check Apache has `mod_rewrite` enabled
3. Verify `AllowOverride All` in Apache config

## Next Steps

1. Replace sample frontend with your HTML/JS files
2. Test API integration
3. Implement authentication flow
4. Add your business logic

For API documentation, see [README.md](README.md) and [CLAUDE.md](CLAUDE.md).
