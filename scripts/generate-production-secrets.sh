#!/bin/bash

# Production Secret Generator
# This script generates secure secrets for production deployment

echo "🔐 Generating Production Secrets"
echo "=================================="

# Create scripts directory if it doesn't exist
mkdir -p scripts

# Generate APP_SECRET
APP_SECRET=$(openssl rand -base64 32)
echo "APP_SECRET=$APP_SECRET"

# Generate JWT_PASSPHRASE
JWT_PASSPHRASE=$(openssl rand -base64 32)
echo "JWT_PASSPHRASE=$JWT_PASSPHRASE"

# Generate MYSQL_PASSWORD
MYSQL_PASSWORD=$(openssl rand -base64 32)
echo "MYSQL_PASSWORD=$MYSQL_PASSWORD"

# Generate MYSQL_ROOT_PASSWORD
MYSQL_ROOT_PASSWORD=$(openssl rand -base64 32)
echo "MYSQL_ROOT_PASSWORD=$MYSQL_ROOT_PASSWORD"

echo ""
echo "📝 Copy these values to your .env.prod file:"
echo "============================================="
echo "APP_SECRET=$APP_SECRET"
echo "JWT_PASSPHRASE=$JWT_PASSPHRASE"
echo "MYSQL_PASSWORD=$MYSQL_PASSWORD"
echo "MYSQL_ROOT_PASSWORD=$MYSQL_ROOT_PASSWORD"
echo ""

# Generate JWT keys if config/jwt directory exists
if [ -d "config/jwt" ]; then
    echo "🔑 Generating JWT Keys..."

    # Generate private key
    openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096 -pass pass:"$JWT_PASSPHRASE"

    # Generate public key
    openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout -passin pass:"$JWT_PASSPHRASE"

    # Set proper permissions
    chmod 644 config/jwt/public.pem
    chmod 600 config/jwt/private.pem

    echo "✅ JWT keys generated successfully!"
else
    echo "⚠️  config/jwt directory not found. Create it first:"
    echo "   mkdir -p config/jwt"
fi

echo ""
echo "🚀 Next steps:"
echo "1. Create .env.prod file with the generated secrets"
echo "2. Update your domain in CORS_ALLOW_ORIGIN"
echo "3. Run: docker-compose -f docker-compose.prod.yml --env-file .env.prod up -d"
echo ""
echo "⚠️  IMPORTANT: Keep these secrets secure and never commit them to version control!"