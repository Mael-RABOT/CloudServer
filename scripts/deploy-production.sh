#!/bin/bash

set -e

echo "🚀 Starting production deployment for cloud.api.maelrabot.com"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running as root
if [[ $EUID -eq 0 ]]; then
   print_error "This script should not be run as root"
   exit 1
fi

# Check if .env.production exists
if [ ! -f .env.production ]; then
    print_error ".env.production file not found!"
    echo "Please copy env.production.example to .env.production and configure it:"
    echo "cp env.production.example .env.production"
    echo "nano .env.production"
    exit 1
fi

# Load environment variables
source .env.production

print_status "Building FrankenPHP container..."
docker-compose -f docker-compose.prod.yml build

print_status "Stopping existing containers..."
docker-compose -f docker-compose.prod.yml down

print_status "Starting FrankenPHP container..."
docker-compose -f docker-compose.prod.yml up -d

print_status "Waiting for container to be ready..."
sleep 10

# Check if container is running
if ! docker-compose -f docker-compose.prod.yml ps | grep -q "Up"; then
    print_error "Container failed to start!"
    docker-compose -f docker-compose.prod.yml logs
    exit 1
fi

print_status "Running database migrations..."
docker-compose -f docker-compose.prod.yml exec frankenphp php bin/console doctrine:migrations:migrate --no-interaction --env=prod

print_status "Clearing and warming up cache..."
docker-compose -f docker-compose.prod.yml exec frankenphp php bin/console cache:clear --env=prod --no-debug
docker-compose -f docker-compose.prod.yml exec frankenphp php bin/console cache:warmup --env=prod

print_status "Setting proper permissions..."
docker-compose -f docker-compose.prod.yml exec frankenphp chown -R symfony:symfony var/

print_status "Testing API endpoint..."
if curl -f -s http://localhost:8080/health > /dev/null; then
    print_status "✅ API is responding correctly!"
else
    print_warning "⚠️  API health check failed, but container is running"
fi

print_status "🎉 Deployment completed!"
echo ""
echo "Next steps:"
echo "1. Copy the Apache configuration:"
echo "   sudo cp apache/cloud.api.maelrabot.com.conf /etc/apache2/sites-available/"
echo ""
echo "2. Enable the site:"
echo "   sudo a2ensite cloud.api.maelrabot.com.conf"
echo ""
echo "3. Enable required Apache modules:"
echo "   sudo a2enmod proxy proxy_http headers rewrite ssl"
echo ""
echo "4. Test Apache configuration:"
echo "   sudo apache2ctl configtest"
echo ""
echo "5. Reload Apache:"
echo "   sudo systemctl reload apache2"
echo ""
echo "6. Set up DNS:"
echo "   Add A record: cloud.api.maelrabot.com -> YOUR_VPS_IP"
echo ""
echo "7. Set up SSL certificates (if not already done):"
echo "   sudo certbot --apache -d cloud.api.maelrabot.com"
echo ""
echo "Your API will be available at: https://cloud.api.maelrabot.com"