#!/bin/bash

# Diecast Empire Development Setup Script
echo "🚀 Setting up Diecast Empire development environment..."

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo "❌ Docker is not installed. Please install Docker first."
    exit 1
fi

if ! command -v docker-compose &> /dev/null; then
    echo "❌ Docker Compose is not installed. Please install Docker Compose first."
    exit 1
fi

# Create necessary directories
echo "📁 Creating necessary directories..."
mkdir -p backend/storage/app/public
mkdir -p backend/storage/framework/cache/data
mkdir -p backend/storage/framework/sessions
mkdir -p backend/storage/framework/views
mkdir -p backend/storage/logs
mkdir -p backend/bootstrap/cache

# Set proper permissions
echo "🔐 Setting proper permissions..."
chmod -R 775 backend/storage
chmod -R 775 backend/bootstrap/cache

# Copy environment files
echo "📋 Setting up environment files..."
if [ ! -f backend/.env ]; then
    cp backend/.env.example backend/.env
    echo "✅ Backend .env file created"
else
    echo "⚠️  Backend .env file already exists"
fi

# Start Docker containers
echo "🐳 Starting Docker containers..."
docker-compose up -d

# Wait for MySQL to be ready
echo "⏳ Waiting for MySQL to be ready..."
sleep 30

# Install backend dependencies
echo "📦 Installing backend dependencies..."
docker-compose exec -T app composer install --no-interaction --prefer-dist --optimize-autoloader

# Generate application key
echo "🔑 Generating application key..."
docker-compose exec -T app php artisan key:generate

# Run database migrations
echo "🗄️  Running database migrations..."
docker-compose exec -T app php artisan migrate --force

# Create storage link
echo "🔗 Creating storage link..."
docker-compose exec -T app php artisan storage:link

# Clear and cache config
echo "🧹 Clearing and caching configuration..."
docker-compose exec -T app php artisan config:clear
docker-compose exec -T app php artisan config:cache
docker-compose exec -T app php artisan route:cache

# Install frontend dependencies
echo "📦 Installing frontend dependencies..."
cd frontend
if command -v npm &> /dev/null; then
    npm install
    echo "✅ Frontend dependencies installed"
else
    echo "⚠️  npm not found. Please install Node.js to set up the frontend."
fi
cd ..

# Display access information
echo ""
echo "🎉 Setup complete! Your Diecast Empire development environment is ready."
echo ""
echo "📍 Access URLs:"
echo "   Frontend:    http://localhost:3000"
echo "   Backend API: http://localhost:8080/api"
echo "   MySQL:       localhost:3306"
echo "   Redis:       localhost:6379"
echo ""
echo "🔧 Useful commands:"
echo "   Start services:     docker-compose up -d"
echo "   Stop services:      docker-compose down"
echo "   View logs:          docker-compose logs -f"
echo "   Backend shell:      docker-compose exec app bash"
echo "   Run migrations:     docker-compose exec app php artisan migrate"
echo "   Clear cache:        docker-compose exec app php artisan cache:clear"
echo ""
echo "📚 Next steps:"
echo "   1. Start the frontend: cd frontend && npm start"
echo "   2. Visit http://localhost:3000 to see the application"
echo "   3. Check the API at http://localhost:8080/api/health"
echo ""