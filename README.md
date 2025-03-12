# PadelLesManager

A PHP-based application for managing padel lessons and students.

## Requirements

- PHP 8.2 or newer
- MySQL 5.7 or newer
- Composer

## Installation

1. Clone the repository:
```bash
git clone https://github.com/yourusername/PadelLesManager.git
cd PadelLesManager
```

2. Install dependencies:
```bash
composer install
```

3. Create environment file:
```bash
cp .env.example .env
```

4. Update the `.env` file with your database credentials and other configuration settings.

5. Create the database and tables:
```bash
mysql -u your_username -p < database/schema.sql
```

6. Set up your web server (Apache/Nginx) to point to the `public` directory.

7. Make sure the following directories are writable by your web server:
   - `storage/logs`
   - `storage/cache`

## Features

- Secure authentication system
- Student management
- Lesson scheduling
- Attendance tracking
- User-friendly interface

## Security

- Password hashing using PHP's built-in functions
- Session-based authentication
- SQL injection protection through prepared statements
- XSS protection through proper output escaping

## Directory Structure

```
PadelLesManager/
├── composer.json
├── database/
│   └── schema.sql
├── public/
│   ├── index.php
│   └── assets/
├── src/
│   ├── Database/
│   ├── Models/
│   └── Middleware/
└── storage/
    ├── logs/
    └── cache/
```

## Usage

1. Access the application through your web browser
2. Log in using your credentials
3. Use the dashboard to manage students and lessons

## Contributing

1. Fork the repository
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Create a new Pull Request 