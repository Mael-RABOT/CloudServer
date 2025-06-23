# Production Deployment Guide

This guide will help you deploy your Symfony REST API to production using FrankenPHP and Apache as a reverse proxy.

## Architecture Overview

```
Internet → Apache (SSL/TLS) → FrankenPHP Container → External MariaDB
```

- **Apache**: Handles SSL termination and reverse proxy to FrankenPHP
- **FrankenPHP**: High-performance PHP server running your Symfony application
- **External MariaDB**: Your existing MariaDB installation on the VPS

## Prerequisites

1. **VPS with Docker installed**
2. **Apache web server running**
3. **MariaDB/MySQL running on the host**
4. **Domain name configured** (cloud.api.maelrabot.com)
5. **SSL certificates** (Let's Encrypt recommended)

## Step 1: Prepare Your VPS

### Install Docker and Docker Compose
```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# Add user to docker group
sudo usermod -aG docker $USER

# Install Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# Logout and login again for group changes to take effect
```

### Enable Apache Modules
```bash
sudo a2enmod proxy
sudo a2enmod proxy_http
sudo a2enmod headers
sudo a2enmod rewrite
sudo a2enmod ssl
sudo systemctl reload apache2
```

## Step 2: Database Setup

### Create Database and User
```sql
-- Connect to MariaDB as root
mysql -u root -p

-- Create database
CREATE DATABASE symfony_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user
CREATE USER 'symfony_user'@'%' IDENTIFIED BY 'your-secure-password';

-- Grant privileges
GRANT ALL PRIVILEGES ON symfony_prod.* TO 'symfony_user'@'%';
FLUSH PRIVILEGES;

-- Exit
EXIT;
```

## Step 3: Application Deployment

### 1. Clone/Upload Your Code
```bash
# Create application directory
mkdir -p /var/www/cloud-api
cd /var/www/cloud-api

# Upload your code here or clone from git
# git clone your-repository-url .
```

### 2. Configure Environment
```bash
# Copy environment template
cp env.production.example .env.production

# Edit environment file
nano .env.production
```

**Important environment variables to set:**
```env
APP_ENV=prod
APP_SECRET=your-long-random-secret-key
MYSQL_DATABASE=symfony_prod
MYSQL_USER=symfony_user
MYSQL_PASSWORD=your-secure-password
MYSQL_VERSION=10.11
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PASSPHRASE=your-jwt-passphrase
```

### 3. Generate JWT Keys
```bash
# Generate JWT private key
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096

# Generate JWT public key
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout

# Set proper permissions
chmod 644 config/jwt/public.pem
chmod 600 config/jwt/private.pem
```

### 4. Deploy Application
```bash
# Make deployment script executable
chmod +x scripts/deploy-production.sh

# Run deployment
./scripts/deploy-production.sh
```

## Step 4: Apache Configuration

### 1. Copy Apache Configuration
```bash
sudo cp apache/cloud.api.maelrabot.com.conf /etc/apache2/sites-available/
```

### 2. Update SSL Certificate Paths
Edit the Apache configuration to match your SSL certificate paths:
```bash
sudo nano /etc/apache2/sites-available/cloud.api.maelrabot.com.conf
```

Update these lines with your actual certificate paths:
```apache
SSLCertificateFile /path/to/your/certificate.crt
SSLCertificateKeyFile /path/to/your/private.key
SSLCertificateChainFile /path/to/your/chain.crt
```

### 3. Enable Site
```bash
sudo a2ensite cloud.api.maelrabot.com.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
```

## Step 5: DNS Configuration

Add an A record in your DNS provider:
```
Type: A
Name: cloud.api
Value: YOUR_VPS_IP_ADDRESS
TTL: 300
```

## Step 6: SSL Certificate (Let's Encrypt)

### Install Certbot
```bash
sudo apt install certbot python3-certbot-apache
```

### Obtain SSL Certificate
```bash
sudo certbot --apache -d cloud.api.maelrabot.com
```

### Auto-renewal
```bash
# Test auto-renewal
sudo certbot renew --dry-run

# Add to crontab for auto-renewal
sudo crontab -e
# Add this line:
# 0 12 * * * /usr/bin/certbot renew --quiet
```

## Step 7: Security Hardening

### 1. Firewall Configuration
```bash
# Install UFW if not installed
sudo apt install ufw

# Configure firewall
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow ssh
sudo ufw allow 80
sudo ufw allow 443
sudo ufw enable
```

### 2. MariaDB Security
```bash
# Secure MariaDB installation
sudo mysql_secure_installation
```

### 3. File Permissions
```bash
# Set proper ownership
sudo chown -R www-data:www-data /var/www/cloud-api

# Set proper permissions
sudo chmod -R 755 /var/www/cloud-api
sudo chmod -R 775 /var/www/cloud-api/var
```

## Step 8: Monitoring and Logs

### View Application Logs
```bash
# FrankenPHP logs
docker-compose -f docker-compose.prod.yml logs -f frankenphp

# Apache logs
sudo tail -f /var/log/apache2/cloud.api.maelrabot.com_error.log
sudo tail -f /var/log/apache2/cloud.api.maelrabot.com_access.log
```

### Health Check
```bash
# Test API health
curl -k https://cloud.api.maelrabot.com/health
```

## Step 9: Maintenance

### Update Application
```bash
cd /var/www/cloud-api

# Pull latest changes
git pull origin main

# Rebuild and restart
docker-compose -f docker-compose.prod.yml down
docker-compose -f docker-compose.prod.yml build
docker-compose -f docker-compose.prod.yml up -d

# Run migrations
docker-compose -f docker-compose.prod.yml exec frankenphp php bin/console doctrine:migrations:migrate --no-interaction --env=prod

# Clear cache
docker-compose -f docker-compose.prod.yml exec frankenphp php bin/console cache:clear --env=prod
```

### Backup Database
```bash
# Create backup script
sudo nano /usr/local/bin/backup-database.sh
```

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/mysql"
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -u symfony_user -p symfony_prod > $BACKUP_DIR/symfony_prod_$DATE.sql
find $BACKUP_DIR -name "*.sql" -mtime +7 -delete
```

```bash
chmod +x /usr/local/bin/backup-database.sh

# Add to crontab for daily backups
sudo crontab -e
# Add this line:
# 0 2 * * * /usr/local/bin/backup-database.sh
```

## Troubleshooting

### Common Issues

1. **Container won't start**
   ```bash
   docker-compose -f docker-compose.prod.yml logs frankenphp
   ```

2. **Database connection issues**
   - Check if MariaDB is running: `sudo systemctl status mariadb`
   - Verify credentials in `.env.production`
   - Check firewall rules

3. **Apache proxy issues**
   - Check Apache error logs: `sudo tail -f /var/log/apache2/error.log`
   - Verify modules are enabled: `sudo apache2ctl -M | grep proxy`

4. **SSL certificate issues**
   - Check certificate validity: `sudo certbot certificates`
   - Renew if needed: `sudo certbot renew`

### Performance Optimization

1. **Enable OPcache**
   - Already configured in the FrankenPHP setup

2. **Database optimization**
   ```sql
   -- Add indexes for better performance
   -- Optimize tables
   OPTIMIZE TABLE your_table_name;
   ```

3. **Monitoring**
   - Consider setting up monitoring with tools like Prometheus/Grafana
   - Monitor disk space, memory usage, and database performance

## Support

If you encounter issues:
1. Check the logs first
2. Verify all prerequisites are met
3. Ensure proper file permissions
4. Test each component individually

Your API should now be accessible at `https://cloud.api.maelrabot.com`!