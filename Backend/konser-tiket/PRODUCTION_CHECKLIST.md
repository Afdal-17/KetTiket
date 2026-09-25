# Production Deployment Checklist - KetTiket

## ✅ Persiapan untuk Production

### 1. Environment Configuration
- [x] Midtrans credentials setup dengan environment variables
- [x] Production-ready error handling dan logging
- [x] Webhook signature validation
- [x] Database transaction safety dengan proper rollback

### 2. Security Hardening
- [x] Input validation pada semua endpoints
- [x] Authentication & authorization checks
- [x] SQL injection prevention dengan Eloquent ORM
- [x] XSS protection dengan output escaping
- [x] CSRF protection (Laravel default)

### 3. Payment Integration
- [x] Midtrans Snap.js integration
- [x] Environment-aware script loading (sandbox/production)
- [x] Comprehensive webhook processing
- [x] Transaction logging dan audit trail
- [x] Automatic ticket generation pada payment success
- [x] Order status management

### 4. UI/UX Improvements
- [x] Premium interface tanpa emoji berlebihan
- [x] Single gateway Midtrans flow (sesuai standar)
- [x] Google Maps integration dengan proper embedding
- [x] Responsive design dan mobile-friendly
- [x] Error handling dengan user-friendly messages

### 5. Performance Optimizations
- [x] Database indexing pada foreign keys
- [x] Lazy loading untuk relationships
- [x] Query optimization dengan eager loading
- [x] Asset minification (CSS/JS)

## 🚀 Deployment Steps

### 1. Server Requirements
```bash
PHP >= 8.1
MySQL >= 8.0
Nginx/Apache
Composer
Node.js (untuk asset building)
SSL Certificate (untuk HTTPS)
```

### 2. Production Environment Setup
```bash
# Clone repository
git clone [repository-url] kettiket-production
cd kettiket-production

# Install dependencies
composer install --no-dev --optimize-autoloader
npm install && npm run build

# Set permissions
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Environment configuration
cp .env.example .env
php artisan key:generate
```

### 3. Database Setup
```bash
# Run migrations
php artisan migrate --force

# Seed initial data
php artisan db:seed --force

# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 4. Web Server Configuration

#### Nginx Configuration
```nginx
server {
    listen 443 ssl http2;
    server_name yourdomain.com;
    root /path/to/kettiket/public;

    ssl_certificate /path/to/ssl/cert.pem;
    ssl_certificate_key /path/to/ssl/private.key;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### 5. Environment Variables untuk Production
```bash
APP_NAME=KetTiket
APP_ENV=production
APP_KEY=base64:GENERATED_KEY_HERE
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kettiket_production
DB_USERNAME=your_db_user
DB_PASSWORD=your_secure_password

# Midtrans Production
MIDTRANS_SERVER_KEY=Mid-server-YOUR_PRODUCTION_SERVER_KEY
MIDTRANS_CLIENT_KEY=Mid-client-YOUR_PRODUCTION_CLIENT_KEY
MIDTRANS_IS_PRODUCTION=true
MIDTRANS_IS_3DS=true

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email
MAIL_PASSWORD=your-email-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"

# Queue & Cache
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

## 🔧 Post-Deployment Verification

### 1. Functional Testing
- [ ] User registration/login
- [ ] Event browsing dan search
- [ ] Ticket selection dan seat mapping
- [ ] Google Maps integration
- [ ] Midtrans payment flow
- [ ] Webhook processing
- [ ] Ticket generation
- [ ] QR code scanning

### 2. Performance Testing
- [ ] Page load speeds < 3 seconds
- [ ] API response times < 500ms
- [ ] Database query optimization
- [ ] Memory usage monitoring

### 3. Security Testing
- [ ] SSL certificate validation
- [ ] XSS protection testing
- [ ] SQL injection testing
- [ ] Authentication bypass testing
- [ ] File upload security

## 📊 Monitoring Setup

### 1. Application Monitoring
```bash
# Install monitoring tools
composer require laravel/horizon
composer require spatie/laravel-backup

# Setup log monitoring
tail -f storage/logs/laravel.log
```

### 2. Database Monitoring
- Monitor slow queries
- Set up automated backups
- Track connection pool usage

### 3. Payment Monitoring
- Midtrans dashboard monitoring
- Webhook success/failure rates
- Transaction reconciliation

## 🚨 Emergency Procedures

### 1. Rollback Plan
```bash
# Database rollback
php artisan migrate:rollback --step=1

# Code rollback
git checkout previous-stable-tag
composer install --no-dev
php artisan config:cache
```

### 2. Maintenance Mode
```bash
# Enable maintenance
php artisan down --message="Scheduled maintenance" --retry=60

# Disable maintenance
php artisan up
```

## 📞 Support Contacts
- Technical Lead: [your-email]
- Midtrans Support: support@midtrans.com
- Server Admin: [server-admin-email]

---
**Status**: ✅ READY FOR PRODUCTION DEPLOYMENT
**Last Updated**: 2026-09-15
**Version**: v1.0.0