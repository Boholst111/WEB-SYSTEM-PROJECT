#!/bin/bash

# Diecast Empire - Staging Deployment Script
# This script deploys the application to the staging environment

set -e  # Exit on error

echo "========================================="
echo "Diecast Empire - Staging Deployment"
echo "========================================="

# Configuration
ENVIRONMENT="staging"
DOCKER_COMPOSE_FILE="docker-compose.staging.yml"

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Function to print colored output
print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

# Check if .env files exist
echo ""
echo "Step 1: Checking environment configuration..."
if [ ! -f "backend/.env.staging" ]; then
    print_warning "backend/.env.staging not found, copying from .env.production"
    cp backend/.env.production backend/.env.staging
fi

if [ ! -f "frontend/.env.production" ]; then
    print_error "frontend/.env.production not found"
    exit 1
fi

print_success "Environment configuration checked"

# Pull latest code
echo ""
echo "Step 2: Pulling latest code..."
git pull origin staging
print_success "Code updated"

# Build Docker images
echo ""
echo "Step 3: Building Docker images..."
docker-compose -f $DOCKER_COMPOSE_FILE build --no-cache
print_success "Docker images built"

# Stop existing containers
echo ""
echo "Step 4: Stopping existing containers..."
docker-compose -f $DOCKER_COMPOSE_FILE down
print_success "Containers stopped"

# Start new containers
echo ""
echo "Step 5: Starting new containers..."
docker-compose -f $DOCKER_COMPOSE_FILE up -d
print_success "Containers started"

# Wait for services to be ready
echo ""
echo "Step 6: Waiting for services to be ready..."
sleep 10
print_success "Services ready"

# Run database migrations
echo ""
echo "Step 7: Running database migrations..."
docker-compose -f $DOCKER_COMPOSE_FILE exec -T app php artisan migrate --force
print_success "Migrations completed"

# Clear and optimize caches
echo ""
echo "Step 8: Optimizing application..."
docker-compose -f $DOCKER_COMPOSE_FILE exec -T app php artisan config:cache
docker-compose -f $DOCKER_COMPOSE_FILE exec -T app php artisan route:cache
docker-compose -f $DOCKER_COMPOSE_FILE exec -T app php artisan view:cache
docker-compose -f $DOCKER_COMPOSE_FILE exec -T app php artisan optimize
print_success "Application optimized"

# Run health check
echo ""
echo "Step 9: Running health check..."
sleep 5
HEALTH_CHECK=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8081/api/health)

if [ "$HEALTH_CHECK" = "200" ]; then
    print_success "Health check passed"
else
    print_error "Health check failed (HTTP $HEALTH_CHECK)"
    exit 1
fi

# Run smoke tests
echo ""
echo "Step 10: Running smoke tests..."
docker-compose -f $DOCKER_COMPOSE_FILE exec -T app php artisan test --filter=SystemIntegrationTest::test_health_check_integration
print_success "Smoke tests passed"

# Display deployment summary
echo ""
echo "========================================="
echo "Deployment Summary"
echo "========================================="
echo "Environment: $ENVIRONMENT"
echo "Backend URL: http://localhost:8081"
echo "Frontend URL: http://localhost:3001"
echo "Database: MySQL (port 3307)"
echo "Cache: Redis (port 6380)"
echo ""
print_success "Deployment completed successfully!"
echo ""
echo "Next steps:"
echo "1. Verify application functionality"
echo "2. Run full test suite"
echo "3. Monitor logs for errors"
echo ""
echo "View logs: docker-compose -f $DOCKER_COMPOSE_FILE logs -f"
echo "========================================="
