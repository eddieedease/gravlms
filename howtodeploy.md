# How to Deploy the LMS Application

This guide explains how to build and deploy your LMS application to a production server using FileZilla.

## Prerequisites

Before deploying, ensure you have:
- Node.js and npm installed locally
- FileZilla or another FTP/SFTP client
- Access to your production server (FTP/SFTP credentials)
- A web server with PHP 8.0+ and MySQL/MariaDB
- Composer installed on your production server (or ability to upload vendor folder)

## Step 1: Build the Application

Run the following command in your project root to create a production build:

```bash
npm run build
```

This will create a `dist/lms-mockup/browser/` folder containing:
- **Frontend files** (index.html, JavaScript, CSS) at the root
- **backend/** folder with all PHP API files
- **uploads/** folder for user-uploaded images
- **i18n/** folder with translation files

## Step 2: Configure for Production

### 2.1 Backend Configuration

1. Navigate to `dist/lms-mockup/browser/backend/`
2. Copy `config.example.php` to `config.php`
3. Edit `config.php` with your production values:

```php
return [
    'database' => [
        'host' => 'localhost',           // Your database host
        'name' => 'your_database_name',  // Your database name
        'user' => 'your_db_username',    // Your database username
        'password' => 'your_db_password' // Your database password
    ],
    'jwt' => [
        'secret' => 'CHANGE-THIS-TO-A-RANDOM-SECRET-KEY', // IMPORTANT!
    ],
    'app' => [
        'environment' => 'production',
        'debug' => false
    ]
];
```

> **⚠️ IMPORTANT**: Generate a strong, random JWT secret key for production!

### 2.2 Frontend Configuration

1. Navigate to `dist/lms-mockup/browser/`
2. Edit `config.js` to point to your production API:

```javascript
window.APP_CONFIG = {
  apiUrl: 'https://yourdomain.com/backend/api'  // Update this!
};
```

**Common configurations:**
- Same server: `apiUrl: '/backend/api'` (relative path)
- Subdomain: `apiUrl: 'https://api.yourdomain.com/api'`
- Different server: `apiUrl: 'https://yourserver.com/api'`

## Step 3: Upload Files via FileZilla

### 3.1 Connect to Your Server

1. Open FileZilla
2. Enter your server credentials:
   - Host: `ftp.yourdomain.com` or `sftp://yourdomain.com`
   - Username: Your FTP/SFTP username
   - Password: Your FTP/SFTP password
   - Port: 21 (FTP) or 22 (SFTP)
3. Click "Quickconnect"

### 3.2 Upload the Application

1. On your local machine (left panel), navigate to: `dist/lms-mockup/browser/`
2. On the server (right panel), navigate to your web root (usually `public_html/` or `www/`)
3. Select **all files and folders** from the browser folder
4. Drag and drop them to the server, or right-click → Upload

**Files to upload:**
```
├── backend/          (entire folder)
├── uploads/          (entire folder with .gitkeep)
├── i18n/            (translation files)
├── index.html
├── config.js        (after editing!)
├── main-*.js
├── styles-*.css
├── favicon.ico
└── ...
```

> **📝 Note**: The upload may take several minutes depending on your connection speed.

### 3.3 Set Folder Permissions

After uploading, set the correct permissions for the uploads folder:

1. Right-click on the `uploads/` folder in FileZilla
2. Select "File permissions..."
3. Set permissions to `755` or `777` (depending on your server configuration)
4. Check "Recurse into subdirectories"
5. Click OK

## Step 4: Database Setup

### 4.1 Create the Database

1. Log into your hosting control panel (cPanel, Plesk, etc.)
2. Navigate to MySQL Databases or phpMyAdmin
3. Create a new database (note the name for config.php)
4. Create a database user with a strong password
5. Grant the user all privileges on the database

### 4.2 Initialize Database Tables

**Option A: Using phpMyAdmin**
1. Access phpMyAdmin from your hosting control panel
2. Select your database
3. Navigate to your application: `https://yourdomain.com/backend/init_db.php`
4. This will create all necessary tables

**Option B: Manual SQL Import**
1. Run the initialization script by accessing: `https://yourdomain.com/backend/init_db.php`
2. Check for success message

> **⚠️ Security**: After initialization, consider removing or protecting `init_db.php`

## Step 5: Configure Web Server

### 5.1 Apache (.htaccess)

The backend already includes an `.htaccess` file. Ensure mod_rewrite is enabled on your server.

If you need to create one for the root, add this to the root `.htaccess`:

```apache
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /
  RewriteRule ^index\.html$ - [L]
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule . /index.html [L]
</IfModule>
```

### 5.2 Nginx

If using Nginx, add this to your server block:

```nginx
location / {
    try_files $uri $uri/ /index.html;
}

location /backend {
    try_files $uri $uri/ /backend/index.php?$query_string;
}
```

## Step 6: Post-Deployment Verification

### 6.1 Test the Frontend

1. Visit `https://yourdomain.com`
2. You should see the LMS login page
3. Open browser console (F12) and check for errors
4. Verify that `window.APP_CONFIG` is defined

### 6.2 Test the Backend API

1. Visit `https://yourdomain.com/backend/api/test` (or your configured path)
2. You should see a JSON response
3. If you get a 500 error, check:
   - Database credentials in `config.php`
   - PHP error logs on your server
   - File permissions

### 6.3 Test Login

1. Create a test user via phpMyAdmin or the admin interface
2. Try logging in
3. Verify JWT token is generated and stored

### 6.4 Test File Upload

1. Log in as an admin/editor
2. Go to the editor
3. Try uploading an image
4. Verify it appears in the `uploads/` folder
5. Check that the image displays in the preview

## Troubleshooting

### Issue: White screen / Blank page
- **Solution**: Check browser console for errors. Likely a config.js issue or incorrect API URL.

### Issue: API calls fail with CORS errors
- **Solution**: Update CORS settings in `backend/config.php` to include your domain.

### Issue: Database connection failed
- **Solution**: Verify database credentials in `backend/config.php`. Check if database exists and user has permissions.

### Issue: Images won't upload
- **Solution**: 
  - Check `uploads/` folder permissions (should be 755 or 777)
  - Verify PHP upload limits in `php.ini` (upload_max_filesize, post_max_size)
  - Check server error logs

### Issue: 404 errors for API routes
- **Solution**: 
  - Ensure mod_rewrite is enabled (Apache)
  - Check `.htaccess` file exists in backend folder
  - Verify web server configuration

### Issue: JWT authentication fails
- **Solution**: 
  - Ensure JWT secret is set in `config.php`
  - Check that Authorization headers are being sent
  - Verify server supports Authorization headers

## Updating the Application

To deploy updates:

1. Make changes locally
2. Run `npm run build`
3. Update `config.js` in the dist folder (if needed)
4. Upload only the changed files via FileZilla
5. Clear browser cache and test

## Security Checklist

- [ ] Changed JWT secret to a strong random value
- [ ] Set `debug` to `false` in backend config
- [ ] Database user has minimum required permissions
- [ ] Removed or protected `init_db.php` after first run
- [ ] HTTPS is enabled on your domain
- [ ] File upload folder has appropriate permissions (not 777 if possible)
- [ ] Backend config.php is not publicly accessible
- [ ] Regular backups are configured

## File Structure on Server

```
public_html/  (or www/)
├── backend/
│   ├── api/
│   │   ├── auth.php
│   │   ├── courses.php
│   │   ├── db.php
│   │   └── ...
│   ├── vendor/
│   ├── config.php          ← Edit this!
│   ├── config.example.php
│   ├── index.php
│   └── .htaccess
├── uploads/
│   └── .gitkeep
├── i18n/
│   ├── en.json
│   └── nl.json
├── config.js               ← Edit this!
├── index.html
├── main-*.js
├── styles-*.css
└── favicon.ico
```

## Support

If you encounter issues not covered in this guide:
1. Check server error logs (usually in cPanel or via SSH)
2. Check browser console for frontend errors
3. Verify all configuration files are correct
4. Ensure all required PHP extensions are installed (PDO, MySQL, etc.)
