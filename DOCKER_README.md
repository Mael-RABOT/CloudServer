# Docker Setup for Symfony Project

This Docker setup includes:
- **PHP-FPM container** for running your Symfony application
- **MariaDB database** for data storage
- **phpMyAdmin** for database management
- **Nginx** web server

## Quick Start

1. **Copy environment variables** (if you don't have a `.env` file):
   ```bash
   cp .env.example .env
   ```

2. **Build and start the containers**:
   ```bash
   docker-compose up -d --build
   ```

3. **Install Symfony dependencies**:
   ```bash
   docker-compose exec php composer install
   ```

4. **Set up the database**:
   ```bash
   docker-compose exec php php bin/console doctrine:database:create
   docker-compose exec php php bin/console doctrine:migrations:migrate
   ```

## Access Points

- **Symfony Application**: http://localhost:8080
- **phpMyAdmin**: http://localhost:8081
- **MariaDB**: localhost:3306

## Environment Variables

Create a `.env` file with the following variables:

```env
# Symfony Environment
APP_ENV=dev
APP_SECRET=your-secret-key-here

# Database Configuration
MYSQL_VERSION=10.6.12
MYSQL_DATABASE=app
MYSQL_USER=app
MYSQL_PASSWORD=!ChangeMe!
MYSQL_ROOT_PASSWORD=!ChangeMe!

# Symfony Database URL (auto-generated from above variables)
DATABASE_URL=mysql://${MYSQL_USER}:${MYSQL_PASSWORD}@database:3306/${MYSQL_DATABASE}?serverVersion=${MYSQL_VERSION}
```

## Useful Commands

- **View logs**: `docker-compose logs -f`
- **Stop containers**: `docker-compose down`
- **Rebuild containers**: `docker-compose up -d --build`
- **Access PHP container**: `docker-compose exec php bash`
- **Run Symfony commands**: `docker-compose exec php php bin/console [command]`

## phpMyAdmin Access

- **Server**: database
- **Username**: app (or root for full access)
- **Password**: Use the MYSQL_PASSWORD or MYSQL_ROOT_PASSWORD from your .env file

## Security Notes

- Change all default passwords in production
- The database is exposed on port 3306 for development only
- Consider using Docker secrets for production deployments