# Negoride Canada API 🚀

Backend RESTful API service powering the Negoride Canada rideshare platform.

## Overview

Laravel-based backend API providing comprehensive rideshare services for the Canadian market, including user authentication, ride matching, real-time tracking, payment processing, and comprehensive admin management tools.

## Features

- 🔐 **JWT Authentication** - Secure token-based authentication
- 👥 **User Management** - Riders and drivers with role-based access
- 🚗 **Trip Management** - Create, book, and manage rides
- 💰 **Payment Processing** - Secure payment integration (CAD)
- 📍 **Location Services** - Real-time GPS tracking across Canada
- 💬 **Chat System** - In-app messaging between riders and drivers
- 🔔 **Push Notifications** - OneSignal integration for instant updates
- 📊 **Admin Dashboard** - Comprehensive Laravel Admin panel
- 📧 **Email Notifications** - Automated email system
- 🔄 **Real-time Updates** - WebSocket support for live features
- 📱 **OTP Verification** - Secure phone number verification
- ⭐ **Rating System** - Two-way rating and review system

## Tech Stack

- **Framework:** Laravel 8.x
- **PHP:** 7.3+ / 8.0+
- **Database:** MySQL 8.0+
- **Authentication:** JWT (tymon/jwt-auth)
- **Admin Panel:** Laravel Admin (encore/laravel-admin)
- **PDF Generation:** DomPDF (barryvdh/laravel-dompdf)
- **Push Notifications:** OneSignal (berkayk/onesignal-laravel)
- **CORS:** Laravel CORS (fruitcake/laravel-cors)

## Requirements

- PHP >= 7.3
- MySQL >= 5.7 or MariaDB >= 10.2
- Composer
- Node.js & NPM (for asset compilation)
- Apache/Nginx web server

## Installation

### 1. Clone Repository
```bash
git clone https://github.com/yourusername/negoride-canada-api.git
cd negoride-canada-api
```

### 2. Install Dependencies
```bash
composer install
npm install
```

### 3. Environment Setup
```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Generate JWT secret
php artisan jwt:secret
```

### 4. Configure Environment
Edit `.env` file with your settings:

```env
APP_NAME="Negoride Canada"
APP_ENV=local
APP_URL=http://localhost:8888/negoride-canada-api

# Database
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=negoride_canada
DB_USERNAME=root
DB_PASSWORD=root

# Canadian Timezone
APP_TIMEZONE=America/Toronto

# OneSignal (Canadian App)
ONESIGNAL_APP_ID=your_onesignal_app_id
ONESIGNAL_REST_API_KEY=your_rest_api_key

# MAMP MySQL Socket (macOS only)
DB_SOCKET=/Applications/MAMP/tmp/mysql/mysql.sock
```

### 5. Database Setup
```bash
# Create database
mysql -u root -p -e "CREATE DATABASE negoride_canada CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Run migrations
php artisan migrate

# Seed database (optional)
php artisan db:seed
```

### 6. Admin Panel Setup
```bash
# Install Laravel Admin
php artisan admin:install

# Create admin user
php artisan admin:create-user
```

### 7. Start Development Server
```bash
php artisan serve
```

Visit: `http://localhost:8000`

## API Documentation

### Base URL
```
Development: http://localhost:8888/negoride-canada-api/api
Production: https://api.negoride.ca/api
```

### Authentication

All authenticated endpoints require a JWT token in the header:
```
Authorization: Bearer {your_jwt_token}
```

### Endpoints

#### Authentication
```
POST   /api/auth/register        # User registration
POST   /api/auth/login           # User login
POST   /api/auth/verify-otp      # OTP verification
POST   /api/auth/resend-otp      # Resend OTP
POST   /api/auth/logout          # User logout
GET    /api/auth/me              # Get authenticated user
POST   /api/auth/refresh         # Refresh token
```

#### User Management
```
GET    /api/users                # List users
GET    /api/users/{id}           # Get user details
PUT    /api/users/{id}           # Update user
DELETE /api/users/{id}           # Delete user
POST   /api/users/avatar         # Upload avatar
```

#### Trips
```
GET    /api/trips                # List available trips
POST   /api/trips                # Create new trip
GET    /api/trips/{id}           # Trip details
PUT    /api/trips/{id}           # Update trip
DELETE /api/trips/{id}           # Cancel trip
GET    /api/trips/my-trips       # User's trips
GET    /api/trips/nearby         # Trips near location
```

#### Bookings
```
GET    /api/bookings             # List bookings
POST   /api/bookings             # Create booking
GET    /api/bookings/{id}        # Booking details
PUT    /api/bookings/{id}        # Update booking
DELETE /api/bookings/{id}        # Cancel booking
POST   /api/bookings/{id}/accept # Accept booking (driver)
POST   /api/bookings/{id}/reject # Reject booking (driver)
```

#### Negotiations
```
GET    /api/negotiations         # List negotiations
POST   /api/negotiations         # Create negotiation
PUT    /api/negotiations/{id}    # Update negotiation
POST   /api/negotiations/{id}/accept # Accept offer
POST   /api/negotiations/{id}/reject # Reject offer
```

#### Chat
```
GET    /api/chats                # List chats
POST   /api/chats                # Create chat
GET    /api/chats/{id}/messages  # Get messages
POST   /api/chats/{id}/messages  # Send message
```

#### Important Contacts
```
GET    /api/contacts             # List emergency contacts
POST   /api/contacts             # Add contact
DELETE /api/contacts/{id}        # Remove contact
```

#### Locations
```
GET    /api/route-stages         # List route stages
POST   /api/route-stages         # Create route stage
GET    /api/locations/search     # Search locations (Canadian cities)
```

## Admin Panel

Access the admin panel at: `/admin`

Default credentials (after seeding):
- Email: admin@negoride.ca
- Password: admin

### Admin Features
- 👥 User Management (Riders & Drivers)
- 🚗 Trip Monitoring & Management
- 💰 Payment Tracking & Reports
- 📊 Analytics Dashboard
- 📈 Revenue Reports
- ⚙️ System Settings
- 🗺️ Route Management
- ⭐ Reviews & Ratings
- 📧 Email Templates
- 🔔 Notification Management

## Configuration

### OneSignal Setup
1. Create OneSignal account
2. Create new app for Negoride Canada
3. Get App ID and REST API Key
4. Update `.env`:
```env
ONESIGNAL_APP_ID=your_app_id
ONESIGNAL_REST_API_KEY=your_api_key
```

### Payment Gateway (Example: Stripe Canada)
```env
STRIPE_KEY=your_stripe_publishable_key
STRIPE_SECRET=your_stripe_secret_key
STRIPE_CURRENCY=CAD
```

### Email Configuration
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your_email@gmail.com
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@negoride.ca
MAIL_FROM_NAME="Negoride Canada"
```

## Development

### Running Migrations
```bash
# Run all migrations
php artisan migrate

# Rollback last migration
php artisan migrate:rollback

# Fresh migration (drop all tables)
php artisan migrate:fresh

# Fresh with seeding
php artisan migrate:fresh --seed
```

### Cache Management
```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Cache configurations (production)
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Database Seeding
```bash
# Seed all seeders
php artisan db:seed

# Seed specific seeder
php artisan db:seed --class=UsersTableSeeder
```

### Queue Workers
```bash
# Run queue worker
php artisan queue:work

# With specific connection
php artisan queue:work database

# Restart workers after code changes
php artisan queue:restart
```

## Testing

```bash
# Run all tests
php artisan test

# Run specific test
php artisan test --filter UserTest

# With coverage
php artisan test --coverage
```

## Deployment

### Production Checklist
- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Generate new `APP_KEY`
- [ ] Configure production database
- [ ] Update `APP_URL` to production domain
- [ ] Set up SSL certificate
- [ ] Configure CORS for mobile app domains
- [ ] Set up automated database backups
- [ ] Configure queue workers (Supervisor)
- [ ] Set up cron jobs for scheduled tasks
- [ ] Enable error logging and monitoring
- [ ] Configure rate limiting
- [ ] Set up CDN for assets (optional)
- [ ] Configure email service
- [ ] Test all API endpoints

### Server Configuration

#### Nginx Configuration Example
```nginx
server {
    listen 80;
    server_name api.negoride.ca;
    root /var/www/negoride-canada-api/public;

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
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

#### Cron Jobs
Add to crontab:
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

#### Supervisor Configuration
```ini
[program:negoride-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/negoride-canada-api/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/negoride-canada-api/storage/logs/worker.log
```

## Database Schema

### Key Tables
- `admin_users` - System users (riders, drivers, admins)
- `trips` - Trip listings
- `trip_bookings` - Ride bookings
- `negotiations` - Price negotiations
- `chats` / `chat_messages` - In-app messaging
- `route_stages` - Trip route stages
- `important_contacts` - Emergency contacts

## Security

- JWT token authentication
- CORS protection
- SQL injection prevention (Eloquent ORM)
- XSS protection
- CSRF protection
- Rate limiting
- Password hashing (bcrypt)
- API key management

## Monitoring & Logging

### Logs Location
```
storage/logs/laravel.log
```

### Enable Debug Mode (Development Only)
```env
APP_DEBUG=true
LOG_LEVEL=debug
```

### Production Logging
```env
LOG_CHANNEL=stack
LOG_LEVEL=error
```

## API Rate Limiting

Default rate limits (can be configured in `app/Http/Kernel.php`):
- 60 requests per minute for authenticated users
- 10 requests per minute for unauthenticated users

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## Versioning

We use [SemVer](http://semver.org/) for versioning.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

- 📧 **Technical Support:** dev@negoride.ca
- 📞 **Phone:** 1-800-NEGORIDE
- 🌐 **Website:** https://negoride.ca
- 📚 **Documentation:** https://docs.negoride.ca

## Acknowledgments

- Laravel team for the excellent framework
- Laravel Admin for the admin panel
- OneSignal for push notification service
- All contributors and beta testers

---

**Negoride Canada API** - Powering Canadian Rideshare 🍁

*Built with ❤️ for the Canadian market*
