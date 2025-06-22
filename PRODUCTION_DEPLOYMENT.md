# Production Deployment Guide

## Security Checklist for Production

### 1. Environment Variables

**CRITICAL: Replace all placeholder values with strong, unique passwords and secrets**

```bash
# Generate strong passwords (run these commands)
openssl rand -base64 32  # For APP_SECRET
openssl rand -base64 32  # For JWT_PASSPHRASE
openssl rand -base64 32  # For MYSQL_PASSWORD
openssl rand -base64 32  # For MYSQL_ROOT_PASSWORD
```

### 2. Required Changes for Production

#### A. Database Security
- ✅ Change `MYSQL_USER` from `root` to a dedicated user (e.g., `cloud_user`)
- ✅ Use strong passwords (minimum 16 characters, mix of letters, numbers, symbols)
- ✅ Change database name from `cloud` to `cloud_prod`
- ✅ Update `DATABASE_URL` to use the new credentials

#### B. Application Security
- ✅ Change `APP_ENV` from `dev` to `prod`
- ✅ Generate a new `APP_SECRET` (32+ character random string)
- ✅ Generate a new `JWT_PASSPHRASE` (32+ character random string)
- ✅ Update CORS origins to your actual domain

#### C. Network Security
- ✅ Database port only accessible from localhost (`127.0.0.1:3306`)
- ✅ phpMyAdmin only accessible from localhost (`127.0.0.1:8081`)
- ✅ Add restart policies (`restart: unless-stopped`)

### 3. SSL/HTTPS Setup

#### Generate SSL Certificates
```bash
# Create SSL directory
mkdir -p ssl

# Generate self-signed certificate (for testing)
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout ssl/nginx.key -out ssl/nginx.crt \
  -subj "/C=US/ST=State/L=City/O=Organization/CN=yourdomain.com"

# For production, use Let's Encrypt or commercial certificates
```

#### Update Nginx Configuration
Uncomment SSL lines in `docker-compose.prod.yml`:
```yaml
volumes:
  - ./ssl/nginx.crt:/etc/nginx/ssl/cert.pem
  - ./ssl/nginx.key:/etc/nginx/ssl/key.pem
```

### 4. Production Deployment Steps

#### Step 1: Create Production Environment File
```bash
# Copy the example and customize it
cp env.production.example .env.prod

# Edit with your actual values
nano .env.prod
```

#### Step 2: Generate JWT Keys
```bash
# Generate JWT private key
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096

# Generate JWT public key
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout

# Set proper permissions
chmod 644 config/jwt/public.pem
chmod 600 config/jwt/private.pem
```

#### Step 3: Build and Deploy
```bash
# Build production images
docker-compose -f docker-compose.prod.yml --env-file .env.prod build

# Start production services
docker-compose -f docker-compose.prod.yml --env-file .env.prod up -d

# Run database migrations
docker-compose -f docker-compose.prod.yml --env-file .env.prod exec php bin/console doctrine:migrations:migrate --no-interaction

# Clear and warmup cache
docker-compose -f docker-compose.prod.yml --env-file .env.prod exec php bin/console cache:clear --env=prod
docker-compose -f docker-compose.prod.yml --env-file .env.prod exec php bin/console cache:warmup --env=prod
```

### 5. Security Recommendations

#### Remove Development Tools
- ❌ Remove phpMyAdmin from production (or secure with authentication)
- ❌ Disable debug mode in Symfony
- ❌ Remove development routes and endpoints

#### Additional Security Measures
- ✅ Set up firewall rules
- ✅ Configure rate limiting in Nginx
- ✅ Enable HTTPS redirect
- ✅ Set secure headers
- ✅ Regular security updates
- ✅ Database backups
- ✅ Log monitoring

### 6. Monitoring and Maintenance

#### Health Checks
```bash
# Check service status
docker-compose -f docker-compose.prod.yml ps

# Check logs
docker-compose -f docker-compose.prod.yml logs -f

# Database backup
docker-compose -f docker-compose.prod.yml exec database mysqldump -u root -p cloud_prod > backup.sql
```

#### Update Process
```bash
# Pull latest code
git pull origin main

# Rebuild and restart
docker-compose -f docker-compose.prod.yml --env-file .env.prod down
docker-compose -f docker-compose.prod.yml --env-file .env.prod build
docker-compose -f docker-compose.prod.yml --env-file .env.prod up -d
```

### 7. Environment Variables Template

Create your `.env.prod` file with these values:

```bash
APP_ENV=prod
APP_SECRET=your-generated-32-char-secret
DATABASE_URL="mysql://cloud_user:your-strong-db-password@database:3306/cloud_prod?serverVersion=10.6.12&charset=utf8"
MYSQL_DATABASE=cloud_prod
MYSQL_USER=cloud_user
MYSQL_PASSWORD=your-strong-db-password
MYSQL_ROOT_PASSWORD=your-very-strong-root-password
MYSQL_VERSION=10.6.12
CORS_ALLOW_ORIGIN='^https?://(yourdomain\.com|www\.yourdomain\.com)$'
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=your-generated-jwt-passphrase
```

**Remember: Never commit your actual `.env.prod` file to version control!**