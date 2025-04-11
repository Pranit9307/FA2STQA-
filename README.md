# EventHub - Event Management System

A comprehensive event management platform built with PHP and MySQL.

## Features

- User authentication and profile management
- Event creation and management
- Event discovery and recommendations
- Social features (following, sharing)
- Event ratings and reviews
- Location-based event search
- Admin dashboard

## Setup

1. Requirements:
   - PHP 7.4+
   - MySQL 5.7+
   - Apache/Nginx
   - Composer

2. Installation:
   ```bash
   git clone [repository-url]
   cd SDL_final
   composer install
   ```

3. Database Setup:
   - Create MySQL database
   - Import `sql/database.sql`
   - Configure `config/database.php`

4. Web Server:
   - Point document root to project directory
   - Enable mod_rewrite
   - Set uploads directory permissions

## Project Structure

```
SDL_final/
├── assets/          # Static files
├── config/          # Configuration files
├── includes/        # Common components
├── sql/            # Database schema
└── *.php           # Application files
```

## Key Files

- `index.php` - Homepage
- `events.php` - Event listing
- `event_details.php` - Event view
- `create_event.php` - Event creation
- `user_interests.php` - Recommendations
- `social_features.php` - Social features

## Database Tables

- `users` - User accounts
- `events` - Event information
- `categories` - Event categories
- `rsvps` - Event attendance
- `event_ratings` - Reviews
- `user_interests` - User preferences

## Security

- Password hashing
- SQL injection prevention
- XSS protection
- CSRF protection
- Input validation

## Support

For issues and support:
1. Check documentation
2. Search existing issues
3. Create new issue if needed

## License

MIT License
